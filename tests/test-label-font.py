"""Check approved static font/notice hashes and real Unicode cmap coverage."""
import hashlib
import json
import pathlib
import struct
import subprocess

root = pathlib.Path(__file__).resolve().parents[1]
export = "define('ABSPATH', getcwd() . '/'); function __($text, $domain) { return $text; } require $argv[1]; require $argv[2]; echo json_encode(['labels'=>Email_Countdown_Timer_Admin::label_presets(),'fonts'=>Email_Countdown_Timer_Fonts::bundled()]);"
catalog = json.loads(subprocess.check_output(['php', '-r', export, str(root / 'includes/class-email-countdown-timer-admin.php'), str(root / 'includes/class-email-countdown-timer-fonts.php')], cwd=root))
characters = {char for preset in catalog['labels'].values() for label in preset['labels'].values() for char in label}
characters.update('0123456789: ĄĆĘŁŃÓŚŹŻąćęłńóśźż')

def glyph_lookup(data):
    u16 = lambda at: struct.unpack_from('>H', data, at)[0]
    tables = {}
    for index in range(u16(4)):
        tag, _, offset, length = struct.unpack_from('>4sIII', data, 12 + 16 * index)
        assert offset + length <= len(data)
        tables[tag] = offset
    assert b'glyf' in tables and b'fvar' not in tables, 'Only reviewed static TrueType faces'
    cmap = tables[b'cmap']
    bmp = []
    for index in range(u16(cmap + 2)):
        platform, encoding, offset = struct.unpack_from('>HHI', data, cmap + 4 + 8 * index)
        table = cmap + offset
        if (platform == 0 or (platform == 3 and encoding == 1)) and u16(table) == 4:
            bmp.append(table)
    assert bmp, 'A Unicode BMP cmap is required'

    def glyph(point, table):
        if point > 0xFFFF:
            return 0
        count = u16(table + 6) // 2
        ends = table + 14
        starts = ends + 2 * count + 2
        deltas = starts + 2 * count
        ranges = deltas + 2 * count
        for index in range(count):
            start, end = u16(starts + 2 * index), u16(ends + 2 * index)
            if start <= point <= end:
                delta, offset = u16(deltas + 2 * index), u16(ranges + 2 * index)
                if offset == 0:
                    return (point + delta) & 0xFFFF
                value = u16(ranges + 2 * index + offset + 2 * (point - start))
                return (value + delta) & 0xFFFF if value else 0
        return 0
    return lambda point: any(glyph(point, table) for table in bmp)

for choice, face in catalog['fonts'].items():
    font = (root / 'fonts' / face['file']).read_bytes()
    notice = (root / 'fonts' / face['license']).read_bytes()
    assert hashlib.sha256(font).hexdigest() == face['sha256'], choice
    assert hashlib.sha256(notice).hexdigest() == face['license_sha256'], choice
    has_glyph = glyph_lookup(font)
    assert not has_glyph(0xFFFF) and not has_glyph(0x10FFFF), 'Negative glyph controls'
    missing = [f'U+{ord(c):04X}' for c in characters if not has_glyph(ord(c))]
    assert not missing, (choice, missing)
print(f'LABEL FONT PASS: {len(catalog["fonts"])} immutable static faces/notices; {len(catalog["labels"])} languages, full Polish alphabet and negative controls')
