'use strict';

// Executar com o servidor real: php extra/acoustic-lab/server.php
const { test, before, after, beforeEach, afterEach } = require('node:test');
const assert = require('node:assert/strict');
const { writeFile, unlink } = require('node:fs/promises');
const { existsSync } = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const baseURL = process.env.ACOUSTIC_LAB_URL || 'http://127.0.0.1:9510';
const prefix = `acoustic-lab-test-${process.pid}`;
const fixtures = [
  { name: `${prefix}-mono.wav`, seconds: 4, rate: 8000, channels: 1 },
  { name: `${prefix}-stereo.wav`, seconds: 4, rate: 44100, channels: 2 },
  { name: `${prefix}-short.wav`, seconds: 0.6, rate: 8000, channels: 1 },
];
let browser, page, errors;

function wav({ seconds, rate, channels }) {
  const samples = Math.round(seconds * rate);
  const buffer = Buffer.alloc(44 + samples * channels * 2);
  buffer.write('RIFF', 0);
  buffer.writeUInt32LE(buffer.length - 8, 4);
  buffer.write('WAVEfmt ', 8);
  buffer.writeUInt32LE(16, 16);
  buffer.writeUInt16LE(1, 20);
  buffer.writeUInt16LE(channels, 22);
  buffer.writeUInt32LE(rate, 24);
  buffer.writeUInt32LE(rate * channels * 2, 28);
  buffer.writeUInt16LE(channels * 2, 32);
  buffer.writeUInt16LE(16, 34);
  buffer.write('data', 36);
  buffer.writeUInt32LE(buffer.length - 44, 40);
  for (let i = 0; i < samples; i++) {
    const value = i / rate < 2 ? Math.round(10000 * Math.sin(2 * Math.PI * 425 * i / rate)) : 0;
    for (let ch = 0; ch < channels; ch++) buffer.writeInt16LE(value, 44 + (i * channels + ch) * 2);
  }
  return buffer;
}

async function select(index = 0, wait = true) {
  await page.waitForFunction(() => document.querySelector('#fileList').options.length);
  await page.selectOption('#fileList', `lab:${fixtures[index].name}`);
  if (wait) await page.waitForFunction(() => document.querySelector('audio').readyState >= 1);
}
async function startPlaying() {
  await page.click('#start');
  await page.waitForFunction(() => !document.querySelector('audio').paused && document.querySelector('audio').currentTime > 0.05);
}
async function snapshot() {
  return page.evaluate(() => {
    const audio = document.querySelector('audio');
    const timestamps = [...document.querySelectorAll('#events tr td:first-child')].map((cell) => {
      const [m, s] = cell.textContent.split(':').map(Number);
      return (m * 60 + s) * 1000;
    });
    return {
      time: audio.currentTime, paused: audio.paused, src: audio.src,
      message: document.querySelector('#connection').textContent,
      bad: document.querySelector('#connection').classList.contains('bad'),
      events: document.querySelector('#events').textContent,
      signal: document.querySelector('#signal').textContent,
      counts: { ...window.calls }, timestamps,
    };
  });
}

before(async () => {
  await Promise.all(fixtures.map((fixture) => writeFile(path.join(__dirname, '../audio', fixture.name), wav(fixture))));
  const response = await fetch(`${baseURL}/api/files`);
  assert.ok(response.ok, 'O servidor Acoustic Lab deve estar em execução');
  const executablePath = process.env.CHROME_BIN || (existsSync('/usr/bin/google-chrome') ? '/usr/bin/google-chrome' : undefined);
  browser = await chromium.launch({ executablePath, headless: true });
});
after(async () => {
  await browser?.close();
  await Promise.all(fixtures.map((fixture) => unlink(path.join(__dirname, '../audio', fixture.name)).catch(() => {})));
});
beforeEach(async () => {
  errors = [];
  page = await browser.newPage();
  page.on('pageerror', (error) => errors.push(error.message));
  await page.addInitScript(() => {
    window.calls = { play: 0, pause: 0, load: 0, streams: 0 };
    window.streams = [];
    for (const name of ['play', 'pause', 'load']) {
      const original = HTMLMediaElement.prototype[name];
      HTMLMediaElement.prototype[name] = function (...args) {
        window.calls[name]++;
        return original.apply(this, args);
      };
    }
    const OriginalEventSource = window.EventSource;
    window.EventSource = class extends OriginalEventSource {
      constructor(...args) { super(...args); window.calls.streams++; window.streams.push(this); }
    };
  });
  await page.goto(baseURL);
  await select();
});
afterEach(async () => {
  await page.close();
  assert.deepEqual(errors, [], 'Não deve haver rejeições de Promise sem tratamento');
});

