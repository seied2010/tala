"""Fresh Linux Docker test environment. Refuses to overwrite existing containers/data."""
from pathlib import Path
import subprocess,json,secrets,os,tarfile,zipfile,shutil
root=Path(__file__).resolve().parents[1];runtime=root/'runtime'
def command(args,**kwargs):return subprocess.run(args,check=True,**kwargs)
for name in ['gc-db','gc-site1','gc-site2']:
 if subprocess.run(['docker','container','inspect',name],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL).returncode==0:raise SystemExit('Refusing to overwrite existing test container: '+name)
if runtime.exists():raise SystemExit('Use a fresh checkout: runtime already exists.')
runtime.mkdir();downloads=runtime/'downloads';downloads.mkdir()
urls={'wordpress.tar.gz':'https://codeload.github.com/WordPress/WordPress/tar.gz/refs/tags/7.1.4','woocommerce.zip':'https://github.com/woocommerce/woocommerce/releases/download/11.2.2/woocommerce.zip','wordpress-seo.zip':'https://github.com/Yoast/wordpress-seo/releases/download/28.8/wordpress-seo-28.8.zip'}
for name,url in urls.items():command(['curl','--fail','--location','--max-time','180',url,'-o',str(downloads/name)])
wp=runtime/'wp';wp.mkdir()
with tarfile.open(downloads/'wordpress.tar.gz') as archive:
 for item in archive.getmembers():
  item.name='/'.join(item.name.split('/')[1:])
  if item.name:archive.extract(item,wp,filter='data')
for archive_name in ['woocommerce.zip','wordpress-seo.zip']:
 with zipfile.ZipFile(downloads/archive_name) as archive:
  for name in archive.namelist():
   if not (wp/'wp-content/plugins'/name).resolve().is_relative_to((wp/'wp-content/plugins').resolve()):raise RuntimeError('Unsafe archive path')
  archive.extractall(wp/'wp-content/plugins')
c={'db_password':secrets.token_hex(24),'admin_password':secrets.token_hex(24),'load_secret':secrets.token_hex(32)}
(runtime/'credentials.json').write_text(json.dumps(c));(runtime/'credentials.json').chmod(0o600)
images=['wordpress:cli-php8.3','wordpress:cli-php8.5','mariadb:11']
for image in images:command(['docker','pull',image])
subprocess.run(['docker','network','create','gc-test'],capture_output=True)
env=dict(os.environ,GC_DB_PASSWORD=c['db_password'],MARIADB_ROOT_PASSWORD=c['db_password'],MYSQL_PWD=c['db_password'])
command(['docker','run','-d','--name','gc-db','--network','gc-test','--tmpfs','/var/lib/mysql:rw,size=2g','-e','MARIADB_ROOT_PASSWORD','mariadb:11'],env=env)
import time
for attempt in range(30):
 ready=subprocess.run(['docker','exec','-e','MYSQL_PWD','gc-db','mariadb','-uroot','-e','CREATE DATABASE IF NOT EXISTS gold1; CREATE DATABASE IF NOT EXISTS gold2;'],env=env,capture_output=True)
 if ready.returncode==0:break
 time.sleep(1)
else:raise RuntimeError('MariaDB did not become ready')
for n,php in [(1,'8.3'),(2,'8.5')]:
 site=runtime/('site'+str(n));shutil.copytree(wp,site)
 for plugin in ['gold-commerce-core','gold-agah-connector']:(site/'wp-content/plugins'/plugin).symlink_to('../../../../source/'+plugin)
 (site/'wp-content/themes/gold-commerce-theme').symlink_to('../../../../source/gold-commerce-theme')
 config="<?php\ndefine('DB_NAME','gold"+str(n)+"');\ndefine('DB_USER','root');\ndefine('DB_PASSWORD',getenv('GC_DB_PASSWORD'));\ndefine('DB_HOST','gc-db');\ndefine('DB_CHARSET','utf8mb4');\n$table_prefix='wp_';\ndefine('WP_ENVIRONMENT_TYPE','local');\ndefine('WP_DEBUG',false);\ndefine('DISABLE_WP_CRON',true);\n"
 for key in ['AUTH_KEY','SECURE_AUTH_KEY','LOGGED_IN_KEY','NONCE_KEY','AUTH_SALT','SECURE_AUTH_SALT','LOGGED_IN_SALT','NONCE_SALT']:config+="define('"+key+"','"+secrets.token_hex(48)+"');\n"
 config+="define('ABSPATH',__DIR__.'/');require_once ABSPATH.'wp-settings.php';\n";(site/'wp-config.php').write_text(config);(site/'wp-config.php').chmod(0o600)
 uid=str(os.getuid())+':'+str(os.getgid())
 command(['docker','run','-d','--name','gc-site'+str(n),'--network','gc-test','--user',uid,'-p','127.0.0.1:'+str(8080+n)+':8080','-e','GC_DB_PASSWORD','-v',str(root)+':/project','-w','/project/runtime/site'+str(n),'--entrypoint','php','wordpress:cli-php'+php,'-S','0.0.0.0:8080','-t','.'],env=env)
 def cli(*args):command(['docker','exec','gc-site'+str(n),'wp','--path=/project/runtime/site'+str(n),*args],stdout=subprocess.DEVNULL)
 cli('core','install','--url=http://localhost:'+str(8080+n),'--title=Fictional Gold '+str(n),'--admin_user=gcadmin','--admin_password='+c['admin_password'],'--admin_email=owner'+str(n)+'@example.test','--skip-email')
 cli('plugin','activate','woocommerce','gold-commerce-core','gold-agah-connector','wordpress-seo');cli('theme','activate','gold-commerce-theme');cli('eval-file','/project/fixtures/install-sample.php','--use-include')
print('Independent synthetic sites ready on localhost:8081 and localhost:8082. Credentials remain in ignored runtime/credentials.json. No real payment/rate/Agah is connected.')
