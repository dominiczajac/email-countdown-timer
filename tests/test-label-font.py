"""Check actual preset text against the pinned bundled font's Unicode cmap."""
import json
import pathlib
import struct
import subprocess

root = pathlib.Path(__file__).resolve().parents[1]
export = "define('ABSPATH', getcwd() . '/'); function __($text, $domain) { return $text; } require $argv[1]; echo json_encode(Email_Countdown_Timer_Admin::label_presets());"
presets = json.loads(subprocess.check_output(['php', '-r', export, str(root / 'includes/class-email-countdown-timer-admin.php')], cwd=root))
font = (root / 'fonts/easy-countdown-lato-regular.ttf').read_bytes()

def u16(offset):
    return struct.unpack_from('>H', font, offset)[0]

tables = {}
for index in range(u16(4)):
    tag, _, offset, length = struct.unpack_from('>4sIII', font, 12 + 16 * index)
    assert offset + length <= len(font)
    tables[tag] = offset
cmap = tables[b'cmap']
unicode_bmp = []
for index in range(u16(cmap + 2)):
    platform, encoding, offset = struct.unpack_from('>HHI', font, cmap + 4 + 8 * index)
    table = cmap + offset
    if (platform == 0 or (platform == 3 and encoding == 1)) and u16(table) == 4:
        unicode_bmp.append(table)
assert unicode_bmp, 'The pinned Lato font must expose its Unicode BMP cmap'

def glyph(codepoint, table):
    if codepoint > 0xFFFF:
        return 0
    count = u16(table + 6) // 2
    ends = table + 14
    starts = ends + 2 * count + 2
    deltas = starts + 2 * count
    ranges = deltas + 2 * count
    for index in range(count):
        start, end = u16(starts + 2 * index), u16(ends + 2 * index)
        if start <= codepoint <= end:
            delta, offset = u16(deltas + 2 * index), u16(ranges + 2 * index)
            if offset == 0:
                return (codepoint + delta) & 0xFFFF
            value = u16(ranges + 2 * index + offset + 2 * (codepoint - start))
            return (value + delta) & 0xFFFF if value else 0
    return 0

assert not any(glyph(point, table) for point in (0xFFFF, 0x10FFFF) for table in unicode_bmp), 'Negative glyph controls'
characters = {character for preset in presets.values() for label in preset['labels'].values() for character in label}
missing = [f'U+{ord(character):04X}' for character in characters if not any(glyph(ord(character), table) for table in unicode_bmp)]
assert not missing, f'Preset characters missing from bundled Lato: {missing}'
print(f'LABEL FONT PASS: {len(presets)} presets, {len(characters)} distinct characters, real cmap and negative control')