test('primeira reprodução: eventos acústicos não recarregam nem pausam o áudio', async () => {
  await startPlaying();
  await page.waitForTimeout(1400);
  const state = await snapshot();
  assert.equal(state.bad, false);
  assert.equal(state.paused, false);
  assert.deepEqual(state.counts, { play: 1, pause: 0, load: 0, streams: 1 });
  assert.ok(state.timestamps.length > 0, 'O AcousticStreamAnalyzer deve produzir eventos reais');
  assert.ok(state.timestamps.every((at) => at <= state.time * 1000));
});

test('pausar e continuar preserva a sessão e congela a apresentação no relógio do áudio', async () => {
  await startPlaying();
  await page.evaluate(() => document.querySelector('audio').pause());
  await page.waitForFunction(() => document.querySelector('#start').textContent === 'Continuar análise');
  await page.waitForTimeout(150);
  const paused = await snapshot();
  await page.waitForTimeout(1000);
  const later = await snapshot();
  assert.equal(later.time, paused.time);
  assert.equal(later.events, paused.events);
  assert.equal(later.signal, paused.signal);
  await startPlaying();
  await page.waitForFunction((position) => document.querySelector('audio').currentTime > position + 0.2, paused.time);
  const resumed = await snapshot();
  assert.ok(resumed.time > paused.time + 0.2);
  assert.equal(resumed.counts.streams, 1);
  assert.equal(resumed.counts.play, 2);
  assert.ok(resumed.timestamps.every((at) => at <= resumed.time * 1000));
});

test('continuar pelo controle nativo não duplica play() ou a conexão SSE', async () => {
  await startPlaying();
  await page.evaluate(() => document.querySelector('audio').pause());
  await page.waitForFunction(() => !document.querySelector('#start').disabled);
  const paused = await snapshot();
  await page.evaluate(() => document.querySelector('audio').play());
  await page.waitForFunction((position) => document.querySelector('audio').currentTime > position + 0.2, paused.time);
  const resumed = await snapshot();
  assert.ok(resumed.time > paused.time + 0.2);
  assert.equal(resumed.counts.play, 2);
  assert.equal(resumed.counts.streams, 1);
  assert.equal(resumed.bad, false);
});

test('parar antes dos metadados impede o play() tardio', async () => {
  let release;
  const gate = new Promise((resolve) => { release = resolve; });
  await page.route('**/api/audio?*', async (route) => { await gate; await route.continue().catch(() => {}); });
  await page.reload();
  await select(0, false);
  await page.click('#start');
  await page.click('#stop');
  release();
  await page.waitForFunction(() => document.querySelector('audio').readyState >= 1);
  await page.waitForTimeout(150);
  const state = await snapshot();
  assert.equal(state.counts.play, 0);
  assert.equal(state.counts.streams, 0);
  assert.equal(state.paused, true);
  assert.equal(state.message, 'Interrompido');
  assert.equal(state.bad, false);
});

test('parar aguardando ready do SSE invalida a continuação antiga', async () => {
  let release;
  const gate = new Promise((resolve) => { release = resolve; });
  await page.route('**/api/stream?*', async (route) => { await gate; await route.continue().catch(() => {}); });
  await page.click('#start');
  await page.waitForFunction(() => window.calls.streams === 1);
  await page.click('#stop');
  release();
  await page.waitForTimeout(350);
  const state = await snapshot();
  assert.equal(state.counts.play, 0);
  assert.equal(state.message, 'Interrompido');
  assert.equal(state.bad, false);
});

