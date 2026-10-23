#!/usr/bin/env python3
"""Build a deterministic, allowlisted Iceberg Custom Elements release ZIP."""
import argparse
import os
import subprocess
from pathlib import Path
import tempfile
from zipfile import ZIP_DEFLATED, ZipFile, ZipInfo

ROOT = Path(__file__).resolve().parents[1]
PLUGIN_ROOT = 'iceberg-custom-elements'
FILES = ('plugin.php', 'LICENSE', 'README.md', 'CHANGELOG.md')
DIRECTORIES = ('elements', 'includes', 'assets')
TIMESTAMP = (2020, 1, 1, 0, 0, 0)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()
    if not args.output.is_absolute():
        parser.error('--output must be an absolute path')
    output = args.output.resolve()
    if output == ROOT or ROOT in output.parents:
        parser.error('output must be outside the source tree')
    if output.suffix.lower() != '.zip':
        parser.error('output must be a .zip file')
    for directory in DIRECTORIES:
        base = ROOT / directory
        if not base.is_dir():
            parser.error(f'missing runtime directory: {directory}')
    tracked = subprocess.run(['git', 'ls-files', '-z', '--', *FILES, *DIRECTORIES],
                             cwd=ROOT, capture_output=True, check=True).stdout
    paths = [ROOT / os.fsdecode(name) for name in tracked.split(b'\0') if name]
    for required in FILES:
        if ROOT / required not in paths:
            parser.error(f'missing tracked runtime file: {required}')
    if not all(any((ROOT / directory) in path.parents for path in paths) for directory in DIRECTORIES):
        parser.error('missing tracked runtime directory')
    for path in paths:
        if not path.is_file() or path.is_symlink() or ROOT not in path.resolve().parents:
            parser.error(f'invalid runtime file: {path}')
    if b'Version: 3.0.0' not in (ROOT / 'plugin.php').read_bytes():
        parser.error('plugin.php does not declare Version: 3.0.0')
    output.parent.mkdir(parents=True, exist_ok=True)
    temporary = None
    try:
        with tempfile.NamedTemporaryFile(prefix='.iceberg-custom-elements-', suffix='.zip',
                                         dir=output.parent, delete=False) as stream:
            temporary = Path(stream.name)
        with ZipFile(temporary, mode='w', compression=ZIP_DEFLATED, compresslevel=9) as archive:
            for path in sorted(paths, key=lambda p: p.relative_to(ROOT).as_posix()):
                name = f'{PLUGIN_ROOT}/{path.relative_to(ROOT).as_posix()}'
                info = ZipInfo(name, date_time=TIMESTAMP)
                info.compress_type = ZIP_DEFLATED
                info.external_attr = 0o100644 << 16
                archive.writestr(info, path.read_bytes(), compress_type=ZIP_DEFLATED, compresslevel=9)
        os.replace(temporary, output)
        print(f'Built {output} ({len(paths)} files)')
    finally:
        if temporary is not None and temporary.exists():
            temporary.unlink()


if __name__ == '__main__':
    main()
