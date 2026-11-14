"""Functional checks: nonzero exit or SKIP is a failure; each result is retained."""
import json, pathlib, subprocess, sys
php, root, destination = sys.argv[1:]
root = pathlib.Path(root).resolve()
suite = pathlib.Path(__file__).resolve().parent
commands = [[str(suite / 'check.php'), str(root), mode] for mode in ['dtmf', 'idle', 'invalid', 'reentrant']]
commands += [[str(suite / name), str(root)] for name in ['ptime-frame.php', 'edges.php', 'flows.php', 'codec-matrix.php']]
commands += [[str(root / name)] for name in ['test_rtpc_native.php', 'test_pcm_stream_converter.php', 'test_audio_file_playback_resample.php', 'test_media_channel_member_ptime.php', 'test_trunk_recording.php']]
rows = []
for args in commands:
    result = subprocess.run([php, *args], cwd=root, capture_output=True, text=True, timeout=60)
    passed = result.returncode == 0 and 'SKIP' not in result.stdout + result.stderr
    rows.append(dict(args=args, status=result.returncode, passed=passed, stdout=result.stdout, stderr=result.stderr))
    print(('PASS ' if passed else 'FAIL ') + ' '.join(args), flush=True)
pathlib.Path(destination).write_text(json.dumps(rows, indent=2))
sys.exit(0 if all(r['passed'] for r in rows) else 1)
