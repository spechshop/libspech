"""Counters are process-wide, pinned to one permitted CPU. Never a CI gate."""
import json, os, pathlib, subprocess, sys
php, before, after, output = sys.argv[1:]
suite = pathlib.Path(__file__).resolve().parent
out = pathlib.Path(output); out.mkdir(exist_ok=True, parents=True)
rows = []
for rep in range(3):
    for scenario in ['same', 'dtmf', 'ptime40', 'idle']:
        for variant in (['before', 'after'] if rep % 2 == 0 else ['after', 'before']):
            root = before if variant == 'before' else after
            csv = out / f'{scenario}-{variant}-{rep}.csv'
            args = [str(suite / 'idle.php'), root] if scenario == 'idle' else [str(suite / 'bench.php'), root, scenario, '50']
            command = ['perf', 'stat', '-x,', '-e', 'task-clock,context-switches,cycles,instructions', '-o', str(csv), 'taskset', '-c', str(min(os.sched_getaffinity(0))), php, *args]
            result = subprocess.run(command, capture_output=True, text=True, timeout=15)
            rows.append(dict(command=command, status=result.returncode, stdout=result.stdout, stderr=result.stderr))
(out / 'runs.json').write_text(json.dumps(rows, indent=2))
if any(r['status'] for r in rows): sys.exit(1)
