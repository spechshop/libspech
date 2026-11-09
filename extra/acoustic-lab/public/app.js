'use strict';

const $ = (id) => document.getElementById(id);
const fileList = $('fileList');
const player = $('player');
const timeline = $('timeline');
let session = null;
let selectedId = null;
let filesRequest = 0;
let pendingPlay = null;
let duration = 0;
let latest = 0;
let blocks = [];
let eventCount = 0;

function formatMs(ms) {
  ms = Math.max(0, Math.floor(Number(ms) || 0));
  const minutes = String(Math.floor(ms / 60000)).padStart(2, '0');
  const seconds = String(Math.floor(ms / 1000) % 60).padStart(2, '0');
  return `${minutes}:${seconds}.${String(ms % 1000).padStart(3, '0')}`;
}

function message(text, bad = false) {
  $('connection').textContent = text;
  $('connection').classList.toggle('bad', bad);
}

function updateControls() {
  $('start').disabled = !selectedId || Boolean(session && session.phase !== 'paused');
  $('start').textContent = session?.phase === 'paused' ? 'Continuar análise' : 'Iniciar análise';
  $('stop').disabled = !session;
}

async function listFiles(prefer) {
  const request = ++filesRequest;
  const response = await fetch('/api/files');
  if (!response.ok) throw new Error('Falha ao listar os áudios');
  const data = await response.json();
  if (request !== filesRequest) return;
  const previous = prefer || fileList.value;
  fileList.replaceChildren();
  for (const file of data.files) {
    const option = new Option(file.name, file.id);
    fileList.add(option);
  }
  if ([...fileList.options].some((option) => option.value === previous)) fileList.value = previous;
  selectAudio();
  if (!fileList.options.length) message('Adicione um WAV para começar');
}

function selectAudio() {
  const id = fileList.value;
  if (id === selectedId) return;
  stop(id ? 'Áudio selecionado' : 'Adicione um WAV para começar');
  selectedId = id;
  // Atribuir src já inicia o carregamento. Não recarregar em eventos acústicos.
  if (id) player.src = `/api/audio?id=${encodeURIComponent(id)}`;
  else {
    player.removeAttribute('src');
    player.load();
  }
  resetAnalysis();
  updateControls();
}

function draw() {
  const dpr = window.devicePixelRatio || 1;
  const width = Math.max(1, timeline.clientWidth);
  const height = 84;
  timeline.width = Math.round(width * dpr);
  timeline.height = Math.round(height * dpr);
  const ctx = timeline.getContext('2d');
  ctx.scale(dpr, dpr);
  ctx.clearRect(0, 0, width, height);
  ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--canvasbg') || '#151c27';
  ctx.fillRect(0, 0, width, height);
  if (!duration) return;
  const color = { silence: '#344458', narrowband_tone: '#efc773', voice_like: '#5ed8aa', music_like: '#9187f5', noise: '#91a0af', other_audio: '#91a0af' };
  for (const block of blocks) {
    const x = block.start / duration * width;
    const w = Math.max(1, (block.end - block.start) / duration * width);
    ctx.fillStyle = color[block.signal] || '#91a0af';
    ctx.fillRect(x, 8, w, 48);
    if (block.ring) {
      ctx.fillStyle = '#fa7878';
      ctx.fillRect(x, 59, w, 8);
    }
  }
  const position = player.currentTime * 1000;
  ctx.strokeStyle = '#e5eef7';
  ctx.beginPath();
  ctx.moveTo(Math.min(width, position / duration * width), 0);
  ctx.lineTo(Math.min(width, position / duration * width), height);
  ctx.stroke();
}

function stateChanged(state) {
  if (!state) return;
  const previous = latest;
  latest = Number(state.processed_ms) || 0;
  if (latest > previous) {
    blocks.push({ start: previous, end: latest, signal: state.signal || 'silence', ring: Boolean(state.ring_active) });
  }
  $('signal').textContent = state.signal || 'silence';
  $('signal').dataset.signal = state.signal || 'silence';
  $('ring').textContent = state.ring_active ? 'Tom detectado' : 'Inativo';
  $('speech').textContent = state.speech_active ? 'Voz provável' : 'Inativa';
  $('cadence').textContent = state.cadence_detected ? 'Cadência confirmada' : 'Cadência não confirmada';
  $('interrupt').textContent = state.ring_interrupted ? 'Ring interrompido' : 'Sem interrupção';
  $('frequency').textContent = state.ring_frequency_hz ? `${Number(state.ring_frequency_hz).toFixed(1)} Hz` : '—';
  $('level').textContent = `${Number(state.ring_level_dbfs ?? -120).toFixed(1)} dBFS`;
  draw();
}

