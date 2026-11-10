from pathlib import Path
import json,datetime,subprocess
from docx import Document
from openpyxl import load_workbook
from pypdf import PdfReader
root=Path(__file__).resolve().parents[1];evidence=root/'evidence';results=[]
for suffix,container in [('83','gc-site1'),('85','gc-site2')]:
 subprocess.run(['docker','exec',container,'php','/project/tests/export.php',suffix],capture_output=True,check=True)
 d=Document(evidence/('sample-report-'+suffix+'.docx'));assert d.tables[0].cell(1,1).text=='28073800'
 w=load_workbook(evidence/('sample-report-'+suffix+'.xlsx'));assert w.active['B2'].value=='28073800';assert w.active['A3'].data_type=='s'
 p=PdfReader(evidence/('sample-report-'+suffix+'.pdf'));assert len(p.pages)>0;assert '28073800' in ''.join(x.extract_text() for x in p.pages)
 results.append({'test':'T43.external_readers_php'+suffix,'status':'PASS','readers':['python-docx','openpyxl','pypdf'],'xlsx_formula_injection':'inlineStr remains string','pdf_pages':len(p.pages)})
(evidence/'export-readers.json').write_text(json.dumps({'at_utc':datetime.datetime.now(datetime.timezone.utc).isoformat(),'results':results},indent=2));print(json.dumps(results))