test('parar com a Promise de play pendente não inicia uma análise antiga', async () => {
  await page.evaluate(() => {
    const audio = document.querySelector('audio');
    const original = audio.play.bind(audio);
    audio.play = () => {
      const pending = original();
      document.querySelector('#stop').click();
      return pending;
    };
  });
  await page.click('#start');
  await page.waitForFunction(() => window.calls.play === 1);
  await page.waitForTimeout(300);
  const state = await snapshot();
  assert.equal(state.paused, true);
  assert.equal(state.message, 'Interrompido');
  assert.equal(state.bad, false);
});

test('trocar arquivo durante reprodução cancela a sessão e permite nova análise stereo', async () => {
  await startPlaying();
  const previous = await snapshot();
  await select(1);
  assert.equal((await snapshot()).paused, true);
  await startPlaying();
  await page.waitForTimeout(350);
  const state = await snapshot();
  assert.notEqual(state.src, previous.src);
  assert.ok(state.message.includes(fixtures[1].name));
  assert.equal(state.counts.streams, 2);
  assert.equal(state.bad, false);
  assert.equal(state.paused, false);
});

test('callbacks tardios do SSE antigo não alteram a sessão nova', async () => {
  await startPlaying();
  await select(1);
  await startPlaying();
  const before = await snapshot();
  await page.evaluate(() => {
    for (const [type, data] of [
      ['ready', { duration_ms: 999999, filename: 'sessão antiga', state: { signal: 'antigo' } }],
      ['status', { processed_ms: 0, signal: 'antigo' }],
      ['acoustic', { type: 'speech.started', at_ms: 0, reason: 'antigo' }],
      ['done', { state: { processed_ms: 0, signal: 'antigo' } }],
      ['failure', { error: 'Erro antigo' }],
    ]) window.streams[0].dispatchEvent(new MessageEvent(type, { data: JSON.stringify(data) }));
    window.streams[0].dispatchEvent(new Event('error'));
  });
  const state = await snapshot();
  assert.equal(state.message, before.message);
  assert.equal(state.bad, false);
  assert.equal(state.paused, false);
  assert.notEqual(state.signal, 'antigo');
  assert.ok(!state.events.includes('antigo'));
  assert.deepEqual(state.counts, before.counts);
});

test('atualizar a lista sem trocar o arquivo não reinicializa o player', async () => {
  await startPlaying();
  const before = await snapshot();
  await page.evaluate(() => listFiles());
  const state = await snapshot();
  assert.equal(state.src, before.src);
  assert.equal(state.paused, false);
  assert.deepEqual(state.counts, before.counts);
});

test('uma Promise antiga rejeitada não interrompe o play da sessão nova', async () => {
  await page.evaluate(() => {
    const audio = document.querySelector('audio');
    const original = audio.play.bind(audio);
    let first = true;
    audio.play = () => {
      const actual = original();
      if (!first) return actual;
      first = false;
      actual.catch(() => {});
      return new Promise((resolve, reject) => {
        window.rejectOldPlay = () => reject(new DOMException('Cancelamento antigo', 'AbortError'));
      });
    };
  });
  await startPlaying();
  await page.click('#stop');
  await page.click('#start');
  await page.waitForFunction(() => window.calls.streams === 2);
  await page.waitForTimeout(100);
  assert.equal((await snapshot()).counts.play, 1, 'A Promise anterior deve terminar antes do próximo play');
  await page.evaluate(() => window.rejectOldPlay());
  await page.waitForFunction(() => window.calls.play === 2 && !document.querySelector('audio').paused);
  assert.equal((await snapshot()).bad, false);
});

test('cliques rápidos em iniciar/parar mantêm a última sessão válida', async () => {
  for (let i = 0; i < 6; i++) {
    await page.evaluate(() => document.querySelector('#start').click());
    await page.waitForTimeout(i * 5);
    await page.evaluate(() => document.querySelector('#stop').click());
  }
  await startPlaying();
  await page.waitForTimeout(300);
  const state = await snapshot();
  assert.equal(state.paused, false);
  assert.equal(state.bad, false);
  assert.ok(state.message.startsWith('Analisando:'));
});

test('reiniciar após o término aguarda o seek interno sem interromper play()', async () => {
  await select(2);
  await startPlaying();
  await page.waitForFunction(() => document.querySelector('#connection').textContent === 'Reprodução e análise concluídas');
  await startPlaying();
  assert.equal((await snapshot()).bad, false);
  await page.waitForFunction(() => document.querySelector('#connection').textContent === 'Reprodução e análise concluídas');
  const state = await snapshot();
  assert.equal(state.counts.play, 2);
  assert.equal(state.counts.streams, 2);
});

