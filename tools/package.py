"""Deterministic install archives and complete source/evidence delivery. No runtime data."""
from pathlib import Path
from zipfile import ZipFile,ZipInfo,ZIP_DEFLATED
from hashlib import sha256
import json,os
root=Path(__file__).resolve().parents[1];out=root/'work/packages';out.mkdir(parents=True,exist_ok=True)
def write_archive(target,entries):
 with ZipFile(target,'w',ZIP_DEFLATED) as z:
  for file,name in sorted(entries,key=lambda item:item[1]):
   info=ZipInfo(name,(2026,11,10,0,0,0));info.compress_type=ZIP_DEFLATED;info.external_attr=0o100644<<16;z.writestr(info,file.read_bytes())
 with ZipFile(target) as z:assert z.testzip() is None
package_versions=[('gold-commerce-theme','1.2.1'),('gold-commerce-core','1.2.0'),('gold-agah-connector','1.1.0'),('tala-demo-catalog','1.0.0')]
packages=[]
for package,version in package_versions:
 target=out/(package+'-'+version+'.zip');write_archive(target,[(f,str(f.relative_to(root/'source'))) for f in (root/'source'/package).rglob('*') if f.is_file()]);packages.append(target)
records=[]
for p in packages:records.append({'file':p.name,'bytes':p.stat().st_size,'sha256':sha256(p.read_bytes()).hexdigest()})
files=[]
for name in ['source','docs','tests','fixtures','evidence','tools']:
 for f in (root/name).rglob('*'):
  if not f.is_file() or any(part in ['node_modules','__pycache__'] for part in f.parts):continue
  if f.suffix=='.log':continue # Raw diagnostics may contain SQL payloads. Structured redacted evidence is authoritative.
  files.append((f,str(f.relative_to(root))))
source_records=[{'file':name,'sha256':sha256(f.read_bytes()).hexdigest()} for f,name in files]
manifest={'product':'Gold Commerce','version':'1.2.2','specification':'Gold-Commerce-Development-Package-v1.1','packages':records,'files':source_records,'agah_status':'MOCK_ONLY','agah_reference_included':False,'theme':{'version':'1.2.1','default_scheme':'light','visitor_personalization':True,'sitewide_generated_palette':True,'custom_color_picker':True,'accessibility_panel':True},'demo_catalog':{'version':'1.0.0','optional':True,'products':27,'images':9,'prices':'FICTIONAL','purchasable_via_standard_woocommerce':True}}
(out/'manifest.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2)+'\n')
inner_sums=''.join(f'{sha256(p.read_bytes()).hexdigest()}  {p.name}\n' for p in packages+[out/'manifest.json'])
(out/'SHA256SUMS.txt').write_text(inner_sums)
entries=files+[(p,p.name) for p in packages]+[(out/'manifest.json','manifest.json'),(out/'SHA256SUMS.txt','SHA256SUMS.txt')]
if (root/'README.md').exists():entries.append((root/'README.md','README.md'))
if (root/'.gitignore').exists():entries.append((root/'.gitignore','.gitignore'))
complete=out/'Gold-Commerce-1.2.2-complete-delivery.zip';write_archive(complete,entries)
external=packages+[out/'manifest.json',complete]
(out/'SHA256SUMS-1.2.2.txt').write_text(''.join(f'{sha256(p.read_bytes()).hexdigest()}  {p.name}\n' for p in external))
for p in packages+[complete]:
 print(json.dumps({'file':p.name,'bytes':p.stat().st_size,'sha256':sha256(p.read_bytes()).hexdigest()}))
