import concurrent.futures,json,subprocess,datetime
from pathlib import Path
root=Path(__file__).resolve().parents[1]
def run(op,**values):
    args=['docker','exec','-e','GC_WORKER_OP='+op]
    for key,value in values.items():args+=['-e','GC_'+key.upper()+'='+str(value)]
    args+=['gc-site1','wp','--path=/project/runtime/site1','eval-file','/project/tests/worker.php','--use-include']
    r=subprocess.run(args,capture_output=True,text=True,timeout=60)
    text=r.stdout.strip();start=text.find('{')
    if start<0:raise RuntimeError('Worker failed: '+r.stderr[:200])
    return json.loads(text[start:])
def race(op,inputs):
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:return list(pool.map(lambda values:run(op,**values),inputs))
setup=run('setup');issues=race('issue',[{'order_id':i} for i in setup['orders']]);winners=[r for r in issues if r.get('ok')]
assert len(winners)==1,issues
q=winners[0];begins=race('begin',[{'quote_uuid':q['uuid'],'quote_token':q['token']}]*2);intents=[r for r in begins if r.get('ok')]
assert len(intents)==1,begins
intent=intents[0]['intent'];settles=race('settle',[{'order_id':q['order_id'],'intent':intent}]*2)
assert sorted(r.get('status') for r in settles)==['duplicate','paid'],settles
state=run('check',order_id=q['order_id'],product_id=setup['product']);assert state['paid'] and state['stock']==0 and state['quote_status']=='paid',state
out={'at_utc':datetime.datetime.now(datetime.timezone.utc).isoformat(),'workers':2,'tests':[{'id':'T23.concurrent_last_unit','status':'PASS','successful_issues':len(winners)},{'id':'T15.concurrent_payment_start','status':'PASS','successful_intents':len(intents)},{'id':'T18.concurrent_callback','status':'PASS','results':[r['status'] for r in settles],'final_stock':state['stock']} ]}
(root/'evidence/concurrency.json').write_text(json.dumps(out,indent=2));print(json.dumps(out,indent=2))
