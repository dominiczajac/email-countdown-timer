#!/usr/bin/env python3
"""Compare installed theme bytes with the SHA256-pinned official archive."""
import argparse
import hashlib
import json
from pathlib import Path, PurePosixPath
import stat
import zipfile

def verify(archive, directory, slug, expected):
    if hashlib.sha256(archive.read_bytes()).hexdigest() != expected:
        raise ValueError('archive_hash_mismatch')
    files = {}
    with zipfile.ZipFile(archive) as source:
        if sum(x.file_size for x in source.infolist()) > 64 * 1024 * 1024:
            raise ValueError('oversized_archive')
        for entry in source.infolist():
            path = PurePosixPath(entry.filename)
            if (path.is_absolute() or '..' in path.parts or not path.parts or path.parts[0] != slug
                    or stat.S_ISLNK(entry.external_attr >> 16)):
                raise ValueError('unsafe_archive_entry')
            if entry.is_dir(): continue
            name = str(path.relative_to(slug))
            if name in files: raise ValueError('duplicate_archive_entry')
            files[name] = hashlib.sha256(source.read(entry)).hexdigest()
    if directory.is_symlink() or not directory.is_dir(): raise ValueError('invalid_theme_directory')
    actual = {}
    for path in directory.rglob('*'):
        if path.is_symlink(): raise ValueError('linked_theme_file')
        if path.is_file(): actual[path.relative_to(directory).as_posix()] = hashlib.sha256(path.read_bytes()).hexdigest()
    if not files or actual != files: raise ValueError('installed_theme_mismatch')
    return {'slug': slug, 'archive_sha256': expected, 'file_count': len(files), 'files': files, 'passed': True}

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('archive', type=Path); parser.add_argument('directory', type=Path)
    parser.add_argument('--slug', required=True); parser.add_argument('--sha256', required=True); parser.add_argument('--report', type=Path, required=True)
    args = parser.parse_args()
    result = verify(args.archive, args.directory, args.slug, args.sha256)
    with args.report.open('x') as stream: stream.write(json.dumps(result, indent=2) + '\n')
    print('Pinned theme installed-file verification passed:', result['file_count'], 'files')
