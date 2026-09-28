#!/usr/bin/env python3
"""Fail closed on PCP findings, malformed output, stderr, or tool failure."""
import json
import pathlib
import sys


def check_report(report: str, stderr: str, exit_code: str) -> int:
    if exit_code.strip() != '0' or stderr.strip():
        raise ValueError('Plugin Check execution failed or produced diagnostics on stderr.')
    text = report.strip()
    if text == 'Success: Checks complete. No errors found.':
        return 0
    rows = json.loads(text)  # strict-json format, not per-file JSON fragments.
    if not isinstance(rows, list):
        raise ValueError('Expected a Plugin Check result list.')
    for row in rows:
        if not isinstance(row, dict) or row.get('type') not in ('ERROR', 'WARNING'):
            raise ValueError('Unknown or malformed Plugin Check finding.')
        if not isinstance(row.get('code'), str) or not isinstance(row.get('message'), str):
            raise ValueError('Missing Plugin Check finding code/message.')
    if rows:
        counts = {kind: sum(row['type'] == kind for row in rows) for kind in ('ERROR', 'WARNING')}
        raise ValueError(f'Plugin Check has unresolved findings: {counts}')
    return 0


if __name__ == '__main__':
    try:
        if len(sys.argv) != 2:
            raise ValueError('Usage: check-pcp-report.py EVIDENCE_DIRECTORY')
        directory = pathlib.Path(sys.argv[1])
        check_report((directory/'plugin-check.json').read_text(),
                     (directory/'plugin-check.stderr').read_text(),
                     (directory/'exit-code.txt').read_text())
    except (ValueError, OSError) as exc:
        print(f'PLUGIN CHECK GATE FAILED: {exc}', file=sys.stderr)
        sys.exit(1)
    print('PLUGIN CHECK GATE PASS: no reported errors or warnings.')
