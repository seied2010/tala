"""Local synthetic burst test, not a production capacity guarantee."""
import asyncio,aiohttp,json,time,datetime,statistics,subprocess,os
from pathlib import Path
root=Path(__file__).resolve().parents[1]
secret=json.loads((root/'runtime/credentials.json').read_text())['load_secret']
base='http://localhost:8081/?rest_route=/gold-test/v1/run/'
async def wave(users,operation):
    durations=[];codes={};start=time.perf_counter()
    timeout=aiohttp.ClientTimeout(total=180)
    async with aiohttp.ClientSession(connector=aiohttp.TCPConnector(limit=0),timeout=timeout,headers={'X-GC-Test':secret}) as session:
        async def one():
            begin=time.perf_counter()
            try:
                async with session.post(base+operation) as r:
                    await r.read();code=str(r.status)
            except Exception as e:code=type(e).__name__
            durations.append((time.perf_counter()-begin)*1000);codes[code]=codes.get(code,0)+1
        await asyncio.gather(*(one() for _ in range(users)))
    elapsed=time.perf_counter()-start;durations.sort();stats=subprocess.run(['docker','stats','--no-stream','--format','{{.Name}} {{.CPUPerc}} {{.MemUsage}}','gc-site1','gc-db'],capture_output=True,text=True).stdout.strip()
    # Burst latency is explicitly measured without an arrival-rate model or sustained soak.
    result={'users':users,'operation':operation,'requests':users,'duration_s':round(elapsed,3),'throughput_rps':round(users/elapsed,2),'p95_ms':round(durations[max(0,int(len(durations)*.95)-1)],2),'status_codes':codes,'error_rate':round(1-codes.get('200',0)/users,4),'resources_end':stats,'criterion':'No unexpected HTTP failures; synthetic burst P95 <= 180s. Not a commercial SLA.'}
    result['status']='PASS' if result['error_rate']==0 and result['p95_ms']<=180000 else 'FAIL'
    return result
async def main():
    results=[]
    for users in [50,100,300,1000]:
        for operation in ['browse','product','search','request','issue','checkout','callback','queue']:
            result=await wave(users,operation);results.append(result);print(json.dumps(result),flush=True)
            (root/'evidence/load.json').write_text(json.dumps({'at_utc':datetime.datetime.now(datetime.timezone.utc).isoformat(),'environment':{'cpu_quota_cores':2,'memory_limit_bytes':8589934592,'php_workers':8,'server':'PHP built-in, 8 workers; MariaDB 11; no page cache; local networking','dataset':'Shared hidden finite-stock product, synthetic orders, virtual delivery; authenticated test-only adapter; no real payment or rate API. Security/public anti-abuse is tested separately.'},'results':results},indent=2))
asyncio.run(main())
