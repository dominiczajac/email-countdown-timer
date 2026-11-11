#!/usr/bin/env python3
"""Post-shutdown OLS/LSAPI evidence, separate from the unchanged FPM gate."""
import argparse
import importlib.util
import json
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('fpm_log_reader', ROOT / 'tests/gutenberg/check-native-logs.py')
reader = importlib.util.module_from_spec(spec)
spec.loader.exec_module(reader)
BAD = re.compile(r'\[(?:ERROR|ALERT|CRIT|FATAL)\]|SIG(?:ILL|TRAP|ABRT|BUS|FPE|KILL|SEGV|XCPU|XFSZ|SYS)\b|'
                 r'segmentation fault|bus error|floating point exception|illegal instruction|bad system call|core dumped|'
                 r'(?:PHP\s+)?Fatal error|Maximum execution time|Unable to load dynamic library|'
                 r'signal\s*[:=]?\s*(?:4|5|6|7|8|9|11|24|25|31)\b|timed out|Error Timeout|Not support \[|'
                 r'(?:exceed|reach).*(?:MAX_PROCESS_TIME|processing time|CPU limit)', re.I)
FILES = ('ols-error.log', 'php-errors.log', 'lsapi-stderr.log', 'server-container.log')


def inspect(directory):
    errors, checked = [], []
    if directory.is_symlink() or not directory.is_dir():
        return {'passed': False, 'checked_files': [], 'errors': [{'file': '.', 'reason': 'invalid_directory'}]}
    for name in FILES:
        try:
            text = reader.read_regular(directory / name)
            checked.append(name)
            if name == 'ols-error.log' and 'LiteSpeed/1.9.4 Open starts successfully!' not in text:
                errors.append({'file': name, 'reason': 'missing_startup_evidence'})
            if name == 'ols-error.log' and not re.search(r'httpdWorkers:\s*1\b', text):
                errors.append({'file': name, 'reason': 'wrong_server_worker_count'})
            if name == 'server-container.log' and not text.strip():
                errors.append({'file': name, 'reason': 'empty_container_log'})
            for number, line in enumerate(text.splitlines(), 1):
                if BAD.search(line):
                    errors.append({'file': name, 'line': number, 'reason': 'native_or_server_failure'})
        except (OSError, ValueError, UnicodeError) as error:
            errors.append({'file': name, 'reason': type(error).__name__})
    try:
        state = json.loads(reader.read_regular(directory / 'server-stop.json'))
        if (not isinstance(state, dict) or state.get('Running') is not False or state.get('OOMKilled') is not False
                or type(state.get('ExitCode')) is not int or state['ExitCode'] != 0):
            errors.append({'file': 'server-stop.json', 'reason': 'unclean_server_stop'})
        checked.append('server-stop.json')
    except (OSError, ValueError, UnicodeError) as error:
        errors.append({'file': 'server-stop.json', 'reason': type(error).__name__})
    return {'passed': not errors, 'checked_files': checked, 'errors': errors}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('directory', type=Path)
    parser.add_argument('--report', type=Path)
    args = parser.parse_args()
    result = inspect(args.directory)
    text = json.dumps(result) + '\n'
    if args.report:
        with args.report.open('x') as stream:
            stream.write(text)
    print(text, end='')
    return 0 if result['passed'] else 1


if __name__ == '__main__':
    raise SystemExit(main())
