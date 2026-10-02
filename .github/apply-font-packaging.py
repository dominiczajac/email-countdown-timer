from pathlib import Path
import hashlib
import subprocess
root=Path(__file__).resolve().parents[1]
changed=[]
F='easy-countdown-gudea-regular.ttf'
H='a4b5410090a821ea17021627312babdf3d8db0271217fd8c4d9f629ef0a2d90b'
L='315a576cbc7ab61c9e347b5725893bc8498fdcb8fc10831793c6864bc2cefba8'
assert 'Version: 12.5.2' in (root/'email-countdown-timer.php').read_text()
assert hashlib.sha256((root/'fonts/Gudea-Regular.ttf').read_bytes()).hexdigest()==H
assert hashlib.sha256((root/'fonts/OFL.txt').read_bytes()).hexdigest()==L
assert not (root/'fonts'/F).exists()
(root/'fonts/Gudea-Regular.ttf').rename(root/'fonts'/F)
changed.extend(['fonts/Gudea-Regular.ttf','fonts/'+F])
def change(name,func):
 p=root/name;s=p.read_text();t=func(s)
 assert s!=t,'No change: '+name
 p.write_text(t);changed.append(name)
def new(name,s):
 p=root/name;assert not p.exists(),name
 p.parent.mkdir(parents=True,exist_ok=True);p.write_text(s);changed.append(name)
change('scripts/build-zip.py',lambda s:s.replace('ROOT_FILES =',f'''# Exactly one reviewed font; never package arbitrary site-managed font files.
FONT_HASHES = {{
    "fonts/{F}": "{H}",
    "fonts/OFL.txt": "{L}",
}}
FONT_FILES = set(FONT_HASHES) | {{"fonts/README.txt"}}
ROOT_FILES =''').replace('or not ROOT_FILES.issubset(names):','or not (ROOT_FILES | FONT_FILES).issubset(names):').replace('valid = name in ROOT_FILES or re.fullmatch','valid = name in ROOT_FILES | FONT_FILES or re.fullmatch').replace('discovered = set(ROOT_FILES)','discovered = ROOT_FILES | FONT_FILES').replace('    return {name: regular_file(root, name).read_bytes() for name in sorted(names)}','''    files = {name: regular_file(root, name).read_bytes() for name in sorted(names)}
    for name, expected in FONT_HASHES.items():
        if hashlib.sha256(files[name]).hexdigest() != expected:
            raise ValueError("Bundled font or original license changed: " + name)
    return files'''))
change('scripts/distribution-files.txt',lambda s:s.replace('# Explicit shipped sources. Font binaries and development files are never bundled.','# Explicit shipped sources. Only the reviewed example font and its notices are bundled.').replace('LICENSE\n',f'LICENSE\nfonts/{F}\nfonts/OFL.txt\nfonts/README.txt\n',1))
change('includes/class-email-countdown-timer-fonts.php',lambda s:s.replace('    private const MAX_BYTES',f"    public const BUNDLED_FILE = '{F}';\n    private const BUNDLED_SHA256 = '{H}';\n    private const MAX_BYTES",1).replace("                $hash = hash_file( 'sha256', $file );","""                $hash = hash_file( 'sha256', $file );
                // An unchanged shipped font is restored by updates, not user migration data.
                if ( self::BUNDLED_FILE === $name && self::BUNDLED_SHA256 === $hash ) {
                    continue;
                }""",1))
