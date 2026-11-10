import subprocess,time,json,datetime,os
from pathlib import Path
root=Path(__file__).resolve().parents[1]
with (root/'evidence/load-resources.jsonl').open('w') as file:
 for _ in range(240):
  r=subprocess.run(['docker','stats','--no-stream','--format','{{.Name}} {{.CPUPerc}} {{.MemUsage}} {{.PIDs}}','gc-site1','gc-db'],capture_output=True,text=True)
  record={'at_utc':datetime.datetime.now(datetime.timezone.utc).isoformat(),'samples':r.stdout.strip().splitlines()};file.write(json.dumps(record)+'\n');file.flush()
  try:
   if len(json.loads((root/'evidence/load.json').read_text())['results'])>=32:break
  except (OSError,ValueError):pass
  time.sleep(10)
