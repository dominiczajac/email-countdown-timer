"""Parse test-fixture CLI output, retaining third-party trailing diagnostics verbatim.

Never used on HTTP response bytes. A CLI logger may append diagnostics during
shutdown even with WP_DEBUG_DISPLAY false; those are evidence, not valid JSON.
"""
import json

def read_cli_json(path):
    raw = path.read_text()
    value, end = json.JSONDecoder().raw_decode(raw.lstrip())
    trailing = raw.lstrip()[end:]
    if trailing.strip():
        path.with_suffix('.extra.txt').write_text(trailing)
    return value
