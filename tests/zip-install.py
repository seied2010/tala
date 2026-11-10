import subprocess,json,os,shutil,zipfile,datetime
from pathlib import Path
root=Path(__file__).resolve().parents[1];runtime=root/'runtime';creds=json.loads((runtime/'credentials.json').read_text());env=dict(os.environ,MYSQL_PWD=creds['db_password'])
subprocess.run(['docker','exec','-e','MYSQL_PWD','gc-db','mariadb','-uroot','-e','CREATE DATABASE IF NOT EXISTS gold_zip;'],env=env,capture_output=True,check=True)
site=runtime/'zipsite';site.mkdir(exist_ok=True)
for p in (runtime/'wp').iterdir():
 if p.name=='wp-content':continue
 target=site/p.name
 if target.exists() or target.is_symlink():continue
 if p.is_file():shutil.copyfile(p,target)
 else:target.symlink_to('../wp/'+p.name)
(site/'wp-content/plugins').mkdir(parents=True,exist_ok=True);(site/'wp-content/themes').mkdir(exist_ok=True)
wc=site/'wp-content/plugins/woocommerce'
if not wc.exists():wc.symlink_to('../../../wp/wp-content/plugins/woocommerce')
config=(runtime/'site2/wp-config.php').read_text().replace("'gold2'","'gold_zip'")
(site/'wp-config.php').write_text(config)
def wp(*args):
 r=subprocess.run(['docker','exec','gc-site2','wp','--path=/project/runtime/zipsite',*args],capture_output=True,text=True)
 if r.returncode:raise RuntimeError((r.stdout+r.stderr)[-1000:])
 return r.stdout
installed=subprocess.run(['docker','exec','gc-site2','wp','--path=/project/runtime/zipsite','core','is-installed'],capture_output=True).returncode==0
if not installed:wp('core','install','--url=http://localhost:8083','--title=ZIP install test','--admin_user=zipadmin','--admin_password='+creds['admin_password'],'--admin_email=zip@example.test','--skip-email')
wp('plugin','activate','woocommerce')
for name in ['gold-commerce-core','gold-agah-connector']:
 wp('plugin','install','/project/work/packages/'+name+'-1.1.0.zip','--force','--activate')
wp('theme','install','/project/work/packages/gold-commerce-theme-1.1.0.zip','--force','--activate')
wp('eval-file','/project/fixtures/install-sample.php','--use-include')
raw=wp('eval-file','/project/tests/zip-install.php','--use-include');result=json.loads(raw[raw.index('{'):]);(root/'evidence/zip-install.json').write_text(json.dumps(result,indent=2));print(json.dumps(result,indent=2))
