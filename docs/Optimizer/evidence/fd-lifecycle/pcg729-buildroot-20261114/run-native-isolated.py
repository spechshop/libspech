"""One process per case; ephemeral self-signed localhost certificate, no external services."""
import tempfile, subprocess, pathlib, json, sys
out = pathlib.Path(__file__).resolve().parent
php = '/home/lotus/CLionProjects/pcg729/buildroot/bin/php'
rows = []
with tempfile.TemporaryDirectory(prefix='libspech-tls-') as temp:
    cert, key = temp+'/cert.pem', temp+'/key.pem'
    subprocess.run(['openssl','req','-x509','-newkey','rsa:2048','-nodes','-keyout',key,'-out',cert,'-days','1','-subj','/CN=localhost','-addext','subjectAltName=DNS:localhost'],check=True,capture_output=True)
    for case in ['tls-idle','tls-blocked','import-destruct','import-close','import-stream-first']:
        p = subprocess.run([php,str(out/'native-isolated.php'),case,cert,key],capture_output=True,text=True,timeout=15)
        rows.append(dict(case=case,status=p.returncode,stdout=p.stdout,stderr=p.stderr))
        print(case,p.returncode,p.stdout,p.stderr)
(out/'native-isolated.json').write_text(json.dumps(rows,indent=2))
sys.exit(0 if all(r['status']==0 and json.loads(r['stdout'])['passed'] for r in rows) else 1)
