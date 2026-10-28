"""Exercise the release builder against disposable plugin copies."""
import pathlib
import shutil
import subprocess
import tarfile
import tempfile
import unittest
import zipfile

SOURCE = pathlib.Path(__file__).resolve().parents[1]


class PackageTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = pathlib.Path(self.temp.name)
        self.plugin = self.root / "checkwarranty"
        shutil.copytree(SOURCE, self.plugin, ignore=shutil.ignore_patterns("dist", "__pycache__"))
        if (SOURCE.parent / "package.sh").exists():
            shutil.copy(SOURCE.parent / "package.sh", self.root / "package.sh")

    def build(self):
        script = self.plugin / "tools/package.sh"
        if not script.exists():
            script = self.root / "package.sh"
        return subprocess.run(["bash", str(script)], capture_output=True, text=True)

    def test_invalid_php_aborts_build(self):
        (self.plugin / "inc/broken.class.php").write_text("<?php function broken( {\n")
        result = self.build()
        self.assertNotEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertFalse(list(self.root.rglob("*.tar.gz")))

    def test_archives_exclude_development_files_and_secrets(self):
        (self.plugin / ".env").write_text("SENTINEL_SECRET=do-not-ship\n")
        (self.plugin / "vendor").mkdir(exist_ok=True)
        (self.plugin / "vendor/private.txt").write_text("private\n")
        result = self.build()
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        archive = next(self.root.rglob("*.tar.gz"))
        with tarfile.open(archive) as package:
            tar_names = {name.rstrip("/") for name in package.getnames() if not package.getmember(name).isdir()}
        with zipfile.ZipFile(next(self.root.rglob("*.zip"))) as package:
            zip_names = {name for name in package.namelist() if not name.endswith("/")}
        self.assertEqual(tar_names, zip_names)
        self.assertIn("checkwarranty/setup.php", tar_names)
        self.assertIn("checkwarranty/locales/fr_FR.mo", tar_names)
        self.assertFalse(any(part.startswith(".") or part in {"vendor", "tests", "tools"}
                             for name in tar_names for part in pathlib.PurePosixPath(name).parts))

    def test_release_tag_must_match_plugin_version(self):
        script = self.plugin / "tools/package.sh"
        if not script.exists():
            script = self.root / "package.sh"
        result = subprocess.run(["bash", str(script), "9.9.9"], capture_output=True, text=True)
        self.assertNotEqual(result.returncode, 0)


if __name__ == "__main__":
    unittest.main()
