"""Full loopback HTTP tests; target is deliberately not configurable."""
import concurrent.futures, hashlib, http.client, json, math, os, pathlib, statistics, subprocess, threading, time
if os.environ.get('ECD_HTTP_DISPOSABLE') != '1' or os.environ.get('GITHUB_ACTIONS') != 'true':
    raise SystemExit('Disposable CI only')
ROOT=pathlib.Path(__file__).resolve().parents[2]
OUT=pathlib.Path(os.environ['ECD_HTTP_EVIDENCE'])
WP=['wp','--path='+os.environ['ECD_HTTP_WP'],'--no-color','eval-file',str(ROOT/'tests/http/fixture.php')]
checks=[]; results={}
def check(condition, message):
    if not condition: raise AssertionError(message)
    checks.append(message)
def fixture(task):
    return subprocess.check_output(WP+[task],text=True,stderr=subprocess.STDOUT).strip()
def clear_publications(): (OUT/'publications.jsonl').write_text('')
def publications(): return [json.loads(x) for x in (OUT/'publications.jsonl').read_text().splitlines() if x]
def align():
    # Keep a measured burst inside one real 15-second bucket, without freezing time.
    remaining=15-time.time()%15
    if remaining<6: time.sleep(remaining+0.08)
def request(mode='email', method='GET', timer='http-timer', barrier=None, canary=False):
    if barrier: barrier.wait(timeout=10)
    path='/?ecd_action=render&ecd='+timer+'&mode='+mode+'&_t='+str(time.time_ns())
    headers={'Accept':'image/gif,image/png'}
    if canary:
        path+='&email=ECT_HTTP_PRIVATE_CANARY%40example.invalid'
        headers['User-Agent']='ECT_HTTP_PRIVATE_CANARY'
    start=time.perf_counter(); conn=http.client.HTTPConnection('127.0.0.1',8082,timeout=12)
    try:
        conn.request(method,path,headers=headers); response=conn.getresponse(); body=response.read()
        result = {'ms':(time.perf_counter()-start)*1000,'status':response.status,'headers':dict((k.lower(),v) for k,v in response.getheaders()),'bytes':len(body),'sha256':hashlib.sha256(body).hexdigest(),'magic':body[:6].hex()}
        if 'x-email-countdown-mode' in result['headers']:
            # Decode after latency was recorded; the extra process is not part of HTTP timing.
            decoded=subprocess.check_output(['php','-r','$a=new Imagick();$a->readImageBlob(stream_get_contents(STDIN));echo json_encode([$a->getNumberImages(),$a->getImageWidth(),$a->getImageHeight()]);'],input=body)
            result['frames'],result['width'],result['height']=json.loads(decoded)
        return result
    finally: conn.close()
def burst(n=8):
    barrier=threading.Barrier(n)
    with concurrent.futures.ThreadPoolExecutor(max_workers=n) as executor:
        return list(executor.map(lambda _:request(barrier=barrier),range(n)))
def summary(rows):
    samples=sorted(x['ms'] for x in rows)
    return {'n':len(rows),'p50_ms':round(statistics.median(samples),3),'observed_p95_ms':round(samples[math.ceil(.95*len(samples))-1],3),'max_ms':round(max(samples),3),'statuses':{str(s):sum(x['status']==s for x in rows) for s in set(x['status'] for x in rows)},'response_bytes':sorted(set(x['bytes'] for x in rows)),'samples_ms':[round(x['ms'],3) for x in rows]}
def resources():
    parent=int(os.environ['ECD_HTTP_SERVER_PID']); ids=[parent]
    for path in pathlib.Path('/proc').glob('[0-9]*/stat'):
        try:
            fields=path.read_text().split()
            if int(fields[3])==parent: ids.append(int(fields[0]))
        except (OSError,ValueError): pass
    cpu=0; hwm=0
    for pid in set(ids):
        try:
            fields=pathlib.Path(f'/proc/{pid}/stat').read_text().split();cpu+=(int(fields[13])+int(fields[14]))/os.sysconf('SC_CLK_TCK')
            for line in pathlib.Path(f'/proc/{pid}/status').read_text().splitlines():
                if line.startswith('VmHWM:'): hwm+=int(line.split()[1])
        except OSError: pass
    return {'server_processes_observed':len(set(ids)),'aggregate_cpu_seconds':round(cpu,3),'sum_process_peak_rss_kib':hwm}
