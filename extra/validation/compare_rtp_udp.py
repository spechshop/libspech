#!/usr/bin/env python3
"""Compare 06_trunk_rtp_udp JSON runs with independently captured RTP packets.

Usage: python3 extra/validation/compare_rtp_udp.py before.json after.json capture.pcap output-dir
Only packets whose SHA-256 was verified by the benchmark receiver are retained.
The capture may contain both runs; unrelated traffic is excluded from artifacts.
"""

import hashlib
import json
import math
from pathlib import Path
import struct
import sys


def stats(values):
    values = sorted(values)
    n = len(values)
    return dict(mean=sum(values) / n, median=values[math.floor((n - 1) * .5)],
                p95=values[math.ceil(n * .95) - 1], p99=values[math.ceil(n * .99) - 1],
                max=values[-1], min=values[0])


def read_capture(path):
    if path.is_dir():
        combined = {}
        for name in ('before-wire.pcap', 'after-wire.pcap'):
            header, records = read_capture(path / name)
            assert not combined.keys() & records.keys(), 'Duplicate RTP across captures'
            combined.update(records)
        return header, combined
    data = path.read_bytes()
    magic = data[:4]
    endian = '<' if magic in (b'\xd4\xc3\xb2\xa1', b'\x4d\x3c\xb2\xa1') else '>'
    scale = 1e9 if magic in (b'\x4d\x3c\xb2\xa1', b'\xa1\xb2\x3c\x4d') else 1e6
    assert struct.unpack(endian + 'I', data[20:24])[0] == 1, 'Ethernet PCAP required'
    records = {}
    pos = 24
    while pos + 16 <= len(data):
        sec, fraction, size, _ = struct.unpack(endian + 'IIII', data[pos:pos + 16])
        end = pos + 16 + size
        if end > len(data):
            break  # A live capture may be appending its last record.
        frame = data[pos + 16:end]
        if frame[12:14] == b'\x08\x00' and len(frame) >= 42 and frame[23] == 17:
            udp = 14 + (frame[14] & 15) * 4
            length = int.from_bytes(frame[udp + 4:udp + 6], 'big')
            payload = frame[udp + 8:udp + length]
            if len(payload) >= 12 and payload[0] >> 6 == 2:
                digest = hashlib.sha256(payload).hexdigest()
                # A shared capture can contain repeated unrelated fixtures.
                # Benchmark sequence/count/hash checks independently reject
                # duplicates in the media being measured.
                records.setdefault(digest, (sec + fraction / scale, data[pos:end]))
        pos = end
    return data[:24], records


def main():
    before, after, capture, output = map(Path, sys.argv[1:])
    output.mkdir(exist_ok=True, parents=True)
    header, captured = read_capture(capture)
    result = {'definitions': {
        'send_clock': 'hrtime at entry to real SocketMutable::sendto',
        'receiver_clock': 'hrtime after recvfrom in a separate OS process',
        'wire_clock': 'tcpdump/libpcap kernel timestamps on Linux loopback, microsecond resolution',
        'late_intervals_over_1ms': 'inter-packet send gap > ptime + 1 ms; inferred missed relative deadlines, not an internal pacer counter',
        'missed_full_slots': 'max(0, floor((gap_ms - 1) / ptime_ms) - 1), summed over gaps; never a discarded media frame',
        'cpu': 'sender process getrusage user+system / scenario wall time, percent of one core',
        'memory': 'PHP peak / maximum RSS accumulated up to each scenario; includes harness and caches',
        'integrity': 'packet count, SHA-256 at receiver, sequence and timestamp continuity; audio encoder sequence additionally checked by 08_audio_preparation.php',
    }, 'before': [], 'after': []}
    for label, file in [('before', before), ('after', after)]:
        report = json.loads(file.read_text())
        wire_records = []
        for item in report['results']:
            intervals = []
            for packets in item['raw_receive_records'].values():
                times = []
                for _, _, _, digest in packets:
                    assert digest in captured, f'Missing wire packet: {label} {digest}'
                    timestamp, raw = captured[digest]
                    times.append(timestamp)
                    wire_records.append((timestamp, raw))
                intervals.extend((b - a) * 1000 for a, b in zip(times, times[1:]))
            row = {k: v for k, v in item.items() if not k.startswith('raw_')}
            row['wire_intervals_ms'] = stats(intervals)
            result[label].append(row)
        # Keep review artifacts free of other traffic in the shared capture.
        (output / f'{label}-wire.pcap').write_bytes(header + b''.join(raw for _, raw in sorted(wire_records)))
    (output / 'comparison.json').write_text(json.dumps(result, indent=2) + '\n')
    lines = ['Comparativo RTP local — tempos em ms; CPU em % de um núcleo.',
             'Cada linha corresponde ao mesmo cenário antes/depois; valores sem arredondamento em comparison.json.',
             'Wire = captura no loopback Linux; não equivale a uma medição de Internet/WSL/provedor SIP.', '']
    for index, (a, b) in enumerate(zip(result['before'], result['after'])):
        fields = ('codec', 'ptime_ms', 'delay_ms', 'calls', 'sharing', 'file_seconds', 'packets')
        assert all(a[key] == b[key] for key in fields), 'Scenario parameters differ'
        lines.append(f"{index + 1:02}: {a['codec']}/{a['ptime_ms']}ms, atraso={a['delay_ms']}ms, chamadas={a['calls']}, arquivo={a['file_seconds']}s, compartilhamento={a['sharing']}, pacotes={a['packets']}")
        for metric in ('send_intervals_ms', 'receiver_intervals_ms', 'wire_intervals_ms', 'callback_ms'):
            lines.append(metric + ' (antes -> depois): ' + ', '.join(f'{k} {a[metric][k]:.3f} -> {b[metric][k]:.3f}' for k in ('mean', 'median', 'p95', 'p99', 'min', 'max')))
        for metric in ('late_intervals_over_1ms', 'missed_full_slots', 'cpu_one_core_percent', 'memory_peak_bytes', 'max_rss_kib', 'incorrect_or_lost'):
            lines.append(f"{metric}: {a[metric]:.3f} -> {b[metric]:.3f}")
        lines.append('')
    (output / 'comparison.txt').write_text('\n'.join(lines))
    print(f"Compared {len(result['before'])} scenarios, {sum(r['packets'] for r in result['before'])} verified UDP/RTP packets per run, complete wire captures.")


if __name__ == '__main__':
    main()
