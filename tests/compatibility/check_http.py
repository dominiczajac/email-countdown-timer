"""Real HTTP compatibility, with page-cache positive controls and decoded images."""
import concurrent.futures, hashlib, http.client, io, json, os, pathlib, re, subprocess, time
from PIL import Image
assert os.environ.get('GITHUB_ACTIONS') == 'true' and os.environ.get('ECD_INTEGRATION_DISPOSABLE') == '1'
evidence = pathlib.Path(os.environ['ECD_COMPAT_EVIDENCE'])
root = pathlib.Path(__file__).resolve().parents[2]
from cli_json import read_cli_json
fixture = read_cli_json(evidence / 'fixture.json')
profile = os.environ['ECD_COMPAT_PROFILE']
wp = ['wp', '--path=' + os.environ['ECD_COMPAT_WP'], '--no-color', 'eval-file', str(root/'tests/compatibility/fixture.php')]
checks, failures, observations = [], [], {}
def check(value, name):
    (checks if value else failures).append(name)
def req(path, method='GET', accept='image/gif'):
    conn = http.client.HTTPConnection('localhost', 8081, timeout=20)
    try:
        start = time.perf_counter()
        conn.request(method, path, headers={'Accept':accept, 'User-Agent':'Mozilla/5.0 compatibility-lab'})
        res=conn.getresponse(); data=res.read()
        return res.status, {k.lower():v for k,v in res.getheaders()}, data, round((time.perf_counter()-start)*1000,3)
    finally: conn.close()
def decode(data):
    im = Image.open(io.BytesIO(data)); im.load()
    return im.format, im.size, getattr(im,'n_frames',1), im.info.get('duration')
def endpoint(timer, mode='email'):
    return '/?ecd_action=render&ecd='+timer+'&mode='+mode
try:
    # Verify the page cache is actually serving a page. Merely activating a plugin is insufficient.
    pages=[req('/',accept='text/html') for _ in range(4)]
    check(all(x[0]==200 for x in pages),'anonymous HTML page responds successfully')
    markers=[re.search(rb'id="ect-compat-origin" data-generation="([^"]+)"',x[2]) for x in pages]
    check(all(markers),'test origin marker is present in real theme output')
    hit=bool(all(markers) and markers[-1][1]==markers[-2][1])
    observations['page_cache_hit_confirmed']=hit
    (evidence/'anonymous-page.html').write_bytes(pages[-1][2])
    observations['page_headers']={k:v for k,v in pages[-1][1].items() if k in ('content-type','cache-control','wp-super-cache','x-powered-by')}
    if profile in ('publishing','commerce'):
        check(hit,'configured page cache has an observable warm HIT (unchanged origin marker)')
    else: check(not hit,'uncached control produces a new origin marker')
    if profile=='publishing':
        check(b'autoptimize_' in pages[-1][2],'Autoptimize actually rewrites HTML asset references')
        check(b'yoast-schema-graph' in pages[-1][2].lower(),'Yoast structured data survives HTML optimization')
    for phase in ('normal-order','reverse-order'):
        if phase=='reverse-order': subprocess.run(wp+['reverse'],check=True,capture_output=True)
        prefix=phase+': '
        s,h,b,ms=req(endpoint('compat-live'))
        f,dim,n,d=decode(b)
        check(s==200 and f=='GIF' and n==60 and d==1000,prefix+'valid 60-frame, one-second GIF')
        check(dim[0]>1 and dim[1]>1 and b.endswith(b';'),prefix+'no HTML/output appended to GIF')
        check('no-store' in h.get('cache-control','') and 'no-transform' in h.get('cache-control','') and h.get('x-content-type-options')=='nosniff',prefix+'binary cache/security headers preserved')
        observations[phase]={'cold_ms':ms,'response_bytes':len(b),'set_cookie': 'set-cookie' in h}
        check('location' not in h,prefix+'no redirect to an image or login page')
        s,h,other,_=req(endpoint('compat-other')); check(s==200 and other!=b,prefix+'different timers are not mixed by page cache')
        s,h,end,_=req(endpoint('compat-end'))
        check(s==200 and hashlib.sha256(end).hexdigest()==fixture['end_sha256'],prefix+'expired GIF matches independently rendered local end image')
        s,h,end2,_=req(endpoint('compat-end')); check(end2==end,prefix+'fixed expired URL remains correct on repeated GET')
        s,h,empty,_=req(endpoint('compat-live'),'HEAD'); check(s==200 and not empty and h.get('content-type')=='image/gif',prefix+'HEAD preserves image MIME without body')
        for accept,fmt in [('image/png','PNG'),('image/webp','WEBP')]:
            s,h,data,_=req(endpoint('compat-live','static'),accept=accept)
            check(s==200 and decode(data)[0]==fmt,prefix+'content negotiation '+fmt)
        s,h,data,_=req(endpoint('missing')); check(s==404 and decode(data)[0]=='PNG',prefix+'unknown ID is controlled 404 image')
        s,h,data,_=req(endpoint('compat-live'),'POST'); check(s==405,prefix+'POST cannot invoke renderer')
        subprocess.run(wp+['expire'],check=True,capture_output=True)
        s,h,expired,_=req(endpoint('compat-live'))
        check(s==200 and hashlib.sha256(expired).hexdigest()==fixture['end_sha256'],prefix+'same URL changes at deadline without flushing page cache')
        subprocess.run(wp+['restore'],check=True,capture_output=True)
        with concurrent.futures.ThreadPoolExecutor(max_workers=8) as pool:
            burst=list(pool.map(lambda _:req(endpoint('compat-live')),range(8)))
        check(all(x[0]==200 and decode(x[2])[0]=='GIF' for x in burst),prefix+'eight concurrent image requests remain valid')
        observations[phase]['burst_ms']=[x[3] for x in burst]
    # This is not an SLO or a full capacity measurement.
except Exception as e:
    failures.append(type(e).__name__+': '+str(e))
finally:
    report={'profile':profile,'checks':checks,'failures':failures,'observations':observations,'target':'Easy Countdown 12.5.0; runtime unchanged','methodology':'Loopback HTTP, actual plugins and databases. No TLS, Cloudflare, FPM or paid plugin binaries. Marker is a test shortcode, not a mocked cache.'}
    (evidence/'http-checks.json').write_text(json.dumps(report,indent=2)+'\n')
print(json.dumps(report,indent=2))
raise SystemExit(1 if failures else 0)
