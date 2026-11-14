import subprocess,json,pathlib,statistics,sys
root=str(pathlib.Path(sys.argv[2]).resolve()); base=str(pathlib.Path(sys.argv[1]).resolve()); suite=root+'/tests/mediachannel/'
rows=[]
for rep in range(7):
 for label,path in ([('before',base),('after',root)] if rep%2==0 else [('after',root),('before',base)]):
  for kind in ['audio','event']:
   p=subprocess.run(['php',suite+'acoustic-dtmf-cost.php',path,kind,'20000','baseline' if label=='before' else 'fixed'],cwd=path,capture_output=True,text=True,check=True)
   rows.append(dict(rep=rep,label=label,scope='tap+DTMF handler',**json.loads(p.stdout)))
  for kind in ['acoustic','dtmf']:
   p=subprocess.run(['php',suite+'bench.php',path,kind,'60'],cwd=path,capture_output=True,text=True,check=True)
   rows.append(dict(rep=rep,label=label,scope='MediaChannel paced 50pps',**json.loads(p.stdout)))
pathlib.Path(root+'/docs/Optimizer/evidence/dtmf-continuity/performance.json').write_text(json.dumps(rows,indent=2))
for scope,kind in [('tap+DTMF handler','audio'),('tap+DTMF handler','event'),('MediaChannel paced 50pps','acoustic'),('MediaChannel paced 50pps','dtmf')]:
 for label in ['before','after']:
  rs=[r for r in rows if r['scope']==scope and r['label']==label and r.get('kind',r.get('scenario'))==kind]
  xs=[r.get('cpu_us_per_rtp',r.get('cpu_us_per_input')) for r in rs]
  print(scope,kind,label,'median/min/max/stdev',statistics.median(xs),min(xs),max(xs),statistics.stdev(xs))

# Hardware counters include interpreter startup; pin both perf and PHP to a core.
# Select a core PMU supported on the benchmark host.
rows=[]
for rep in range(5):
 for label,path in [('before',base),('after',root)]:
  for kind in ['audio','event']:
   result=subprocess.run(['taskset','-c','2','perf','stat','-x,','-e','cpu_core/instructions/','php',root+'/tests/mediachannel/acoustic-dtmf-cost.php',path,kind,'20000','baseline' if label=='before' else 'fixed'],cwd=path,text=True,capture_output=True,check=True)
   rows.append(dict(rep=rep,label=label,kind=kind,perf=result.stderr,result=json.loads(result.stdout)))
pathlib.Path(root+'/docs/Optimizer/evidence/dtmf-continuity/instructions.json').write_text(json.dumps(rows,indent=2))
for label in ['before','after']:
 for kind in ['audio','event']:
  ns=[sum(float(line.split(',')[0]) for line in r['perf'].splitlines() if '/instructions/' in line and line.split(',')[0].isdigit())/r['result']['rtp_packets'] for r in rows if r['label']==label and r['kind']==kind]
  print(label,kind,ns)