change('includes/admin-view.php',lambda s:s.replace('echo esc_html( $email_countdown_timer_file );',"echo esc_html( Email_Countdown_Timer_Fonts::BUNDLED_FILE === $email_countdown_timer_file ? __( 'Gudea Regular (included)', 'easy-countdown' ) : $email_countdown_timer_file );").replace('Use Data Settings to copy legacy fonts into persistent local storage. Legacy /fonts/ remains readable. Bitmap fallback has fixed sizes and printable ASCII labels only; use a trusted TTF/OTF for other characters.','Gudea Regular is included locally; select it and save to use scalable text. FreeType is required. Keep your own licensed TTF/OTF files in the persistent directory shown in Data Settings. Bitmap fallback has fixed sizes and printable ASCII labels only.'))
new('fonts/README.txt',f'''Easy Countdown: one included example font

Select "Gudea Regular (included)" under Create/Edit Timer > Appearance >
Typography and Size > Font, then save. The bitmap remains the default;
existing campaigns are not changed. Local GD/FreeType support is required.

Font: Gudea Regular, by Agustina Mingote.
License: SIL Open Font License 1.1. Read the original OFL.txt beside this file.
Commercial use and bundling with software are permitted subject to that license;
do not sell the font by itself. The font remains OFL, not the plugin's GPL.
Its font data and internal names are unmodified. Only the filename is prefixed
to avoid collisions with custom files named Gudea-Regular.ttf.

Public upstream: https://github.com/google/fonts/tree/9710da1eacb3be272583c3224dcb70f9da6eadbb/ofl/gudea
Original filename: Gudea-Regular.ttf
Installed filename: {F}
Upstream Git blob: a15eab8ab0950f43959f8f45d325f25ad743f9fb
Font SHA256: {H}
OFL.txt SHA256: {L}

The font is part of every installation ZIP; WordPress never downloads it.
It is read locally when drawing an image, not sent as a web font to recipients.
This directory belongs to the plugin and is replaced during updates. Store YOUR
additional fonts in the persistent directory displayed in Data Settings,
normally wp-content/uploads/email-countdown-timer/fonts/ (per-site on multisite).
Do not replace the included file or its license. Unchanged bundled files do not
need the legacy-copy tool. Existing manually supplied filenames keep resolving
as before; adding this example does not migrate or overwrite those files.
''')
change('email-countdown-timer.php',lambda s:s.replace('12.5.2','12.5.3'))
change('CHANGELOG.md',lambda s:s.replace('# Changelog\n','# Changelog\n\n## 12.5.3\n\n- Include one unmodified, locally available Gudea Regular font under SIL OFL 1.1, with its original license and pinned source/hash notice. Bitmap and existing campaign choices are preserved.\n- Package only the explicitly allowlisted font; reject a modified font/license and keep arbitrary user fonts out of distribution. No runtime font download or discovery.\n- Keep unchanged bundled fonts out of user-font migration; custom font storage remains persistent.\n',1))
change('README.md',lambda s:s.replace('**Source version:** 12.5.2','**Source version:** 12.5.3').replace('There are no bundled runtime Composer/npm dependencies or font binaries.','There are no bundled runtime Composer/npm dependencies. One example font, Gudea Regular, is included under SIL OFL 1.1 with its original license.').replace('**Automatic Google Fonts import is deferred and not implemented.**','**Gudea Regular is included locally:** choose **Gudea Regular (included)** in **Appearance > Typography and Size > Font**, then save. Bitmap and existing selections are unchanged. The original OFL license permits bundling with software, including commercial software; font rights remain under OFL, separate from the plugin GPL. Source and integrity details are in [`fonts/README.txt`](fonts/README.txt). FreeType is required. Keep your own fonts in persistent storage, not the replaceable plugin directory.\n\n**Automatic Google Fonts import is deferred and not implemented.**').replace('Fonts, tests, CI and development docs are not bundled.','Only the allowlisted example font and its notices are bundled; user fonts, tests, CI and development docs are not.'))
def readme(s):
 s=s.replace('Stable tag: 12.5.2','Stable tag: 12.5.3').replace('* Custom colors, labels, sizes and local TTF/OTF fonts.','* Custom colors, labels, sizes, an included Gudea Regular font and local TTF/OTF files.')
 s=s.replace('Upload trusted, licensed static TTF/OTF files through SFTP to the directory in Data Settings, normally wp-content/uploads/email-countdown-timer/fonts/. Multisite uses separate site-ID directories. No fonts or Google Fonts importer are included. Keep license notices and confirm server-rendering rights.','Select Gudea Regular (included) in Appearance > Typography and Size > Font, then save. Its SIL OFL 1.1 license permits commercial software bundling; original license/source notices are in fonts/. Bitmap remains the default. FreeType is required. Put your own licensed TTF/OTF files in the persistent directory shown in Data Settings, not this replaceable plugin folder. No Google importer is included.')
 s=s.replace('Recorded isolated profiles include WP Super Cache, Autoptimize, Yoast SEO, Contact Form 7, Elementor, Rank Math, Query Monitor, Limit Login Attempts Reloaded, WooCommerce and W3 Total Cache. Successful timer checks are not universal certification: intermittent admin/native failures remain under investigation, including events with Easy Countdown inactive.','Recorded profiles include WP Super Cache, Autoptimize, Yoast, Contact Form 7, Elementor, Rank Math, Query Monitor, Limit Login Attempts Reloaded, WooCommerce and W3 Total Cache. This is not universal certification: intermittent admin/native failures remain under investigation, including with Easy Countdown inactive.')
 s=s.replace('== Changelog ==\n','== Changelog ==\n\n= 12.5.3 =\n* Add one local Gudea Regular font under SIL OFL 1.1. No download or automatic font change.\n',1)
 s=s[:s.index('== Upgrade Notice ==')]+'== Upgrade Notice ==\n\n= 12.5.3 =\nGudea Regular is included as an optional choice. Existing fonts and bitmap defaults remain.\n'
 s=s.replace('Keep personal data, recipient identifiers and secrets out of public fields, images and URLs.','Keep personal data and secrets out of public content and URLs.')
 assert len(s.encode())<=10000,len(s.encode())
 return s