test('done do SSE durante pausa não conclui nem reinicia o player', async () => {
  await select(2);
  await startPlaying();
  await page.evaluate(() => document.querySelector('audio').pause());
  await page.waitForTimeout(1000);
  const paused = await snapshot();
  assert.ok(paused.message.startsWith('Reprodução pausada'));
  assert.equal(paused.counts.play, 1);
  await startPlaying();
  await page.waitForFunction(() => document.querySelector('#connection').textContent === 'Reprodução e análise concluídas');
  assert.equal((await snapshot()).counts.streams, 1);
});

test('o último quadro arredondado não impede a conclusão após ended', async () => {
  await select(2);
  await startPlaying();
  await page.evaluate(() => {
    window.streams[0].dispatchEvent(new MessageEvent('done', {
      data: JSON.stringify({ state: { processed_ms: 620, signal: 'silence' } }),
    }));
  });
  await page.waitForFunction(() => document.querySelector('#connection').textContent === 'Reprodução e análise concluídas');
  assert.equal((await snapshot()).counts.pause, 0);
  assert.equal(await page.locator('#start').isEnabled(), true);
});

for (const name of ['NotAllowedError', 'NotSupportedError', 'AbortError']) {
  test(`erro real de play (${name}) continua visível`, async () => {
    await page.evaluate((errorName) => {
      document.querySelector('audio').play = () => Promise.reject(new DOMException('Erro real de teste', errorName));
    }, name);
    await page.click('#start');
    await page.waitForFunction(() => document.querySelector('#connection').classList.contains('bad'));
    const state = await snapshot();
    assert.ok(state.message.includes('Erro real de teste'));
    assert.equal(state.paused, true);
  });
}

test('pausa nativa enquanto play está pendente é cancelamento intencional', async () => {
  await page.evaluate(() => {
    const audio = document.querySelector('audio');
    const original = audio.play.bind(audio);
    audio.play = () => {
      const pending = original();
      audio.pause();
      return pending;
    };
  });
  await page.click('#start');
  await page.waitForFunction(() => window.calls.play === 1);
  await page.waitForTimeout(300);
  const state = await snapshot();
  assert.equal(state.bad, false);
  assert.ok(state.message.startsWith('Reprodução pausada'));
});

test('erro de carregamento do áudio é apresentado', async () => {
  await page.route('**/api/audio?*', (route) => route.fulfill({ status: 404, body: 'Audio ausente' }));
  await select(1, false);
  await page.waitForFunction(() => document.querySelector('#connection').classList.contains('bad'));
  assert.ok((await snapshot()).message.startsWith('Falha na reprodução:'));
});

test('falha real do analisador continua visível', async () => {
  await page.route('**/api/stream?*', (route) => route.fulfill({
    contentType: 'text/event-stream', body: 'event: failure\ndata: {"error":"Falha real do analisador"}\n\n',
  }));
  await page.click('#start');
  await page.waitForFunction(() => document.querySelector('#connection').classList.contains('bad'));
  const state = await snapshot();
  assert.ok(state.message.includes('Falha real do analisador'));
  assert.equal(state.paused, true);
});

test('seek externo encerra a sessão; reiniciar reposiciona sem corrida', async () => {
  await startPlaying();
  await page.evaluate(() => { document.querySelector('audio').currentTime = 1; });
  await page.waitForFunction(() => document.querySelector('#connection').textContent.startsWith('Posição alterada'));
  await startPlaying();
  const state = await snapshot();
  assert.ok(state.time < 1);
  assert.equal(state.paused, false);
  assert.equal(state.bad, false);
});

test('reiniciar após mudar a velocidade restaura o relógio de análise normal', async () => {
  await startPlaying();
  await page.evaluate(() => { document.querySelector('audio').playbackRate = 2; });
  await page.waitForFunction(() => document.querySelector('#connection').textContent.startsWith('Velocidade modificada'));
  await startPlaying();
  assert.equal(await page.evaluate(() => document.querySelector('audio').playbackRate), 1);
  assert.equal((await snapshot()).bad, false);
});
