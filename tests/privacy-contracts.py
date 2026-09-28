"""Fail on newly introduced tracking/network primitives in shipped sources.
This regression tripwire complements review, not a complete security/data-flow audit.
"""
from pathlib import Path
import re
root = Path(__file__).resolve().parents[1]
php = [root / 'email-countdown-timer.php', root / 'uninstall.php', *sorted((root / 'includes').glob('*.php'))]
js = sorted((root / 'assets').glob('*.js'))
for path in php:
    code = path.read_text()
    for pattern in [r'\$_(?:COOKIE|SESSION|REQUEST)\b', r"\$_SERVER\s*\[\s*['\"](?:REMOTE_ADDR|HTTP_USER_AGENT|HTTP_REFERER|HTTP_X_FORWARDED_FOR)", r'\b(?:wp_remote_\w+|wp_safe_remote_\w+|curl_exec|fsockopen|stream_socket_client|setcookie|setrawcookie|session_start|wp_mail|eval|shell_exec|exec|system|passthru)\s*\(']:
        assert not re.search(pattern, code), f'Privacy/security contract requires explicit review: {path.name}: {pattern}'
for path in js:
    for pattern in [r'\b(?:fetch|XMLHttpRequest|WebSocket|EventSource|sendBeacon|eval)\b', r'\b(?:localStorage|sessionStorage|indexedDB)\b', r'document\s*\.\s*cookie', r'navigator\s*\.\s*(?:userAgent|geolocation|hardwareConcurrency|deviceMemory)']:
        assert not re.search(pattern, path.read_text()), f'New browser data/network API requires review: {path.name}'
print(f'PASS privacy source tripwire: {len(php)} PHP and {len(js)} JavaScript runtime files')
