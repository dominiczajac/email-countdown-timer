"""Native Thunderbird .eml rendering, no mailbox/login or outgoing email."""
import email.policy
from email.message import EmailMessage
import json
import os
from pathlib import Path
import subprocess
import time
from PIL import Image

out=Path(os.environ['ECD_DELIVERY_EVIDENCE'])
assert os.environ.get('GITHUB_ACTIONS')=='true' and os.environ.get('ECD_INTEGRATION_DISPOSABLE')=='1'
fixture=json.loads((out/'fixture.json').read_text())
message=EmailMessage(policy=email.policy.SMTP)
message['From']='fixture@example.invalid';message['To']='recipient@example.invalid'
message['Subject']='Easy Countdown isolated rendering test'
message.set_content('Synthetic offer ended. Ends: 2001-01-01 00:00:05. No message is sent.')
message.add_alternative('<!doctype html><html><body><h1>Easy Countdown fixture</h1>'+fixture['email_ended']+'</body></html>',subtype='html')
path=out/'native-test.eml';path.write_bytes(message.as_bytes())
binary=os.environ['ECD_THUNDERBIRD']
version=subprocess.check_output([binary,'--version'],text=True,stderr=subprocess.STDOUT).strip()
results=[]
for allowed in (False,True):
    name='allowed' if allowed else 'blocked'
    profile=out/('tb-profile-'+name);profile.mkdir()
    prefs={'mailnews.message_display.disable_remote_image':not allowed,'mailnews.start_page.enabled':False,
           'mail.shell.checkDefaultClient':False,'app.update.auto':False,'app.update.enabled':False,
           'datareporting.healthreport.uploadEnabled':False,'datareporting.policy.dataSubmissionEnabled':False,
           'toolkit.telemetry.enabled':False,'mail.provider.enabled':False,
           'network.proxy.type':1,'network.proxy.http':'127.0.0.1','network.proxy.http_port':9,
           'network.proxy.ssl':'127.0.0.1','network.proxy.ssl_port':9,'network.proxy.no_proxies_on':'localhost,127.0.0.1'}
    (profile/'user.js').write_text(''.join('user_pref('+json.dumps(k)+','+json.dumps(v)+');\n' for k,v in prefs.items()))
    log=open(out/('thunderbird-'+name+'.log'),'w')
    process=subprocess.Popen([binary,'--no-remote','--profile',str(profile),'-file',str(path)],stdout=log,stderr=subprocess.STDOUT)
    try:
        time.sleep(12)
        screenshot=out/('thunderbird-'+name+'.png')
        subprocess.run(['scrot','-o',str(screenshot)],check=True)
        image=Image.open(screenshot).convert('RGB')
        # Synthetic end-image color: substantial exact-area presence is a rendering probe.
        green=sum(1 for r,g,b in image.getdata() if abs(r-25)<6 and abs(g-180)<6 and abs(b-90)<6)
        results.append({'mode':name,'process_running':process.poll() is None,'end_image_color_pixels':green})
    finally:
        process.terminate()
        try: process.wait(timeout=10)
        except subprocess.TimeoutExpired: process.kill();process.wait()
        log.close()
    # Profile files may contain generated identifiers; do not export them.
    import shutil
    shutil.rmtree(profile)
report={'client':version,'mode':'Native Linux Thunderbird, local .eml and loopback remote-image origin; no mail delivery or provider proxy tested.',
        'results':results,'pass':len(results)==2 and all(x['process_running'] for x in results) and results[0]['end_image_color_pixels']<100 and results[1]['end_image_color_pixels']>500}
(out/'thunderbird-results.json').write_text(json.dumps(report,indent=2)+'\n')
print(json.dumps(report,indent=2))
raise SystemExit(not report['pass'])
