"""Create a Hostinger PHP release; credentials are supplied in an ignored JSON file."""
import argparse,json,shutil
from pathlib import Path
p=argparse.ArgumentParser();p.add_argument('--secrets',required=True);args=p.parse_args()
root=Path(__file__).resolve().parents[1];out=root/'.deploy';backend=out/'backend'
out.mkdir(exist_ok=True);backend.mkdir(exist_ok=True)
for folder in ['app','bootstrap','config','database','resources','routes','vendor','storage']:
    shutil.copytree(root/folder,backend/folder,dirs_exist_ok=True,ignore=shutil.ignore_patterns('*.log','*.sqlite','.env'))
for folder in ['storage/framework/sessions','storage/framework/views','storage/framework/cache/data','storage/app/private','storage/logs','bootstrap/cache']:
    target=backend/folder;target.mkdir(parents=True,exist_ok=True)
    for f in target.iterdir():
        if f.is_file() and f.name!='.gitkeep': f.unlink()
shutil.copytree(root/'public',out,dirs_exist_ok=True)
shutil.copy(root/'deploy/root.htaccess',out/'.htaccess')
(backend/'.htaccess').write_text('Require all denied\n')
shutil.copy(root/'artisan',backend/'artisan')
s=(out/'index.php').read_text().replace("__DIR__.'/../", "__DIR__.'/backend/")
(out/'index.php').write_text(s)
shutil.copy(root/'deploy/runtime-bootstrap.php',backend/'bootstrap/runtime-bootstrap.php')
p=backend/'bootstrap/app.php';p.write_text(p.read_text().replace('<?php','<?php\nrequire __DIR__."/runtime-bootstrap.php";',1))
secrets=json.loads(Path(args.secrets).read_text())
env={'APP_NAME':'Buziness','APP_ENV':'production','APP_DEBUG':'false','APP_URL':'https://buziness.exelapp.com','APP_TIMEZONE':'Asia/Kolkata','APP_KEY':secrets['app_key'],'DB_CONNECTION':'mysql','DB_HOST':'127.0.0.1','DB_DATABASE':'u936220228_buziness','DB_USERNAME':'u936220228_buziness','DB_PASSWORD':secrets['db_password'],'SESSION_SECURE_COOKIE':'true','SESSION_DRIVER':'file','CACHE_STORE':'file','LOG_CHANNEL':'single'}
def php(s):return "'"+s.replace('\\','\\\\').replace("'","\\'")+"'"
(backend/'deploy-config.php').write_text('<?php return ['+','.join(php(k)+'=>'+php(v) for k,v in env.items())+'];')
template=(root/'deploy/setup-template.php').read_text()
import hashlib
template=template.replace('SETUP_TOKEN_HASH',hashlib.sha256(secrets['setup_token'].encode()).hexdigest())
# The initial password is submitted over HTTPS, never embedded in published source.
(out/'initialize.php').write_text(template)
print('Prepared .deploy; runtime secrets are excluded from Git.')
