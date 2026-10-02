#!/usr/bin/env python3
"""Fail closed on native failures or incomplete FPM evidence; no third-party imports."""
import json
from pathlib import Path
import re
import sys

PATTERN = re.compile(r"SIGSEGV|segmentation fault|exited on signal|PHP Fatal|Maximum execution time", re.I)

def inspect(directory):
    errors = []
    checked = []
    for name in ("fpm.log", "php-errors.log"):
        path = directory / name
        if path.is_symlink() or not path.is_file():
            errors.append({"file": name, "reason": "missing_or_nonregular_log"})
            continue
        try:
            lines = path.read_text(encoding="utf-8", errors="replace").splitlines()
        except OSError:
            errors.append({"file": name, "reason": "unreadable_log"})
            continue
        checked.append(name)
        for number, line in enumerate(lines, 1):
            if PATTERN.search(line):
                # Keep the original logs in the artifact, not raw diagnostic text here.
                errors.append({"file": name, "line": number, "reason": "native_or_php_failure"})
    return {"passed": not errors, "checked_files": checked, "errors": errors}

if __name__ == "__main__":
    if len(sys.argv) != 2:
        raise SystemExit("Usage: check-native-logs.py EVIDENCE_DIRECTORY")
    result = inspect(Path(sys.argv[1]))
    print(json.dumps(result, indent=2))
    raise SystemExit(0 if result["passed"] else 1)