change('readme.txt',readme)
repls={
'CONTRIBUTING.md':[('Never add font binaries or site data to runtime directories.','Only the reviewed Gudea example and its original OFL notice may be bundled; do not add other font binaries or site data without authorization, licensing review and an explicit manifest/hash update.')],
'docs/DISTRIBUTION-BUILD.md':[('It never includes font binaries, uploads, repository history, development documentation, tests or CI files.','It includes exactly the allowlisted Gudea Regular font and original OFL notice, verified by SHA256, plus its README. Other fonts, uploads, repository history, development documentation, tests and CI files are excluded. No build-time or runtime download is required.')],
'docs/LOCAL-FONT-STORAGE.md':[('No font binaries are distributed with the plugin.','One unmodified OFL Gudea Regular example is distributed in the plugin fonts/ directory. It is optional and restored by updates; its exact unchanged copy is skipped by the legacy-copy tool. Additional user fonts still belong in persistent storage.')],
'docs/wiki/Configuration.md':[('Font files are not bundled with the plugin.','One Gudea Regular font is bundled under SIL OFL 1.1. Select Gudea Regular (included) in Typography and Size; bitmap remains the default.')],
'docs/wiki/Installation.md':[('No font binaries or Google importer are bundled.','One optional Gudea Regular example is bundled under SIL OFL 1.1; no Google importer is implemented.')],
'docs/wiki/Privacy-and-Local-Fonts.md':[('No browser upload endpoint or font binaries are provided.','No browser upload endpoint is provided. One optional Gudea Regular example is bundled under SIL OFL 1.1 and used locally, without remote font requests.')],
'docs/DEPENDENCIES-AND-COMPATIBILITY.md':[('| Drawing |','| Included font | Unmodified Gudea Regular, SIL OFL 1.1 | Explicit file/hash/license allowlist; no runtime download, discovery or change to existing selections. See fonts/README.txt. |\n| Drawing |')],
}
for name,pairs in repls.items():
 def apply(s,pairs=pairs):
  for a,b in pairs:
   assert a in s,(name,a)
   s=s.replace(a,b)
  return s
 change(name,apply)
