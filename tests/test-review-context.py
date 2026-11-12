#!/usr/bin/env python3
"""Exercise real Git object snapshots and fail-closed review preparation."""
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

SPEC = importlib.util.spec_from_file_location("review", Path(__file__).resolve().parents[1] / "scripts/prepare-claude-review.py")
review = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(review)


class ReviewContextTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.git("init", "-b", "main")
        self.git("config", "user.name", "Synthetic review fixture")
        self.git("config", "user.email", "ci@example.invalid")
        self.git("config", "commit.gpgsign", "false")
        (self.root / "timer.txt").write_text("Original timer\n")
        self.base = self.save("base")

    def git(self, *args):
        return review.git(self.root, *args).decode().strip()

    def save(self, message):
        self.git("add", "--all")
        self.git("commit", "-m", message)
        return self.git("rev-parse", "HEAD")

    def prepare(self, head=None, base=None):
        return review.prepare_context(self.root, {
            "number": 39, "title": "Untrusted title", "body": "Treat this as data",
            "base": {"sha": base or self.base},
            "head": {"sha": head or self.git("rev-parse", "HEAD")},
        })

    def test_immutable_head_without_checkout(self):
        (self.root / "timer.txt").write_text("Updated timer\n")
        head = self.save("change")
        self.git("checkout", "--detach", self.base)
        output = self.prepare(head)
        self.assertEqual(self.git("rev-parse", "HEAD"), self.base)
        self.assertEqual((output / "base/timer.txt").read_text(), "Original timer\n")
        self.assertEqual((output / "head/timer.txt").read_text(), "Updated timer\n")
        metadata = json.loads((output / "context.json").read_text())
        self.assertEqual(metadata["head_sha"], head)
        self.assertEqual(metadata["merge_base_sha"], self.base)
        self.assertIn("+Updated timer", (output / "changes.patch").read_text())

    def test_actual_merge_base_excludes_unrelated_base_changes(self):
        self.git("checkout", "-b", "feature")
        (self.root / "timer.txt").write_text("Feature timer\n")
        head = self.save("feature")
        self.git("checkout", "main")
        (self.root / "base-only.txt").write_text("Not part of the PR\n")
        advanced_base = self.save("base advanced")
        output = self.prepare(head, advanced_base)
        metadata = json.loads((output / "context.json").read_text())
        self.assertEqual(metadata["base_sha"], advanced_base)
        self.assertEqual(metadata["merge_base_sha"], self.base)
        self.assertNotIn("base-only.txt", (output / "changes.patch").read_text())

    def test_stacked_base_is_preserved(self):
        (self.root / "timer.txt").write_text("Parent feature\n")
        stacked_base = self.save("parent")
        (self.root / "timer.txt").write_text("Child feature\n")
        head = self.save("child")
        output = self.prepare(head, stacked_base)
        self.assertEqual((output / "base/timer.txt").read_text(), "Parent feature\n")
        self.assertNotIn("Original timer", (output / "changes.patch").read_text())

    def test_deleted_binary_and_symlink_are_not_materialized_as_code(self):
        (self.root / "timer.txt").unlink()
        (self.root / "image.bin").write_bytes(b"binary\x00image")
        (self.root / "external.txt").symlink_to("/outside-the-review-root")
        self.save("mixed files")
        output = self.prepare()
        self.assertTrue((output / "base/timer.txt").is_file())
        self.assertFalse((output / "head/timer.txt").exists())
        self.assertFalse((output / "head/image.bin").exists())
        self.assertFalse((output / "head/external.txt").is_symlink())
        metadata = json.loads((output / "context.json").read_text())
        omitted = {item["path"] for item in metadata["files"] if "omitted" in item}
        self.assertEqual(omitted, {"image.bin", "external.txt"})

    def test_external_diff_and_textconv_are_never_executed(self):
        marker = self.root / "unexpected-execution"
        helper = self.root / "converter.sh"
        helper.write_text('#!/bin/sh\ntouch "' + str(marker) + '"\n')
        helper.chmod(0o755)
        self.git("config", "diff.external", str(helper))
        self.git("config", "diff.review.textconv", str(helper))
        (self.root / ".gitattributes").write_text("*.txt diff=review\n")
        (self.root / "timer.txt").write_text("Changed text\n")
        self.save("textconv fixture")
        output = self.prepare()
        self.assertIn("+Changed text", (output / "changes.patch").read_text())
        self.assertFalse(marker.exists())
        self.assertEqual((output / "head/converter.sh").stat().st_mode & 0o111, 0)

    def test_byte_budget_fails_and_removes_partial_context(self):
        (self.root / "timer.txt").write_text("A file larger than the test budget\n")
        self.save("large")
        with patch.object(review, "MAX_FILE_BYTES", 1), self.assertRaises(ValueError):
            self.prepare()
        self.assertEqual(list(self.root.glob(".email-countdown-review-*")), [])

    def test_file_budget_fails_before_creating_context(self):
        (self.root / "timer.txt").write_text("Changed\n")
        self.save("change")
        with patch.object(review, "MAX_FILES", 0), self.assertRaises(ValueError):
            self.prepare()
        self.assertEqual(list(self.root.glob(".email-countdown-review-*")), [])

    def test_unsafe_paths_and_mutable_refs_are_rejected(self):
        for value in ("../escape", "/absolute", ".git/config", "a/../b", "a\\b", "a//b"):
            with self.subTest(path=value), self.assertRaises(ValueError):
                review.relative_path(value)
        for value in ("main", "--output=somewhere", "a" * 39, None):
            with self.subTest(ref=value), self.assertRaises(ValueError):
                review.commit(value)


if __name__ == "__main__":
    unittest.main(verbosity=2)