function registerEvent(data) {
  eventCount++;
  $('eventCount').textContent = `${eventCount} eventos`;
  $('signalReason').textContent = data.reason || '—';
  const tr = document.createElement('tr');
  const values = [formatMs(data.at_ms), data.type, data.signal ?? '—', data.reason ?? '—', data.period_ms ? `${data.period_ms} ms` : '—'];
  for (const value of values) {
    const td = document.createElement('td');
    td.textContent = value;
    tr.append(td);
  }
  if (data.type.startsWith('ring.')) tr.className = 'ring-event';
  else if (data.type.startsWith('speech.')) tr.className = 'speech-event';
  $('events').prepend(tr);
}

function resetAnalysis() {
  duration = latest = eventCount = 0;
  blocks = [];
  $('events').replaceChildren();
  $('eventCount').textContent = '0 eventos';
  $('signalReason').textContent = 'Aguardando primeira evidência';
  stateChanged({});
  renderPosition();
}

function renderPosition() {
  const position = player.currentTime * 1000;
  $('time').textContent = `${formatMs(position)} / ${formatMs(duration)}`;
  $('progress').style.width = `${duration ? Math.min(100, position / duration * 100) : 0}%`;
  draw();
}

function stop(text = 'Interrompido', bad = false) {
  const previous = session;
  // Invalidar antes de pause(): eventos e Promises antigos não pertencem à próxima sessão.
  session = null;
  previous?.controller.abort();
  previous?.source?.close();
  clearInterval(previous?.timer);
  if (!player.paused) player.pause();
  updateControls();
  message(text, bad);
}

function waitForMedia(type, ready, run) {
  return new Promise((resolve, reject) => {
    const signal = run.controller.signal;
    function cleanup() {
      player.removeEventListener(type, check);
      player.removeEventListener('error', failed);
      signal.removeEventListener('abort', cancelled);
    }
    function check() {
      if (!ready()) return;
      cleanup();
      resolve();
    }
    function failed() {
      cleanup();
      reject(new Error(player.error?.message || 'Não foi possível carregar o áudio'));
    }
    function cancelled() {
      cleanup();
      reject(new DOMException('Sessão cancelada', 'AbortError'));
    }
    player.addEventListener(type, check);
    player.addEventListener('error', failed);
    signal.addEventListener('abort', cancelled, { once: true });
    if (signal.aborted) cancelled();
    else if (player.error) failed();
    else check();
  });
}

function syncAnalysis(run) {
  if (session !== run) return;
  // Ao terminar, consumir também o último quadro parcial e arredondamentos de ms.
  const position = player.ended ? Infinity : player.currentTime * 1000;
  // O SSE permanece incremental. Durante pausas, guardar os dados e só exibi-los
  // quando o relógio do áudio alcançar o timestamp correspondente.
  let consumed = 0;
  while (consumed < run.queue.length && run.queue[consumed].at <= position) {
    const item = run.queue[consumed++];
    if (item.type === 'acoustic') registerEvent(item.data);
    else stateChanged(item.data);
  }
  run.queue.splice(0, consumed);
  renderPosition();
  if (player.ended && run.analysisDone && !run.queue.length) {
    run.controller.abort();
    clearInterval(run.timer);
    session = null;
    updateControls();
    message('Reprodução e análise concluídas');
  }
}

function openAnalysis(run) {
  return new Promise((resolve, reject) => {
    const stream = new EventSource(`/api/stream?id=${encodeURIComponent(run.id)}`);
    run.source = stream;
    let ready = false;
    const fail = (error) => {
      if (session !== run) return;
      stream.close();
      if (!ready) reject(error);
      else stop(`Falha na análise: ${error.message}`, true);
    };
    run.controller.signal.addEventListener('abort', () => {
      stream.close();
      reject(new DOMException('Sessão cancelada', 'AbortError'));
    }, { once: true });
    const listen = (type, handler) => stream.addEventListener(type, (ev) => {
      if (session !== run || run.source !== stream) return;
      try {
        handler(JSON.parse(ev.data));
      } catch (error) {
        fail(error);
      }
    });
    const enqueue = (type, data) => {
      run.queue.push({ type, data, at: Number(type === 'acoustic' ? data.at_ms : data.processed_ms) || 0 });
      syncAnalysis(run);
    };
    listen('ready', (data) => {
      if (ready) return;
      ready = true;
      duration = Number(data.duration_ms) || 0;
      run.filename = data.filename;
      stateChanged(data.state);
      resolve();
    });
    listen('status', (data) => enqueue('status', data));
    listen('acoustic', (data) => enqueue('acoustic', data));
    listen('done', (data) => {
      stream.close();
      run.source = null;
      run.analysisDone = true;
      enqueue('status', data.state);
    });
    listen('failure', (data) => fail(new Error(data.error)));
    stream.onerror = () => {
      if (session === run && run.source === stream) fail(new Error('Conexão com o analisador encerrada inesperadamente'));
    };
  });
}

