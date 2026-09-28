"""Positive and adversarial distribution checks; no WordPress/network dependency."""
import importlib.util
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import unittest
import zipfile

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location("ect_build", ROOT / "scripts/build-zip.py")
builder = importlib.util.module_from_spec(spec)
spec.loader.exec_module(builder)


class BuildTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.base = Path(self.temp.name)
        self.root = self.base / "source"
        self.root.mkdir()
        for name in builder.ROOT_FILES | {"scripts/distribution-files.txt"}:
            target = self.root / name
            target.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(ROOT / name, target)
        for folder in ("includes", "assets"):
            shutil.copytree(ROOT / folder, self.root / folder)
        self.output = self.base / "plugin.zip"

    def tearDown(self):
        self.temp.cleanup()

    def build(self):
        return builder.build(self.root, self.output)

    def test_repeatable_complete_archive(self):
        report = self.build()
        before = self.output.read_bytes()
        os.utime(self.root / "readme.txt", (1800000000, 1800000000))
        (self.root / "readme.txt").chmod(0o600)
        self.assertEqual(report["zip_sha256"], self.build()["zip_sha256"])
        self.assertEqual(before, self.output.read_bytes())
        with zipfile.ZipFile(self.output) as archive:
            self.assertIsNone(archive.testzip())
            self.assertEqual(len(archive.namelist()), report["file_count"])
            for entry in archive.infolist():
                self.assertEqual(entry.date_time, (1980, 1, 1, 0, 0, 0))
                self.assertEqual(entry.external_attr >> 16, 0o100644)
            installed = self.base / "installed"
            archive.extractall(installed)
        report_path = self.base / "report.json"
        report_path.write_text(json.dumps(report))
        command = [sys.executable, str(ROOT / "scripts/verify-distribution.py"), str(report_path), str(installed / builder.SLUG)]
        self.assertEqual(subprocess.run(command, capture_output=True).returncode, 0)
        (installed / builder.SLUG / "uninstall.php").write_text("modified")
        self.assertNotEqual(subprocess.run(command, capture_output=True).returncode, 0)

    def test_development_and_fonts_are_not_bundled(self):
        (self.root / "fonts").mkdir()
        (self.root / "fonts/manual.ttf").write_text("synthetic-not-a-font")
        (self.root / "wp-config.php").write_text("synthetic-not-a-secret")
        self.build()
        with zipfile.ZipFile(self.output) as archive:
            self.assertFalse(any("fonts/" in p or "wp-config" in p or "scripts/" in p for p in archive.namelist()))

    def test_missing_runtime_file(self):
        (self.root / "uninstall.php").unlink()
        with self.assertRaises(ValueError): self.build()

    def test_unlisted_runtime_file(self):
        (self.root / "includes/forgotten.php").write_text("<?php")
        with self.assertRaises(ValueError): self.build()

    def test_symlink_file(self):
        p = self.root / "assets/admin.js"
        p.unlink()
        p.symlink_to(ROOT / "assets/admin.js")
        with self.assertRaises(ValueError): self.build()

    def test_symlink_directory(self):
        shutil.rmtree(self.root / "assets")
        (self.root / "assets").symlink_to(ROOT / "assets", target_is_directory=True)
        with self.assertRaises(ValueError): self.build()

    def test_unsafe_or_duplicate_manifest(self):
        p = self.root / "scripts/distribution-files.txt"
        initial = p.read_text()
        for invalid in ("../outside.php", "/etc/passwd", "fonts/example.ttf", "readme.txt"):
            with self.subTest(invalid=invalid):
                p.write_text(initial + invalid + "\n")
                with self.assertRaises(ValueError): self.build()
        p.write_text(initial)

    def test_version_or_license_drift(self):
        p = self.root / "readme.txt"
        initial = p.read_text()
        for old, new in (("Stable tag:", "Wrong tag:"), ("GPL-3.0-only", "MIT"), ("Requires PHP: 8.1", "Requires PHP: 8.0")):
            with self.subTest(old=old):
                p.write_text(initial.replace(old, new))
                with self.assertRaises(ValueError): self.build()
        p.write_text(initial)

    def test_output_cannot_replace_source(self):
        with self.assertRaises(ValueError): builder.build(self.root, self.root / "includes/build.zip")
        with self.assertRaises(ValueError): builder.build(self.root, self.root / "readme.txt")

    def test_failed_build_preserves_existing_output(self):
        self.build()
        before = self.output.read_bytes()
        (self.root / "assets/admin.js").unlink()
        with self.assertRaises(ValueError): self.build()
        self.assertEqual(before, self.output.read_bytes())


if __name__ == "__main__":
    unittest.main(verbosity=2)
