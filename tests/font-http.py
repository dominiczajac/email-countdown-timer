"""Isolated server-rule tests. No production address or real font is used."""
import argparse, contextlib, http.client, json, os, pathlib, shutil, socket, subprocess, tempfile, time
ROOT=pathlib.Path(__file__).resolve().parents[1]
p=argparse.ArgumentParser();p.add_argument('--report');args=p.parse_args()
checks=[]
def check(value, name):
    if not value: raise AssertionError(name)
    checks.append(name)
def port():
    with socket.socket() as s:s.bind(('127.0.0.1',0));return s.getsockname()[1]
def get(p,path,method='GET'):
    c=http.client.HTTPConnection('127.0.0.1',p,timeout=3)
    try:c.request(method,path);r=c.getresponse();return r.status,r.read()
    finally:c.close()
@contextlib.contextmanager
def server(kind, tmp, www, enforce=True):
    p=port();conf=tmp/(kind+str(p)+'.conf');pid=tmp/(kind+str(p)+'.pid');log=tmp/(kind+str(p)+'.log')
    if kind=='apache':
        conf.write_text(f'''ServerRoot "{tmp}"
ServerName localhost
Listen 127.0.0.1:{p}
PidFile "{pid}"
ErrorLog "{log}"
LogLevel crit
LoadModule mpm_event_module /usr/lib/apache2/modules/mod_mpm_event.so
LoadModule authz_core_module /usr/lib/apache2/modules/mod_authz_core.so
LoadModule dir_module /usr/lib/apache2/modules/mod_dir.so
User www-data
Group www-data
DocumentRoot "{www}"
<Directory "{www}">
 Require all granted
 AllowOverride {'AuthConfig' if enforce else 'None'}
 Options -Indexes
 DirectoryIndex index.html
</Directory>
''')
        cmd=['/usr/sbin/apache2','-f',str(conf),'-DFOREGROUND']
    else:
        # Mirrors the documented ^~ path rule; other upload paths stay public.
        conf.write_text(f'''user www-data;
worker_processes 1;
pid {pid};
error_log {log} crit;
events {{worker_connections 64;}}
http {{ access_log off;
 server {{listen 127.0.0.1:{p};root {www};
 location ^~ /fonts/ {{ {'deny all;' if enforce else ''} }}
 location / {{try_files $uri $uri/ =404;}}
 }}
}}
''')
        cmd=['/usr/sbin/nginx','-c',str(conf),'-g','daemon off;']
    with open(tmp/'process.log','ab') as out:
        process=subprocess.Popen(cmd,stdout=out,stderr=out,start_new_session=True)
        try:
            for _ in range(60):
                if process.poll() is not None:raise RuntimeError((tmp/'process.log').read_text()+ (log.read_text() if log.exists() else ''))
                try:
                    if get(p,'/other.txt')[0]==200:break
                except OSError:time.sleep(.05)
            else:raise RuntimeError('Test server did not become ready')
            yield p
        finally:
            if process.poll() is None:process.terminate()
            try:process.wait(timeout=5)
            except subprocess.TimeoutExpired:process.kill();process.wait()
if os.geteuid()!=0: raise SystemExit('Run with sudo; isolated servers drop to www-data. No system configuration is changed.')
with tempfile.TemporaryDirectory(prefix='ect-font-http-') as d:
    tmp=pathlib.Path(d);tmp.chmod(0o755);www=tmp/'www';www.mkdir();fonts=www/'fonts';fonts.mkdir();(fonts/'nested').mkdir()
    (www/'other.txt').write_text('unrelated asset')
    fixture=b'ECT synthetic non-font fixture'
    for file in ['fixture.ttf','UPPER.OTF','nested/multisite.ttf']:(fonts/file).write_bytes(fixture)
    code="define('ABSPATH',__DIR__);require $argv[1];echo json_encode(Email_Countdown_Timer_Font_Access::templates());"
    templates=json.loads(subprocess.check_output(['php','-r',code,str(ROOT/'includes/class-email-countdown-timer-font-access.php')]))
    for name,text in templates.items():(fonts/name).write_text(text)
    for kind in ['apache','nginx']:
        with server(kind,tmp,www) as p:
            for path in ['fixture.ttf','UPPER.OTF','nested/multisite.ttf','index.html']:
                for method in ['GET','HEAD']:check(get(p,'/fonts/'+path,method)[0]==403,f'{kind}: {method} {path} denied')
            check(get(p,'/other.txt')==(200,b'unrelated asset'),f'{kind}: unrelated files unaffected')
            check((fonts/'fixture.ttf').read_bytes()==fixture,f'{kind}: server-side reads unaffected')
        with server(kind,tmp,www,False) as p:
            check(get(p,'/fonts/fixture.ttf')==(200,fixture),f'{kind}: ignored/absent access rule is NOT protection')
report={'checks':checks,'scope':'Loopback synthetic files, isolated Apache and nginx. No fonts, site data or production settings. Native font rendering is independently tested in WordPress integration.'}
if args.report:pathlib.Path(args.report).write_text(json.dumps(report,indent=2)+'\n')
print('PASS',len(checks),'font HTTP checks')
