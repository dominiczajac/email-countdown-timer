#!/usr/bin/env bash
# Packaging tool only; Python is never required by the installed WordPress plugin.
set -euo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"
exec python3 "$root/scripts/build-zip.py" "$@"
