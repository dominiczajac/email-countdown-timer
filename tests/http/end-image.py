"""Full HTTP end-image regression, only the fixed disposable loopback target."""
import hashlib, http.client, json, os, pathlib, subprocess
assert os.environ.get('ECD_HTTP_DISPOSABLE')=='1' and os.environ.get('GITHUB_ACTIONS')=='true'
root=pathlib.Path(__file__).resolve().parents[2]
wp=['wp','--path='+os.environ['ECD_HTTP_WP'],'--no-color','eval-file',str(root/'tests/http/end-image-fixture.php')]
checks=[]
def check(v,label):
    if not v: raise AssertionError(label)
    checks.append(label)
def request(method='GET'):
    conn=http.client.HTTPConnection('127.0.0.1',8082,timeout=8)
    try:
        conn.request(method,'/?ecd_action=render&ecd=end-http&mode=email',headers={'Accept':'image/gif'})
        res=conn.getresponse();return res.status,dict((k.lower(),v) for k,v in res.getheaders()),res.read()
    finally:conn.close()
expected=subprocess.check_output(wp+['seed'],text=True).strip()
s,h,b=request();check(s==200 and hashlib.sha256(b).hexdigest()==expected,'expired URL returns selected end-image GIF')
check(h.get('content-type')=='image/gif' and 'no-store' in h.get('cache-control','') and h.get('x-content-type-options')=='nosniff','end-image HTTP safeguards retained')
check('set-cookie' not in h and 'location' not in h,'no cookie or redirect to attachment')
s,h,b2=request();check(b2==b,'warm expired response stable')
s,h,empty=request('HEAD');check(s==200 and empty==b'' and h.get('content-type')=='image/gif','HEAD has no decoded body')
holder=subprocess.Popen(wp+['hold'],stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True)
try:
    check(holder.stdout.readline().strip()=='LOCK_HELD','separate session holds end-image lock')
    s,h,b=request();check(s==200 and h.get('x-email-countdown-mode')=='static-busy' and hashlib.sha256(b).hexdigest()==expected,'busy fallback uses same selected end image')
finally:holder.terminate();holder.communicate(timeout=5)
subprocess.check_call(wp+['trash'])
s,h,b=request();check(s==200 and hashlib.sha256(b).hexdigest()!=expected,'trashed attachment falls back to zero countdown')
pathlib.Path(os.environ['ECD_HTTP_EVIDENCE']+'/end-image.json').write_text(json.dumps({'checks':checks},indent=2)+'\n')
print('PASS',len(checks),'end-image HTTP checks')
