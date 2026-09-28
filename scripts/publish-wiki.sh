#!/usr/bin/env bash
# Publish documentation to the separate GitHub Wiki. Default: preview only.
set -euo pipefail
mode="${1:---dry-run}"
if [[ $# -gt 1 || ( "$mode" != "--publish" && "$mode" != "--dry-run" ) ]]; then
    printf 'Usage: bash scripts/publish-wiki.sh [--dry-run|--publish]\n' >&2
    exit 2
fi
command -v git >/dev/null
command -v python3 >/dev/null
root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
source_dir="$root/docs/wiki"
[[ -f "$source_dir/Home.md" ]] || { printf 'Missing docs/wiki/Home.md\n' >&2; exit 1; }
work="$(mktemp -d)"
trap 'rm -rf -- "$work"' EXIT
remote='https://github.com/dominiczajac/email-countdown-timer.wiki.git'
if ! git clone --quiet -- "$remote" "$work/wiki"; then
    printf 'Cannot clone Wiki. Check local Git authentication and create its first page on GitHub if needed.\n' >&2
    exit 1
fi
branch="$(git -C "$work/wiki" symbolic-ref --short HEAD)"
git -C "$work/wiki" rev-parse --verify HEAD >/dev/null
python3 - "$source_dir" "$work/wiki" <<'PY'
from pathlib import Path
import re
import sys
source, target = map(Path, sys.argv[1:])
pages = sorted(source.glob('*.md'))
names = {p.name for p in pages}
for page in pages:
    if page.is_symlink() or not re.fullmatch(r'[A-Za-z0-9_-]+\.md', page.name):
        raise SystemExit('Unsafe documentation filename: ' + page.name)
    destination = target / page.name
    if destination.is_symlink():
        raise SystemExit('Refusing to overwrite a symlink: ' + page.name)
    def replace(match):
        name, anchor = match.group(1), match.group(2) or ''
        return '(' + name[:-3] + anchor + ')' if name in names else match.group(0)
    text = re.sub(r'\(([A-Za-z0-9_-]+\.md)(#[^)]*)?\)', replace, page.read_text(encoding='utf-8'))
    destination.write_text(text, encoding='utf-8')
PY
for page in "$source_dir"/*.md; do
    git -C "$work/wiki" add -- "$(basename "$page")"
done
if git -C "$work/wiki" diff --cached --quiet; then
    printf 'Wiki already matches these source pages; nothing to publish.\n'
    exit 0
fi
git -C "$work/wiki" --no-pager diff --cached --stat
git -C "$work/wiki" --no-pager diff --cached --
if [[ "$mode" == "--dry-run" ]]; then
    printf '\nPreview only: no commit or push. Re-run with --publish after reviewing.\n'
    exit 0
fi
git -C "$work/wiki" var GIT_AUTHOR_IDENT >/dev/null
git -C "$work/wiki" var GIT_COMMITTER_IDENT >/dev/null
git -C "$work/wiki" commit -m 'docs: synchronize Email Countdown Timer wiki'
# A concurrent remote update is rejected; never force-push.
git -C "$work/wiki" push origin "HEAD:refs/heads/$branch"
