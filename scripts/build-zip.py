#!/usr/bin/env python3
"""Build the exact WordPress distribution; standard library only, no network.

Sorted entries, fixed timestamps/permissions and no machine paths. ZIP bytes are
repeatable for identical source and Python/zlib toolchains (not a signed release).
"""
from __future__ import annotations

import argparse
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import re
import sys
import tempfile
import zipfile
import zlib

SLUG = "easy-countdown"
ROOT_FILES = {"email-countdown-timer.php", "uninstall.php", "readme.txt", "LICENSE"}


def regular_file(root: Path, relative: str) -> Path:
    """Refuse symlinks in every component, including an otherwise-contained link."""
    path = root
    for part in PurePosixPath(relative).parts:
        path /= part
        if path.is_symlink():
            raise ValueError(f"Symlink is not a distribution source: {relative}")
    if not path.is_file() or not path.resolve().is_relative_to(root):
        raise ValueError(f"Missing or uncontained distribution file: {relative}")
    return path


def sources(root: Path) -> dict[str, bytes]:
    manifest = regular_file(root, "scripts/distribution-files.txt").read_text(encoding="utf-8")
    names = [line.strip() for line in manifest.splitlines() if line.strip() and not line.lstrip().startswith("#")]
    if len(names) != len(set(names)) or not ROOT_FILES.issubset(names):
        raise ValueError("Duplicate manifest entry or missing required root file")
    for name in names:
        valid = name in ROOT_FILES or name == "blocks/timer/block.json" or re.fullmatch(r"includes/[a-z0-9-]+\.php|assets/[a-z0-9-]+\.(?:css|js)", name)
        if not valid:
            raise ValueError(f"Unsupported distribution path: {name}")
    # A newly added runtime file must be deliberately added to the same manifest.
    discovered = set(ROOT_FILES)
    for directory in ("includes", "assets", "blocks"):
        base = root / directory
        if base.is_symlink() or not base.is_dir():
            raise ValueError(f"Invalid runtime directory: {directory}")
        for path in base.rglob("*"):
            if path.is_symlink():
                raise ValueError(f"Symlink in runtime directory: {path.name}")
            if path.is_file():
                discovered.add(path.relative_to(root).as_posix())
    if discovered != set(names):
        raise ValueError("Manifest/runtime mismatch: " + ", ".join(sorted(discovered ^ set(names))))
    return {name: regular_file(root, name).read_bytes() for name in sorted(names)}


def metadata(files: dict[str, bytes]) -> str:
    php = files["email-countdown-timer.php"].decode("utf-8")
    readme = files["readme.txt"].decode("utf-8")
    def one(pattern: str, text: str) -> str:
        matches = re.findall(pattern, text, re.MULTILINE)
        if len(matches) != 1:
            raise ValueError("Missing or ambiguous package metadata: " + pattern)
        return matches[0].strip()
    if one(r"^\s*\* Text Domain:\s*(\S+)", php) != SLUG:
        raise ValueError("Text Domain must match the WordPress.org submission slug")
    if one(r"^\s*\* Plugin Name:\s*(.+)$", php) != "Easy Countdown" or not readme.startswith("=== Easy Countdown ===\n"):
        raise ValueError("The distribution must use the Easy Countdown display name")
    version = one(r"^\s*\* Version:\s*(\S+)", php)
    constant = one(r"define\(\s*'EMAIL_COUNTDOWN_TIMER_VERSION',\s*'([^']+)'\s*\)", php)
    stable = one(r"^Stable tag:\s*(\S+)", readme)
    if not re.fullmatch(r"\d+\.\d+\.\d+", version) or version != constant or version != stable:
        raise ValueError("Plugin header, version constant and Stable tag must match")
    for field in ("Requires PHP", "Requires at least", "License", "License URI"):
        if one(r"^\s*\* " + field + r":\s*(.+)$", php) != one(r"^" + field + r":\s*(.+)$", readme):
            raise ValueError("Inconsistent metadata: " + field)
    if len(readme.encode("utf-8")) > 10000:
        raise ValueError("Keep the user-facing readme below 10,000 bytes")
    block = json.loads(files["blocks/timer/block.json"])
    if block.get("name") != "easy-countdown/timer" or block.get("version") != version or block.get("textdomain") != SLUG:
        raise ValueError("Block metadata must match the plugin")
    return version


def destination(root: Path, value: Path, suffix: str) -> Path:
    if value.is_symlink() or value.suffix != suffix:
        raise ValueError("Invalid output path: " + str(value))
    value = value.resolve()
    if value.is_relative_to(root) and not value.is_relative_to(root / "dist"):
        raise ValueError("Outputs inside the source tree must be in dist/")
    value.parent.mkdir(parents=True, exist_ok=True)
    return value


def build(root: Path, output: Path, report: Path | None = None) -> dict:
    root = root.resolve(strict=True)
    files = sources(root)
    version = metadata(files)
    output = destination(root, output, ".zip")
    report = destination(root, report, ".json") if report else None
    fd, temporary = tempfile.mkstemp(prefix=".ect-build-", suffix=".zip", dir=output.parent)
    os.close(fd)
    try:
        with zipfile.ZipFile(temporary, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
            for name, content in files.items():
                entry = zipfile.ZipInfo(f"{SLUG}/{name}", date_time=(1980, 1, 1, 0, 0, 0))
                entry.create_system = 3
                entry.external_attr = 0o100644 << 16
                archive.writestr(entry, content, compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
        with zipfile.ZipFile(temporary) as archive:
            if archive.testzip() is not None or len(archive.namelist()) != len(files):
                raise ValueError("Built ZIP failed integrity verification")
            for name, content in files.items():
                if archive.read(f"{SLUG}/{name}") != content:
                    raise ValueError("Built ZIP does not match source bytes")
        data = Path(temporary).read_bytes()
        result = {"version": version, "slug": SLUG, "file_count": len(files),
                  "zip_bytes": len(data), "zip_sha256": hashlib.sha256(data).hexdigest(),
                  "python": sys.version.split()[0], "zlib": zlib.ZLIB_RUNTIME_VERSION,
                  "files": {name: hashlib.sha256(content).hexdigest() for name, content in files.items()}}
        os.replace(temporary, output)
        if report:
            report.write_text(json.dumps(result, indent=2) + "\n", encoding="utf-8")
        return result
    finally:
        if os.path.exists(temporary):
            os.unlink(temporary)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--source", type=Path, default=Path(__file__).resolve().parents[1])
    parser.add_argument("--output", type=Path, required=True)
    parser.add_argument("--report", type=Path)
    args = parser.parse_args()
    try:
        result = build(args.source, args.output, args.report)
    except (OSError, ValueError, UnicodeError, zipfile.BadZipFile) as error:
        print(f"Build refused: {error}", file=sys.stderr)
        return 1
    print(f"Built {SLUG} {result['version']}: {result['file_count']} files, SHA256 {result['zip_sha256']}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
