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
packages=[]
for package in ['gold-commerce-theme','gold-commerce-core','gold-agah-connector']:
 target=out/(package+'-1.1.0.zip');write_archive(target,[(f,str(f.relative_to(root/'source'))) for f in (root/'source'/package).rglob('*') if f.is_file()]);packages.append(target)
records=[]
for p in packages:records.append({'file':p.name,'bytes':p.stat().st_size,'sha256':sha256(p.read_bytes()).hexdigest()})
files=[]
for name in ['source','docs','tests','fixtures','evidence','tools']:
 for f in (root/name).rglob('*'):
  if not f.is_file() or any(part in ['node_modules','__pycache__'] for part in f.parts):continue
  if f.suffix=='.log':continue # Raw diagnostics may contain SQL payloads. Structured redacted evidence is authoritative.
  files.append((f,str(f.relative_to(root))))
source_records=[{'file':name,'sha256':sha256(f.read_bytes()).hexdigest()} for f,name in files]
manifest={'product':'Gold Commerce','version':'1.1.0','specification':'Gold-Commerce-Development-Package-v1.1','packages':records,'files':source_records,'agah_status':'MOCK_ONLY','agah_reference_included':False}
(out/'manifest.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2)+'\n')
entries=files+[(p,p.name) for p in packages]+[(out/'manifest.json','manifest.json')]
if (root/'README.md').exists():entries.append((root/'README.md','README.md'))
if (root/'.gitignore').exists():entries.append((root/'.gitignore','.gitignore'))
write_archive(out/'Gold-Commerce-1.1.0-complete-delivery.zip',entries)
for p in packages+[out/'Gold-Commerce-1.1.0-complete-delivery.zip']:
 print(json.dumps({'file':p.name,'bytes':p.stat().st_size,'sha256':sha256(p.read_bytes()).hexdigest()}))