change('docs/wiki/Fonts.md',lambda s:s.replace('Automatic Google Fonts import is deferred and not implemented. Manually copy licensed static TTF/OTF files into the plugin /fonts directory using your hosting file manager or SFTP. Back up these files before plugin updates. There is no font upload endpoint.','Gudea Regular is included under SIL Open Font License 1.1. In the timer editor, select **Gudea Regular (included)** under **Appearance > Typography and Size > Font**, then save. The bitmap remains available and existing campaigns are not changed. GD/FreeType is required.\n\nThe bundled font is stored in plugin `/fonts/` with its original license and source notice. It can be bundled with commercial software under OFL conditions; it is not relicensed under the plugin GPL.\n\nFor **your own** licensed TTF/OTF files use the persistent directory shown in Data Settings, normally `wp-content/uploads/email-countdown-timer/fonts/`. Files manually added to the plugin directory may be lost during updates. Automatic Google Fonts import and site-wide discovery remain deferred; there is no font upload endpoint.'))
change('tests/test-build.py',lambda s:s.replace('for folder in ("includes", "assets"):', 'for folder in ("includes", "assets", "fonts"):').replace('def test_development_and_fonts_are_not_bundled(self):\n        (self.root / "fonts").mkdir()', 'def test_user_fonts_and_development_are_not_bundled(self):').replace('self.assertFalse(any("fonts/" in p or "wp-config" in p or "scripts/" in p for p in archive.namelist()))','self.assertEqual({p for p in archive.namelist() if "/fonts/" in p}, {builder.SLUG + "/" + p for p in builder.FONT_FILES})\n            self.assertFalse(any("manual.ttf" in p or "wp-config" in p or "scripts/" in p for p in archive.namelist()))'))
p=root/'tests/test-build.py'
s=p.read_text();insert='''
    def test_bundled_font_and_license_integrity(self):
        import hashlib
        files = builder.sources(self.root)
        for name, expected in builder.FONT_HASHES.items():
            self.assertEqual(hashlib.sha256(files[name]).hexdigest(), expected)
        for name in builder.FONT_HASHES:
            p = self.root / name
            original = p.read_bytes()
            p.write_bytes(original + b"changed")
            with self.assertRaises(ValueError): self.build()
            p.write_bytes(original)
            p.unlink()
            with self.assertRaises(ValueError): self.build()
            p.write_bytes(original)

    def test_bundled_font_symlink(self):
        p = self.root / next(n for n in builder.FONT_HASHES if n.endswith(".ttf"))
        p.unlink()
        p.symlink_to(ROOT / p.relative_to(self.root))
        with self.assertRaises(ValueError): self.build()

    def test_bundled_font_directory_symlink(self):
        shutil.rmtree(self.root / "fonts")
        (self.root / "fonts").symlink_to(ROOT / "fonts", target_is_directory=True)
        with self.assertRaises(ValueError): self.build()

    def test_bundled_font_has_required_characters(self):
        # Inspect the immutable TrueType Unicode cmap, not system/browser fonts.
        import struct
        data = (self.root / "fonts/easy-countdown-gudea-regular.ttf").read_bytes()
        u16 = lambda at: struct.unpack_from(">H", data, at)[0]
        u32 = lambda at: struct.unpack_from(">I", data, at)[0]
        tables = {data[o:o+4]: u32(o+8) for o in range(12, 12+16*u16(4), 16)}
        self.assertNotIn(b"fvar", tables, "Example must remain a static face")
        cmap = tables[b"cmap"]
        coverage = set()
        for i in range(u16(cmap+2)):
            record = cmap+4+8*i
            platform, encoding = u16(record), u16(record+2)
            if platform != 0 and (platform, encoding) not in ((3,1),(3,10)):
                continue
            offset = cmap+u32(record+4)
            if u16(offset) != 4:
                continue
            count = u16(offset+6)//2
            end = offset+14
            start = end+2*count+2
            delta = start+2*count
            ranges = delta+2*count
            for segment in range(count):
                for code in range(u16(start+2*segment), u16(end+2*segment)+1):
                    if code == 0xffff: continue
                    jump = u16(ranges+2*segment)
                    glyph = u16(ranges+2*segment+jump+2*(code-u16(start+2*segment))) if jump else code
                    if glyph or not jump:
                        glyph = (glyph+u16(delta+2*segment)) & 0xffff
                    if glyph: coverage.add(code)
        required = "0123456789: -DaysHoursMinutesSecondsĄĆĘŁŃÓŚŹŻąćęłńóśźż"
        self.assertTrue(set(map(ord, required)).issubset(coverage), "Missing expected timer glyphs")
'''
p.write_text(s.replace('\n\nif __name__ == "__main__":','\n'+insert+'\n\nif __name__ == "__main__":'))
change('tests/integration/fonts.php',lambda s:s.replace("    $copied = Email_Countdown_Timer_Fonts::copy_legacy();","    $copied = Email_Countdown_Timer_Fonts::copy_legacy();\n    $assert(!array_key_exists(Email_Countdown_Timer_Fonts::BUNDLED_FILE, get_option(Email_Countdown_Timer_Fonts::OPTION, [])), 'Bundled example is not adopted as migrated user data');",1))
new('tests/integration/bundled-font.php','''<?php
/** Read-only checks of the installed example font on disposable real WordPress. */
if (!defined('WP_CLI') || !WP_CLI || getenv('ECD_INTEGRATION_DISPOSABLE') !== '1' || wp_get_environment_type() !== 'local') {
    throw new RuntimeException('Disposable local WordPress required.');
}
$checks = 0;
$expect = static function (bool $condition, string $message) use (&$checks): void {
    ++$checks;
    if (!$condition) throw new RuntimeException('Bundled font: ' . $message);
};
$name = Email_Countdown_Timer_Fonts::BUNDLED_FILE;
$file = EMAIL_COUNTDOWN_TIMER_DIR . 'fonts/' . $name;
$expect(is_file($file) && !is_link($file), 'exact package contains a regular local file');
$expect(hash_file('sha256', $file) === 'a4b5410090a821ea17021627312babdf3d8db0271217fd8c4d9f629ef0a2d90b', 'original font bytes');
$expect(hash_file('sha256', EMAIL_COUNTDOWN_TIMER_DIR . 'fonts/OFL.txt') === '315a576cbc7ab61c9e347b5725893bc8498fdcb8fc10831793c6864bc2cefba8', 'original license shipped');
$before = get_option('easy_countdown_timers', []);
$expect(in_array($name, Email_Countdown_Timer_Config::fonts(), true), 'discovered by existing selector');
$expect(Email_Countdown_Timer_Config::fontPath($name) === realpath($file), 'resolver uses local bundle');
$expect(Email_Countdown_Timer_Admin::defaults()['font'] === '', 'new timer bitmap default unchanged');
$config = Email_Countdown_Timer_Config::normalize(['deadline'=>'2030-01-01T00:00:00','tz'=>'UTC']);
$expect($config['font'] === '', 'legacy missing selection remains bitmap');
$font_config = array_replace($config, ['font'=>$name,'label_d'=>'Dzień','label_h'=>'Godziny','label_m'=>'Minuty','label_s'=>'Sekundy']);
$expect(function_exists('imagettfbbox') && function_exists('imagettftext'), 'FreeType present for native checks');
$expect(Email_Countdown_Timer_Admin::preflight($font_config) === [], 'Polish labels pass real font preflight');
$renderer = new Email_Countdown_Timer_Renderer();
$deadline = Email_Countdown_Timer_Config::deadline($config);
foreach ([0, 59, 86400, 8640000] as $remaining) {
    $now = $deadline - $remaining;
    $layout = $renderer->measure($font_config, $deadline, $now);
    $bitmap = $renderer->render_static($config, $deadline, $now, 'png');
    foreach (['png', 'gif', 'webp'] as $format) {
        if ($format === 'webp' && !function_exists('imagewebp')) continue;
        $bytes = $renderer->render_static($font_config, $deadline, $now, $format);
        $image = imagecreatefromstring($bytes);
        $expect($image instanceof GdImage, 'native ' . $format . ' decodes');
        $expect(imagesx($image) === $layout['width'] && imagesy($image) === $layout['height'], 'measured dimensions match ' . $format);
        if ($format === 'png') $expect($bytes !== $bitmap, 'font selection actually changes pixels');
        unset($image);
    }
    $expect($renderer->render_static($config, $deadline, $now, 'png') === $bitmap, 'bitmap unchanged after example render');
}
if (class_exists('Imagick')) {
    $animation = new Imagick();
    $animation->readImageBlob($renderer->render($font_config, $deadline, $deadline - 120, 'gif'));
    $expect($animation->getNumberImages() === 60, 'still 60 animation frames');
    foreach ($animation as $frame) $expect($frame->getImageDelay() === 100, 'one-second frame timing');
    $animation->clear();
}
$oversized = array_replace($font_config, ['size_digit'=>200,'size_label'=>100,'fixed_width'=>4000]);
$expect(isset(Email_Countdown_Timer_Admin::preflight($oversized)['font']), 'pixel budget still enforced');
$expect(get_option('easy_countdown_timers', []) === $before, 'no campaign settings changed');
WP_CLI::success("BUNDLED FONT: $checks assertions, installed package and real GD/FreeType.");
''')
change('scripts/test-wordpress.sh',lambda s:s.replace('"${wp[@]}" eval-file "$root/tests/integration/embed-geometry.php"','"${wp[@]}" eval-file "$root/tests/integration/bundled-font.php"\n"${wp[@]}" eval-file "$root/tests/integration/embed-geometry.php"'))
change('tests/font-storage.php',lambda s:s.replace('fonts are never shipped.','arbitrary fixture fonts are never shipped.'))
subprocess.run(['git','add','--',*changed],cwd=root,check=True)
print('Changed',len(changed),'paths; readme bytes',(root/'readme.txt').stat().st_size)
