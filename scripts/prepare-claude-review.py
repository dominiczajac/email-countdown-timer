#!/usr/bin/env python3
"""Materialize immutable review data without checking out or executing PR code."""
import argparse
import json
import os
from pathlib import Path, PurePosixPath
import re
import shutil
import subprocess
import tempfile

MAX_FILES = 200
MAX_FILE_BYTES = 2 * 1024 * 1024
MAX_TOTAL_BYTES = 16 * 1024 * 1024


def git(root, *args):
    return subprocess.run(
        ["git", "--no-pager", "--literal-pathspecs", "-c", "core.hooksPath=/dev/null",
         "-c", "core.fsmonitor=false", *args], cwd=root, check=True,
        stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=60,
    ).stdout


def commit(value):
    if not isinstance(value, str) or not re.fullmatch(r"[0-9a-f]{40}", value):
        raise ValueError("Expected an immutable GitHub commit SHA")
    return value


def relative_path(value):
    path = PurePosixPath(value)
    if (not value or path.is_absolute() or "\\" in value
            or any(part in ("", ".", "..", ".git") for part in value.split("/"))):
        raise ValueError("Unsafe review source path")
    return path


def prepare_context(root, metadata):
    root = Path(root).resolve()
    base = commit(metadata["base"]["sha"])
    head = commit(metadata["head"]["sha"])
    for sha in (base, head):
        try:
            git(root, "cat-file", "-e", sha + "^{commit}")
        except subprocess.CalledProcessError:
            git(root, "fetch", "--no-tags", "--no-recurse-submodules", "origin", sha)
    merge_base = git(root, "merge-base", base, head).decode().strip()
    paths = git(root, "diff", "--no-ext-diff", "--no-textconv", "--no-renames",
                "--name-only", "-z", merge_base, head, "--").decode("utf-8").split("\0")
    paths = [name for name in paths if name]
    if len(paths) > MAX_FILES:
        raise ValueError("PR exceeds the review file limit; manual scope is required")
    for name in paths:
        relative_path(name)
    output = Path(tempfile.mkdtemp(prefix=".email-countdown-review-", dir=root))
    total = 0
    manifest = []
    try:
        for label, sha in (("base", merge_base), ("head", head)):
            for name in paths:
                tree = git(root, "ls-tree", "-z", sha, "--", name)
                if not tree:
                    continue
                header, actual = tree.rstrip(b"\0").split(b"\t", 1)
                mode, kind, oid = header.decode("ascii").split()
                if actual.decode("utf-8") != name:
                    raise ValueError("Unexpected tree path")
                item = {"side": label, "path": name, "mode": mode, "object": oid}
                manifest.append(item)
                # Never materialize symlinks, submodules or executable behavior.
                if kind != "blob" or mode not in ("100644", "100755"):
                    item["omitted"] = "non-regular source; inspect the patch/metadata"
                    continue
                size = int(git(root, "cat-file", "-s", oid))
                total += size
                if size > MAX_FILE_BYTES or total > MAX_TOTAL_BYTES:
                    raise ValueError("PR exceeds the review byte budget; manual scope is required")
                content = git(root, "cat-file", "blob", oid)
                if b"\0" in content:
                    item["omitted"] = "binary source; requires separate artifact inspection"
                    continue
                try:
                    content.decode("utf-8")
                except UnicodeDecodeError:
                    item["omitted"] = "non-UTF-8 source; requires separate inspection"
                    continue
                target = output / label / relative_path(name)
                target.parent.mkdir(parents=True, exist_ok=True)
                target.write_bytes(content)
        patch = git(root, "diff", "--no-ext-diff", "--no-textconv", "--no-renames",
                    "--no-color", "--unified=5", merge_base, head, "--")
        if len(patch) > MAX_TOTAL_BYTES:
            raise ValueError("PR patch exceeds the review byte budget; manual scope is required")
        (output / "changes.patch").write_bytes(patch)
        record = {
            "number": metadata["number"], "title": metadata.get("title", ""),
            "body": metadata.get("body", ""), "base_sha": base, "head_sha": head,
            "merge_base_sha": merge_base, "files": manifest,
        }
        (output / "context.json").write_text(json.dumps(record, indent=2) + "\n", encoding="utf-8")
        return output
    except Exception:
        shutil.rmtree(output)
        raise


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--repository", required=True)
    parser.add_argument("--pr", required=True, type=int)
    args = parser.parse_args()
    if not re.fullmatch(r"[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+", args.repository) or args.pr < 1:
        parser.error("Expected a repository slug and positive PR number")
    raw = subprocess.run(["gh", "api", f"repos/{args.repository}/pulls/{args.pr}"],
                         check=True, capture_output=True, timeout=30).stdout
    metadata = json.loads(raw)
    if metadata["number"] != args.pr or metadata["base"]["repo"]["full_name"].lower() != args.repository.lower():
        raise ValueError("Review metadata does not match this repository/PR")
    output = prepare_context(Path.cwd(), metadata)
    with open(os.environ["GITHUB_OUTPUT"], "a", encoding="utf-8") as handle:
        handle.write(f"context={output.name}\n")
    print(f"Prepared immutable PR #{args.pr} context for {metadata['head']['sha']}")


if __name__ == "__main__":
    main()
