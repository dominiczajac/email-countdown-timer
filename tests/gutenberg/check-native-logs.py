#!/usr/bin/env python3
"""Fail closed on incomplete synthetic browser/native evidence; standard library only.

Reports contain classifications/line numbers, not raw logs, request arguments,
cookies or dumps. The log-only interface remains compatible with existing tests.
"""
from __future__ import annotations

import argparse
import json
import os
from pathlib import Path
import re
import stat

MAX_BYTES = 16 * 1024 * 1024
PATTERN = re.compile(r'SIGSEGV|segmentation fault|exited on signal|(?:PHP\s+)?Fatal error|Maximum execution time', re.I)


def read_regular(path: Path) -> str:
    """Bound reads, refusing links, devices, FIFOs and invalid UTF-8."""
    fd = os.open(path, os.O_RDONLY | os.O_NONBLOCK | os.O_NOFOLLOW)
    with os.fdopen(fd, 'rb') as stream:
        info = os.fstat(stream.fileno())
        if not stat.S_ISREG(info.st_mode) or info.st_size > MAX_BYTES:
            raise ValueError('not a bounded regular file')
        data = stream.read(MAX_BYTES + 1)
        if len(data) > MAX_BYTES:
            raise ValueError('evidence grew beyond the limit')
    return data.decode('utf-8-sig')


def inspect(directory: Path) -> dict:
    """Keep both pre-created logs mandatory; inspect optional nginx independently."""
    errors = []
    checked = []
    if directory.is_symlink() or not directory.is_dir():
        return {'passed': False, 'checked_files': [], 'errors': [{'file': '.', 'reason': 'invalid_evidence_directory'}]}
    for name, required in [('fpm.log', True), ('php-errors.log', True), ('nginx-error.log', False)]:
        path = directory / name
        try:
            if path.is_symlink():
                raise ValueError('symlink')
            text = read_regular(path)
        except FileNotFoundError:
            if required:
                errors.append({'file': name, 'reason': 'missing_required_log'})
            continue
        except (OSError, ValueError, UnicodeError) as error:
            errors.append({'file': name, 'reason': type(error).__name__})
            continue
        checked.append(name)
        if name == 'fpm.log' and not text.strip():
            errors.append({'file': name, 'reason': 'empty_required_log'})
        for number, line in enumerate(text.splitlines(), 1):
            if PATTERN.search(line):
                errors.append({'file': name, 'line': number, 'reason': 'native_or_php_failure'})
    return {'passed': not errors, 'checked_files': checked, 'errors': errors}


def assess(directory: Path) -> dict:
    """Combine log acceptance with an explicit complete, nonempty browser report."""
    result = inspect(directory)
    if directory.is_symlink() or not directory.is_dir():
        return result
    try:
        browser = json.loads(read_regular(directory / 'browser.json'))
        checks = browser.get('checks') if isinstance(browser, dict) else None
        complete = (isinstance(browser, dict) and browser.get('complete') is True
                    and isinstance(checks, list) and bool(checks)
                    and all(isinstance(check, str) and check for check in checks)
                    and type(browser.get('count')) is int and browser['count'] == len(checks)
                    and browser.get('page_errors') == [])
        if not complete:
            result['errors'].append({'file': 'browser.json', 'reason': 'incomplete_or_failed_browser'})
    except (OSError, ValueError, UnicodeError) as error:
        result['errors'].append({'file': 'browser.json', 'reason': type(error).__name__})
    result['passed'] = not result['errors']
    return result


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('evidence', type=Path)
    parser.add_argument('--require-browser', action='store_true')
    parser.add_argument('--report', type=Path)
    args = parser.parse_args()
    result = assess(args.evidence) if args.require_browser else inspect(args.evidence)
    encoded = json.dumps(result, indent=2) + '\n'
    if args.report:
        # Never overwrite evidence or follow an existing destination link.
        with args.report.open('x', encoding='utf-8') as stream:
            stream.write(encoded)
    print(encoded, end='')
    return 0 if result['passed'] else 1


if __name__ == '__main__':
    raise SystemExit(main())
