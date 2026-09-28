#!/usr/bin/env python3
"""Check the installed plugin against the build report before tests run."""
import hashlib
import json
from pathlib import Path
import sys

report = json.loads(Path(sys.argv[1]).read_text(encoding="utf-8"))
root = Path(sys.argv[2])
if root.is_symlink() or not root.is_dir():
    raise SystemExit("Invalid installed distribution directory")
actual = {}
for path in root.rglob("*"):
    if path.is_symlink():
        raise SystemExit("Unexpected symlink in installed package")
    if path.is_file():
        actual[path.relative_to(root).as_posix()] = hashlib.sha256(path.read_bytes()).hexdigest()
if actual != report["files"] or len(actual) != report["file_count"]:
    raise SystemExit("Installed distribution differs from built ZIP")
print(f"PASS installed package: {len(actual)} files match build hashes")
