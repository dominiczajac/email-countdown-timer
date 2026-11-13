"""Real WordPress public HTML route, cross-origin sandbox and local clock."""
import http.server,json,os,pathlib,shutil,subprocess,threading
from urllib.parse import urlsplit
from playwright.sync_api import sync_playwright,expect
assert os.environ.get('GITHUB_ACTIONS')=='true' and os.environ.get('ECD_INTEGRATION_DISPOSABLE')=='1'
root=pathlib.Path(__file__).resolve().parents[2];out=pathlib.Path(os.environ['RUNNER_TEMP'])/'admin-evidence';base='http://localhost:8081';checks=[]
def check(v,label):
 if not v:raise AssertionError(label)
 checks.append(label)
def wp(*args):return subprocess.check_output(['wp','--path='+os.environ['ECD_BROWSER_WP'],*args],text=True)
# Keep every other suite's data intact; this fixture is exclusively synthetic.
original=wp('option','get','easy_countdown_timers','--format=json')
class Host(http.server.BaseHTTPRequestHandler):
 def do_GET(self):
  body=('<!doctype html><iframe title="External countdown" sandbox="allow-scripts" width="600" height="220" referrerpolicy="no-referrer" src="'+base+'/?ecd_action=frame&amp;ecd=frame-live"></iframe>').encode();self.send_response(200);self.send_header('Content-Type','text/html');self.end_headers();self.wfile.write(body)
 def log_message(self,*args):pass
host=http.server.ThreadingHTTPServer(('127.0.0.1',0),Host);threading.Thread(target=host.serve_forever,daemon=True).start()
try:
 wp('eval-file',os.environ.get('ECD_FRAME_FIXTURE',str(root/'tests/browser/frame-fixture.php')))
 with sync_playwright() as p:
  b=p.chromium.launch(executable_path=shutil.which('google-chrome') or shutil.which('chromium'),args=['--no-sandbox']);ctx=b.new_context(reduced_motion='reduce');api=ctx.request
  url=base+'/?ecd_action=frame&ecd=frame-live'
  cold=api.get(url);check(cold.status==200 and cold.headers.get('x-email-countdown-frame-cache')=='miss','cold frame uses a protected generation')
  check(api.get(url+'&_t=ignored').headers.get('x-email-countdown-frame-cache')=='hit','warm request reuses one frame cache slot despite cache buster')
  text=cold.text();check('PRIVATE-FRAME-ONLY' not in text and '.ttf' not in text and '/var/www/' not in text,'HTML exposes raster glyphs without names or font files/paths')
  check('frame-ancestors *' in cold.headers.get('content-security-policy','') and 'X-Frame-Options'.lower() not in cold.headers,'only the standalone frame permits external parents')
  check('no-store' in cold.headers.get('cache-control','') and cold.headers.get('referrer-policy')=='no-referrer','dynamic HTML and referrer headers')
  head=api.head(url);check(head.status==200 and not head.body(),'HEAD has an empty body')
  denied=api.post(url);check(denied.status==405 and denied.headers.get('allow')=='GET, HEAD','write methods rejected')
  for bad in ['missing','A','../outside','x'*201]:
   response=api.get(base+'/?ecd_action=frame&ecd='+bad);check(response.status==404 and 'Countdown is temporarily unavailable' in response.text() and 'ready:false' in response.text(),'unknown/malformed ID has a visible, signal-bearing failure')
  failed=api.get(base+'/?ecd_action=frame&ecd=frame-invalid');check(failed.status==503 and 'ready:false' in failed.text(),'invalid saved geometry fails visibly without exposing an exception')
  page=ctx.new_page();requests=[];page.on('request',lambda r:requests.append(r.url));page.clock.install();page.goto('http://127.0.0.1:'+str(host.server_port))
  live=page.get_by_title('External countdown').content_frame.locator('#ect-frame-canvas');expect(live).to_be_visible();left=int(live.get_attribute('data-remaining'));page.clock.fast_forward(65000)
  check(abs(int(live.get_attribute('data-remaining'))-(left-65))<=1,'cross-origin sandbox keeps ticking past one minute with reduced motion')
  check(sum('ecd_action=frame' in u for u in requests)==1 and not any('ecd_action=render' in u for u in requests),'no per-second PHP/image requests')
  pause=page.get_by_title('External countdown').content_frame.get_by_role('button',name='Pause countdown',exact=True);pause.click();held=live.get_attribute('data-remaining');page.clock.fast_forward(5000);check(live.get_attribute('data-remaining')==held,'pause freezes the snapshot')
  page.get_by_title('External countdown').content_frame.get_by_role('button',name='Resume countdown',exact=True).click();check(int(live.get_attribute('data-remaining'))<=int(held)-5,'resume catches up to the absolute deadline')
  page.screenshot(path=str(out/'external-frame.png'))
  # Use an unmodified clock to check the real server's near deadline.
  wp('eval-file',os.environ.get('ECD_FRAME_FIXTURE',str(root/'tests/browser/frame-fixture.php')))
  real=b.new_context();ended=real.new_page();ended.goto(base+'/?ecd_action=frame&ecd=frame-expire');expect(ended.locator('#ect-frame-canvas')).to_be_visible();ended.wait_for_timeout(5000)
  check(ended.locator('#ect-frame-canvas').is_hidden() and ended.locator('main>img').evaluate('(im)=>im.naturalWidth>1'),'expiry loads the existing public end-image route')
  check(not any(urlsplit(u).hostname in ['fonts.googleapis.com','fonts.gstatic.com'] for u in requests),'no external font service')
  b.close()
 (out/'frame-checks.json').write_text(json.dumps({'checks':checks,'count':len(checks),'scope':'Owned real WordPress; external HTTP parent on a different host/port; not email iframe support'},indent=2)+'\n');print('PASS',len(checks),'native frame checks')
finally:
 host.shutdown();wp('option','update','easy_countdown_timers',original,'--format=json')
