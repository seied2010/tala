import subprocess,json,datetime
from pathlib import Path
root=Path(__file__).resolve().parents[1]
def wp(*args):
 r=subprocess.run(['docker','exec','gc-site2','wp','--path=/project/runtime/site2',*args],capture_output=True,text=True)
 if r.returncode:raise RuntimeError((r.stdout+r.stderr)[-600:])
 return r.stdout
def probe(code):
 text=wp('eval',code);return json.loads(text[text.find('{'):])
snapshot=probe('global $wpdb;echo wp_json_encode(["quotes"=>(int)$wpdb->get_var("SELECT COUNT(*) FROM ".$wpdb->prefix."gc_quotes"),"receipts"=>(int)$wpdb->get_var("SELECT COUNT(*) FROM ".$wpdb->prefix."gc_payment_receipts")]);')
results=[]
try:
 wp('eval','update_option("gc_mode","agah_connected");')
 wp('plugin','deactivate','gold-agah-connector')
 state=probe('echo wp_json_encode(["mode"=>GoldCommerce\\Plugin::mode(),"stored"=>get_option("gc_mode")]);'.replace('\\\\','\\'))
 assert state['mode']=='agah_degraded',state
 results.append({'test':'I12.actual_connector_deactivate','status':'PASS','mode':state['mode']})
 wp('plugin','deactivate','gold-commerce-core')
 probe_code='''$p=wc_get_product(18);$orders=wc_get_orders(["limit"=>1,"status"=>"pending"]);echo wp_json_encode(["core_loaded"=>class_exists("GoldCommerce\\\\Plugin"),"purchasable"=>$p->is_purchasable(),"guard"=>is_file(WP_CONTENT_DIR."/mu-plugins/gold-commerce-guard.php")]);'''
 state=probe(probe_code);assert state['core_loaded'] is False and state['purchasable'] is False and state['guard'],state
 results.append({'test':'T35.actual_core_deactivate_guard','status':'PASS','purchasable':False,'data_preserved':True})
finally:
 wp('plugin','activate','gold-commerce-core','gold-agah-connector')
 wp('eval','update_option("gc_mode","standalone");')
after=probe('global $wpdb;echo wp_json_encode(["quotes"=>(int)$wpdb->get_var("SELECT COUNT(*) FROM ".$wpdb->prefix."gc_quotes"),"receipts"=>(int)$wpdb->get_var("SELECT COUNT(*) FROM ".$wpdb->prefix."gc_payment_receipts")]);')
assert snapshot==after,(snapshot,after)
(root/'evidence/lifecycle.json').write_text(json.dumps({'at_utc':datetime.datetime.now(datetime.timezone.utc).isoformat(),'counts_before':snapshot,'counts_after':after,'results':results},indent=2));print(json.dumps(results))
