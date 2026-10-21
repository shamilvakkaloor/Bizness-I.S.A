"""Static consistency checks only. This does not parse or execute PHP."""
from pathlib import Path
import re, json, subprocess
root=Path(__file__).resolve().parents[1]
results=[]
def check(name,ok,detail=''):
 results.append({'check':name,'passed':bool(ok),'detail':detail})
for path in ['composer.json','public/manifest.webmanifest']:
 try:json.loads((root/path).read_text());check('JSON: '+path,True)
 except Exception as e:check('JSON: '+path,False,str(e))
for path in ['public/app.js','public/sw.js','public/print.js']:
 proc=subprocess.run(['node','--check',str(root/path)],capture_output=True,text=True)
 check('JavaScript syntax: '+path,proc.returncode==0,proc.stderr)
route_source=(root/'routes/web.php').read_text()
for controller,method in re.findall(r'([A-Za-z]+Controller)::class,\s*\'([A-Za-z]+)\'',route_source):
 path=root/'app/Http/Controllers'/f'{controller}.php'
 check(f'Route target {controller}.{method}',path.exists() and bool(re.search(r'function\s+'+re.escape(method)+r'\s*\(',path.read_text() if path.exists() else '')))
for path in root.rglob('*.php'):
 if path.name.endswith('.blade.php'):continue
 check('PHP opening tag: '+str(path.relative_to(root)),path.read_text().startswith('<?php'))
check('No bundled environment credentials',not (root/'.env').exists())
check('No bundled demo password/login',not (root/'database/seeders/DemoSeeder.php').exists())
result={'scope':'Static consistency only; no PHP parsing/execution, browser pass or backend validation','checks':results,'php_tests':'NOT RUN: PHP/Composer unavailable','browser_tests':'NOT RUN: browser preview connection unavailable'}
(root/'validation-result.json').write_text(json.dumps(result,indent=2)+'\n')
print(f"{sum(x['passed'] for x in results)}/{len(results)} static checks passed. PHP and browser tests NOT RUN.")
raise SystemExit(0 if all(x['passed'] for x in results) else 1)
