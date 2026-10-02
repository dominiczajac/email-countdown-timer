"""Native Thunderbird .eml rendering, no mailbox/login or outgoing email."""
import email.policy
from email.message import EmailMessage
import json
import os
from pathlib import Path
import shutil
import subprocess
import time
from PIL import Image, ImageChops

out = Path(os.environ['ECD_DELIVERY_EVIDENCE'])
assert os.environ.get('GITHUB_ACTIONS') == 'true' and os.environ.get('ECD_INTEGRATION_DISPOSABLE') == '1'
fixture = json.loads((out / 'fixture.json').read_text())
binary = os.environ['ECD_THUNDERBIRD']
version = subprocess.check_output([binary, '--version'], text=True, stderr=subprocess.STDOUT).strip()
report = {'client': version, 'mode': 'Native Linux Thunderbird, local .eml and loopback remote-image origin; no mail delivery or provider proxy tested.', 'results': [], 'checks': [], 'pass': False}


def check(ok, name):
    report['checks'].append({'name': name, 'pass': bool(ok)})
    if not ok:
        raise AssertionError(name)


def green_mask(image):
    mask = Image.new('L', image.size)
    mask.putdata([255 if abs(r - 25) < 6 and abs(g - 180) < 6 and abs(b - 90) < 6 else 0 for r, g, b in image.getdata()])
    return mask


def capture(name):
    screenshot = out / ('thunderbird-' + name + '.png')
    subprocess.run(['scrot', '-o', str(screenshot)], check=True)
    return Image.open(screenshot).convert('RGB')


def display(name, html, allowed, crossing=False):
    message = EmailMessage(policy=email.policy.SMTP)
    message['From'] = 'fixture@example.invalid'
    message['To'] = 'recipient@example.invalid'
    message['Subject'] = 'Easy Countdown isolated rendering test'
    message.set_content('Synthetic countdown. Read the absolute deadline in the HTML part. No message is sent.')
    message.add_alternative('<!doctype html><html><body><h1>Easy Countdown fixture</h1>' + html + '</body></html>', subtype='html')
    path = out / ('native-' + name + '.eml')
    path.write_bytes(message.as_bytes())
    profile = out / ('tb-profile-' + name)
    profile.mkdir()
    prefs = {'mailnews.message_display.disable_remote_image': not allowed, 'mailnews.start_page.enabled': False,
             'mail.shell.checkDefaultClient': False, 'app.update.auto': False, 'app.update.enabled': False,
             'datareporting.healthreport.uploadEnabled': False, 'datareporting.policy.dataSubmissionEnabled': False,
             'toolkit.telemetry.enabled': False, 'mail.provider.enabled': False,
             'network.proxy.type': 1, 'network.proxy.http': '127.0.0.1', 'network.proxy.http_port': 9,
             'network.proxy.ssl': '127.0.0.1', 'network.proxy.ssl_port': 9,
             'network.proxy.no_proxies_on': 'localhost,127.0.0.1'}
    (profile / 'user.js').write_text(''.join('user_pref(' + json.dumps(k) + ',' + json.dumps(v) + ');\n' for k, v in prefs.items()))
    log = (out / ('thunderbird-' + name + '.log')).open('w')
    process = subprocess.Popen([binary, '--no-remote', '--profile', str(profile), '-file', str(path)], stdout=log, stderr=subprocess.STDOUT)
    try:
        started = time.monotonic()
        time.sleep(12)
        first = capture(name if not crossing else 'crossing-live-1')
        check(process.poll() is None, name + ': native client remains running')
        count = green_mask(first).histogram()[255]
        if not crossing:
            check(count > 500 if allowed else count < 100, name + ': remote-image policy reflected in pixels')
            report['results'].append({'mode': name, 'process_running': True, 'end_image_color_pixels': count})
        else:
            check(count < 100, 'crossing: actual countdown precedes end image')
            time.sleep(3)
            second = capture('crossing-live-2')
            time.sleep(max(0, 38 - (time.monotonic() - started)))
            final = capture('crossing-ended')
            mask = green_mask(final)
            bbox = mask.getbbox()
            check(bbox is not None and mask.histogram()[255] > 10000, 'crossing: downloaded animation reaches selected end image')
            check(process.poll() is None, 'crossing: native client survives deadline transition')
            # The solid end canvas locates the same image area in earlier screenshots.
            # Compare only that area, not clocks, cursor, onboarding or other chrome.
            a, b = first.crop(bbox), second.crop(bbox)
            dark = sum(max(pixel) < 100 for pixel in a.getdata())
            changes = sum(max(pixel) > 20 for pixel in ImageChops.difference(a, b).getdata())
            check(dark > 100, 'crossing: live digits are visibly rendered')
            check(changes > 10, 'crossing: native client actually plays changing countdown frames')
            report['results'].append({'mode': name, 'timer_bbox': bbox, 'live_dark_pixels': dark, 'changed_timer_pixels': changes, 'end_image_color_pixels': mask.histogram()[255]})
    finally:
        process.terminate()
        try:
            process.wait(timeout=10)
        except subprocess.TimeoutExpired:
            process.kill()
            process.wait()
        log.close()
        # Generated profile identities never enter test artifacts.
        shutil.rmtree(profile)


try:
    display('blocked', fixture['email_ended'], False)
    display('allowed', fixture['email_ended'], True)
    subprocess.run(['wp', '--path=' + os.environ['ECD_DELIVERY_WP_PATH'], '--no-color', 'eval-file', str(Path(__file__).with_name('arm-crossing.php'))], check=True)
    crossing = json.loads((out / 'native-crossing.json').read_text())
    display('crossing', crossing['html'], True, True)
    report['pass'] = True
except Exception as error:
    report['error'] = type(error).__name__ + ': ' + str(error)
finally:
    (out / 'thunderbird-results.json').write_text(json.dumps(report, indent=2) + '\n')
print(json.dumps(report, indent=2))
raise SystemExit(not report['pass'])
