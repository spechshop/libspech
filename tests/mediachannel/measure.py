"""Interleaved process benchmarks. Usage: python3 measure.py PHP BASELINE CANDIDATE OUTPUT."""
import hashlib, json, pathlib, subprocess, sys, statistics
php, baseline, candidate, output = sys.argv[1:]
suite = pathlib.Path(__file__).resolve().parent
out = pathlib.Path(output)
out.mkdir(parents=True, exist_ok=True)
rows = []
with (out / 'bench.jsonl').open('w') as log:
    for repetition in range(5):
        for scenario in ['one', 'same', 'transcode', 'dtmf', 'noacoustic', 'ptime10', 'ptime40']:
            for variant in (['before', 'after'] if repetition % 2 == 0 else ['after', 'before']):
                root = baseline if variant == 'before' else candidate
                result = subprocess.run([php, str(suite / 'bench.php'), root, scenario, '50'], capture_output=True, text=True, timeout=15)
                if result.returncode: raise RuntimeError(result.stdout + result.stderr)
                row = json.loads(result.stdout.splitlines()[-1])
                row.update(variant=variant, repetition=repetition)
                rows.append(row); log.write(json.dumps(row) + '\n'); log.flush()
summary = {}
for scenario in sorted({r['scenario'] for r in rows}):
    summary[scenario] = {}
    for variant in ['before', 'after']:
        values = [r['cpu_us_per_input'] for r in rows if r['scenario'] == scenario and r['variant'] == variant]
        summary[scenario][variant] = dict(values=values, median=statistics.median(values), min=min(values), max=max(values), stdev=statistics.stdev(values))
(out / 'bench-summary.json').write_text(json.dumps(summary, indent=2))
(out / 'runtime.json').write_text(json.dumps(dict(binary=php, sha256=hashlib.sha256(pathlib.Path(php).read_bytes()).hexdigest(), version=subprocess.check_output([php, '-v'], text=True)), indent=2))
