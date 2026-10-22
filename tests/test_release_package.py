"""Behavioral tests for deterministic, allowlisted production ZIP."""
import hashlib
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
from zipfile import ZipFile

ROOT = Path(__file__).resolve().parents[1]
BUILDER = ROOT / 'scripts' / 'build_release.py'


class ReleasePackageTests(unittest.TestCase):
    def run_builder(self, output):
        return subprocess.run([sys.executable, str(BUILDER), '--output', str(output)], cwd=ROOT,
                              capture_output=True, text=True)

    def test_archive_contains_exact_plugin_root_and_runtime_assets(self):
        with tempfile.TemporaryDirectory() as tmp:
            target = Path(tmp) / 'iceberg-custom-elements.zip'
            result = self.run_builder(target)
            self.assertEqual(0, result.returncode, result.stderr)
            with ZipFile(target) as archive:
                names = archive.namelist()
                self.assertIn('iceberg-custom-elements/plugin.php', names)
                self.assertTrue(all(p.startswith('iceberg-custom-elements/') for p in names))
                self.assertEqual(sorted(names), names)
                self.assertIn('iceberg-custom-elements/elements/Wrapper_Div/element.php', names)
                self.assertIn('iceberg-custom-elements/elements/Inner_Section/element.php', names)
                self.assertIn('iceberg-custom-elements/includes/breakdance_enhanced_menu.php', names)
                self.assertIn('iceberg-custom-elements/includes/UpdateService.php', names)
                self.assertIn('iceberg-custom-elements/assets/js/iceberg-menu-click-handler.js', names)
                self.assertIn('iceberg-custom-elements/LICENSE', names)
                self.assertIn('iceberg-custom-elements/README.md', names)
                self.assertIn('iceberg-custom-elements/CHANGELOG.md', names)
                self.assertIn(b'Version: 3.0.0', archive.read('iceberg-custom-elements/plugin.php'))
                self.assertFalse(any('/.git/' in p or '/docs/' in p or '/tests/' in p or '/scripts/' in p for p in names))

    def test_repeated_builds_are_byte_identical(self):
        with tempfile.TemporaryDirectory() as tmp:
            a, b = Path(tmp) / 'a.zip', Path(tmp) / 'b.zip'
            for path in (a, b):
                result = self.run_builder(path)
                self.assertEqual(0, result.returncode, result.stderr)
            self.assertEqual(hashlib.sha256(a.read_bytes()).digest(), hashlib.sha256(b.read_bytes()).digest())

    def test_output_inside_source_tree_is_rejected_without_modifying_source(self):
        source = ROOT / 'plugin.php'
        digest = hashlib.sha256(source.read_bytes()).digest()
        result = self.run_builder(source)
        self.assertNotEqual(0, result.returncode)
        self.assertEqual(digest, hashlib.sha256(source.read_bytes()).digest())


if __name__ == '__main__':
    unittest.main()
