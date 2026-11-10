import asyncio,aiohttp,json,time,datetime
from pathlib import Path
root=Path(__file__).resolve().parents[1];secret=json.loads((root/'runtime/credentials.json').read_text())['load_secret']
async def main():
 start=time.perf_counter();times=[];codes={}
 async with aiohttp.ClientSession(connector=aiohttp.TCPConnector(limit=0),timeout=aiohttp.ClientTimeout(total=180),headers={'X-GC-Test':secret}) as session:
  async def one(index):
   begin=time.perf_counter()
   try:
    async with session.post('http://localhost:8081/?rest_route=/gold-test/v1/run/callback',json={'fixture_index':index}) as r:await r.read();code=str(r.status)
   except Exception as e:code=type(e).__name__
   times.append((time.perf_counter()-begin)*1000);codes[code]=codes.get(code,0)+1
  await asyncio.gather(*(one(i) for i in range(1000)))
 elapsed=time.perf_counter()-start;times.sort();result={'test':'OPS.isolated_1000_prepared_callbacks','status':'PASS' if codes.get('200',0)==1000 else 'FAIL','at_utc':datetime.datetime.now(datetime.timezone.utc).isoformat(),'users':1000,'requests':1000,'p95_ms':round(times[949],2),'throughput_rps':round(1000/elapsed,2),'duration_s':round(elapsed,2),'status_codes':codes,'criterion':'all 1000 prepared callbacks HTTP 200 within unchanged 180-second budget','scope':'Settlement operation isolated from order/quote/start preparation. Other regression work may share CPU and change test-shop authority; received callbacks must remain valid during those changes. No real bank.'}
 (root/'evidence/load-callback-isolated.json').write_text(json.dumps(result,indent=2));print(json.dumps(result,indent=2))
asyncio.run(main())