async function play(run) {
  const request = ++run.playRequest;
  run.phase = 'starting';
  updateControls();
  message('Iniciando reprodução e análise...');
  try {
    // Uma retomada rápida deve esperar a Promise anterior terminar de cancelar.
    if (pendingPlay) await pendingPlay.catch(() => {});
    if (session !== run || request !== run.playRequest) return;
    const pending = Promise.resolve(player.play());
    pendingPlay = pending;
    try {
      await pending;
    } finally {
      if (pendingPlay === pending) pendingPlay = null;
    }
    if (session !== run || request !== run.playRequest) return;
    if (!player.paused) {
      run.phase = 'playing';
      updateControls();
      message(`Analisando: ${run.filename}`);
    }
  } catch (error) {
    // Só cancelamentos identificados pela sessão/solicitação são intencionais.
    // Um AbortError da solicitação atual também é um erro real e deve aparecer.
    if (session === run && request === run.playRequest) stop(`Falha na reprodução: ${error.message}`, true);
  }
}

async function start() {
  if (session?.phase === 'paused') return play(session);
  if (!selectedId || session) return;
  const run = {
    id: selectedId, controller: new AbortController(), phase: 'preparing',
    source: null, queue: [], analysisDone: false, playRequest: 0,
    rewinding: false, timer: null,
  };
  session = run;
  resetAnalysis();
  updateControls();
  message('Preparando reprodução e análise...');
  try {
    if (!player.paused) player.pause();
    if (player.playbackRate !== 1) player.playbackRate = 1;
    await waitForMedia('loadedmetadata', () => player.readyState >= 1, run);
    if (session !== run) return;
    // Só reposicionar quando necessário e aguardar o seek interno antes de play().
    run.rewinding = true;
    if (player.currentTime !== 0) player.currentTime = 0;
    await waitForMedia('seeked', () => !player.seeking, run);
    run.rewinding = false;
    if (session !== run) return;
    await openAnalysis(run);
    if (session !== run) return;
    run.timer = setInterval(() => syncAnalysis(run), 100);
    await play(run);
  } catch (error) {
    if (session === run) stop(`Falha na reprodução/análise: ${error.message}`, true);
  }
}

player.addEventListener('pause', () => {
  const run = session;
  // Ignorar eventos enfileirados de uma pausa antiga e a pausa interna de preparação.
  if (!run || !player.paused || player.ended || run.phase === 'preparing') return;
  ++run.playRequest;
  run.phase = 'paused';
  syncAnalysis(run);
  updateControls();
  message('Reprodução pausada; continue pelo player ou por Continuar análise');
});
player.addEventListener('play', () => {
  const run = session;
  if (!run || player.paused || run.phase !== 'paused') return;
  ++run.playRequest;
  run.phase = 'starting';
  updateControls();
});
player.addEventListener('playing', () => {
  const run = session;
  if (!run || player.paused || run.phase === 'preparing') return;
  run.phase = 'playing';
  updateControls();
  message(`Analisando: ${run.filename}`);
});
player.addEventListener('seeking', () => {
  if (session && !session.rewinding && player.seeking) stop('Posição alterada; reinicie a análise desde o início');
});
player.addEventListener('ratechange', () => {
  if (session && player.playbackRate !== 1) stop('Velocidade modificada; análise interrompida');
});
player.addEventListener('error', () => {
  if (selectedId && player.error) stop(`Falha na reprodução: ${player.error.message || 'Não foi possível carregar o áudio'}`, true);
});
player.addEventListener('timeupdate', () => {
  if (session) syncAnalysis(session);
  else renderPosition();
});
player.addEventListener('ended', () => {
  if (session) syncAnalysis(session);
});
$('start').addEventListener('click', start);
$('stop').addEventListener('click', () => stop());
fileList.addEventListener('change', selectAudio);
$('upload').addEventListener('change', async (ev) => {
  const file = ev.target.files?.[0];
  if (!file) return;
  message('Enviando arquivo...');
  try {
    const form = new FormData();
    form.append('audio', file);
    const response = await fetch('/api/upload', {method: 'POST', body: form});
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'Falha no upload');
    await listFiles(result.id);
    message('Arquivo carregado');
  } catch (error) {
    message(error.message, true);
  }
  ev.target.value = '';
});
window.addEventListener('resize', draw);
updateControls();
listFiles().catch((error) => message(error.message, true));