try:
    start_res=resources()
    check(start_res['server_processes_observed']>=2,'Multiple PHP server processes actually observed')
    for mode in ['static','email']:
        rows=[]
        for _ in range(7):
            fixture('reset'); rows.append(request(mode))
        check(all(x['status']==200 for x in rows),f'Cold {mode} HTTP requests succeed')
        results['cold_'+mode]=summary(rows)
    align();fixture('reset');request();clear_publications()
    with concurrent.futures.ThreadPoolExecutor(max_workers=8) as executor:
        rows=list(executor.map(lambda _:request(),range(40)))
    check(all(x['status']==200 for x in rows),'Warm HTTP requests succeed')
    check(not publications(),'Warm HTTP phase does not regenerate an image')
    results['warm_gif_c8']=summary(rows);results['warm_gif_c8']['publications']=len(publications())
    for phase in ['cold_burst','expired_bucket_burst']:
        align();fixture('reset')
        if phase=='expired_bucket_burst': request();fixture('stale')
        clear_publications();rows=burst()
        check(all(x['status']==200 for x in rows),phase+': all eight simultaneous HTTP requests succeed')
        check(len(publications())==1,phase+': exactly one cache publication after successful rendering')
        check(len({x['sha256'] for x in rows})==1,phase+': all responses use the same generated GIF')
        results[phase]=summary(rows);results[phase]['publications']=len(publications())
    fixture('reset');clear_publications()
    holder=subprocess.Popen(WP+['hold'],stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True)
    try:
        check(holder.stdout.readline().strip()=='LOCK_HELD','Separate database session owns test lock')
        row=request()
        check(row['status']==200 and row['headers'].get('x-email-countdown-mode')=='static-busy','Contended render returns a useful static image')
        check(row.get('frames')==1 and row.get('width',0)>1 and row['headers'].get('content-type')=='image/gif','Fallback is one valid GIF frame, not an invisible error pixel')
        check(850<=row['ms']<3500,'Lock wait is approximately one second, not unbounded')
        check(not publications(),'Static follower did not publish to animation cache')
        expected=fixture('expire');expired=request()
        check(expired['status']==200 and expired.get('frames')==1 and expired['sha256']==expected,'Fallback after deadline matches current zero countdown, never stale pre-deadline image')
        check(not publications(),'Expired static fallback does not poison animation cache')
        check(request(method='HEAD')['status']==200,'HEAD remains cheap while generator lock is held')
        check(request(timer='unknown')['status']==404,'Unknown timer remains 404 while lock is held')
        results['held_lock']=summary([row])
    finally:
        holder.terminate();holder.communicate(timeout=5)
    fixture('seed')
    check(request()['status']==200,'Terminated lock owner releases lock on database disconnect')
    for marker,reason in [('lock-error','error'),('unsupported-lock','unsupported')]:
        fixture('reset');clear_publications();(OUT/marker).touch()
        try:
            row=request()
            check(row['status']==200 and row['headers'].get('x-email-countdown-mode')=='static-'+reason and row.get('frames')==1,reason+': backend failure produces valid current static GIF')
            check(not publications(),reason+': no animation cache publication without lock')
            results['fallback_'+reason]=summary([row])
        finally: (OUT/marker).unlink()
    fixture('reset')
    check(request()['status']==200,'Normal rendering resumes after backend recovery')
    align();fixture('reset');clear_publications();(OUT/'lost-lock').touch()
    try:
        row=request()
        check(row['status']==200 and row['headers'].get('x-email-countdown-mode')=='uncached-lock-lost' and row.get('frames')==60,'Fresh completed GIF is returned after simulated ownership loss')
        check(not publications(),'Lost owner does not publish completed animation')
        results['lost_lock_uncached']=summary([row])
    finally:(OUT/'lost-lock').unlink()
    clear_publications();expected=fixture('near-expiry');(OUT/'lost-after-deadline').touch()
    try:
        row=request()
        check(row['status']==200 and row.get('frames')==1 and row['sha256']==expected,'Ownership loss crossing deadline returns current zero frame')
        check(not publications(),'Cross-deadline fallback never publishes the old completed animation')
    finally:(OUT/'lost-after-deadline').unlink();fixture('seed')
    fixture('reset');(OUT/'fail-publication').touch()
    try: check(request()['status']==503,'Injected cache-publication exception returns sanitized 503')
    finally: (OUT/'fail-publication').unlink()
    check(request()['status']==200,'Exception path releases the session lock')
    row=request(canary=True)
    check(row['status']==200 and 'set-cookie' not in row['headers'],'Anonymous image response adds no cookie')
    check('no-store' in row['headers'].get('cache-control','') and 'no-transform' in row['headers'].get('cache-control',''),'Image advertises cache bypass and no transformation')
    check(row['headers'].get('x-content-type-options')=='nosniff','Image response has nosniff')
    check(fixture('privacy')=='NO_REQUEST_CANARY_IN_OPTIONS','Synthetic recipient/agent canary not persisted in options')
    check(not (OUT/'outbound.jsonl').exists() or not (OUT/'outbound.jsonl').read_text().strip(),'No outbound WordPress HTTP API requests during synthetic public rendering')
    results['resources_start']=start_res;results['resources_end']=resources()
    results['environment']=json.loads((OUT/'versions.json').read_text())
    results['source']=subprocess.check_output(['git','rev-parse','HEAD'],cwd=ROOT,text=True).strip()
    results['methodology']='Loopback HTTP, PHP CLI server with eight workers, real WordPress startup per request, MySQL and optional Redis. Local runner TTF, 600px minimum width, real time. Cache-cold is image-transient cold, not cold OS/opcache. No FlyingPress/WP Rocket, CDN, TLS, remote network or production traffic. Seven cold samples have a descriptive maximum-like p95, not an SLO. RSS is aggregate process high-water memory, not isolated render cost. Publication observer does not change distributed source.'
    results['checks']=checks
    print(json.dumps(results,indent=2))
finally:
    results['checks']=checks
    (OUT/'http-results.json').write_text(json.dumps(results,indent=2)+'\n')
