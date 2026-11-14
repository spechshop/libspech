"""Bounded lifecycle measurement. Preserve failed certification runs as failures."""
import json, pathlib, subprocess, sys
php, root, destination = sys.argv[1:4]
repeats = int(sys.argv[4]) if len(sys.argv) > 4 else 5
suite = pathlib.Path(__file__).resolve().parent
root = pathlib.Path(root).resolve()
out = pathlib.Path(destination).resolve()
out.mkdir(parents=True, exist_ok=True)
rows = []
for mode in ['traditional', 'native']:
    for repeat in range(repeats):
        prefix = out / f'{mode}-{repeat}'
        command = [php, str(suite / 'fd-lifecycle.php'), str(root), mode, str(prefix.with_suffix('.json'))]
        proc = subprocess.run(['perf', 'stat', '-x', ';', '-e', 'task-clock,context-switches',
                               '-o', str(prefix.with_suffix('.perf')), *command], cwd=root,
                              stdout=subprocess.PIPE, stderr=subprocess.STDOUT, timeout=30, text=True)
        prefix.with_suffix('.log').write_text(proc.stdout)
        rows.append(dict(mode=mode, repeat=repeat, command=command, status=proc.returncode))
(out / 'runs.json').write_text(json.dumps(rows, indent=2))
