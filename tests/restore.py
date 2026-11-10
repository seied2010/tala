import subprocess,json,os,time,datetime,shutil,hashlib,zipfile
from pathlib import Path
root=Path(__file__).resolve().parents[1];runtime=root/'runtime';creds=json.loads((runtime/'credentials.json').read_text());env=dict(os.environ,MYSQL_PWD=creds['db_password'])
def sql(query):
 r=subprocess.run(['docker','exec','-e','MYSQL_PWD','gc-db','mariadb','-uroot','-N','-e',query],env=env,capture_output=True,text=True,check=True);return r.stdout.strip()
def wp(*args):return subprocess.run(['docker','exec','gc-site2','wp','--path=/project/runtime/site2',*args],capture_output=True,text=True,check=True).stdout
tables=['wp_gc_quotes','wp_gc_payment_receipts','wp_wc_orders','wp_wc_order_addresses','wp_gc_integration_outbox','wp_gc_external_mapping']
def counts(db):return {table:int(sql('SELECT COUNT(*) FROM '+db+'.'+table)) for table in tables}
before=counts('gold2');started=time.perf_counter();wp('db','export','/project/runtime/restore-drill.sql','--single-transaction','--quick');sql('CREATE DATABASE IF NOT EXISTS gold_restore;')
with (runtime/'restore-drill.sql').open('rb') as file:
 p=subprocess.run(['docker','exec','-i','-e','MYSQL_PWD','gc-db','mariadb','-uroot','gold_restore'],env=env,stdin=file,capture_output=True,check=True)
after=counts('gold_restore');assert before==after,(before,after)
def snapshot_hash(db):return hashlib.sha256(sql("SELECT SHA2(CONCAT(uuid,snapshot,status,amount_irr),256) FROM "+db+".wp_gc_quotes ORDER BY id").encode()).hexdigest()
assert snapshot_hash('gold2')==snapshot_hash('gold_restore')
files=[p for p in (root/'source').rglob('*') if p.is_file()];archive=runtime/'restore-files.zip'
with zipfile.ZipFile(archive,'w',zipfile.ZIP_DEFLATED) as z:
 for p in files:z.write(p,p.relative_to(root/'source'))
restored=runtime/'restored-files';restored.mkdir(exist_ok=True)
with zipfile.ZipFile(archive) as z:z.extractall(restored)
for p in files:assert hashlib.sha256(p.read_bytes()).digest()==hashlib.sha256((restored/p.relative_to(root/'source')).read_bytes()).digest()
out={'test':'OPS.restore_drill','status':'PASS','at_utc':datetime.datetime.now(datetime.timezone.utc).isoformat(),'source':'isolated synthetic shop 2 database','destination':'separate gold_restore database, no live egress','duration_s':round(time.perf_counter()-started,3),'counts_source':before,'counts_restored':after,'quote_snapshot_digest':snapshot_hash('gold_restore'),'checks':['consistent SQL export','import into independent database','financial and HPOS row counts','quote amount/status/snapshot digest'],'scope':'Local database restore, not a production-host filesystem or offsite disaster-recovery certification.'}
out['restored_application_files']=len(files);out['checks'].append('application-file ZIP extraction and per-file SHA256 equality');out['duration_s']=round(time.perf_counter()-started,3)
(root/'evidence/restore.json').write_text(json.dumps(out,indent=2));print(json.dumps(out,indent=2))
