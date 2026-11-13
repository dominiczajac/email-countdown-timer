Easy Countdown: four reviewed local fonts

Select an included face under Create/Edit Timer > Appearance > Typography
and Size > Font, then save. Lato remains the new full editor's default when
FreeType is available; existing campaigns keep their font or bitmap setting.
The other included faces are IBM Plex Mono Regular, Barlow Condensed Regular
and Crimson Text Regular. Local GD/FreeType support is required.

Font: Lato Regular, by Lukasz Dziedzic / tyPoland.
License: SIL Open Font License 1.1. Read the original OFL.txt beside this file.
Commercial use and bundling with software are permitted subject to that license;
do not sell the font by itself. The font remains OFL, not the plugin's GPL.
Its font data and internal names are unmodified. Only the filename is prefixed
to avoid collisions with custom files named Lato-Regular.ttf.

Public upstream: https://github.com/google/fonts/tree/9710da1eacb3be272583c3224dcb70f9da6eadbb/ofl/lato
Original filename: Lato-Regular.ttf
Installed filename: easy-countdown-lato-regular.ttf
Upstream Git blob: 0f3d0f837d24834b9b5b0a6b735459c56f5e75c3
Font SHA256: d636e4683231f931eda222d588e944d082bfd3bdba02f928bee461c0f185b251
OFL.txt SHA256: 74ba064d03f1f1c4a952da936c3eb71866c34404916734de3cae73b34357e59e

The four fonts are part of every installation ZIP; WordPress never downloads them.
They are read locally when drawing an image, not sent as web fonts to recipients.
Website live text continues to use theme typography.
This directory belongs to the plugin and is replaced during updates. Store YOUR
additional fonts in the persistent directory displayed in Data Settings,
normally wp-content/uploads/email-countdown-timer/fonts/ (per-site on multisite).
Do not replace the included file or its license. Unchanged bundled files do not
need the legacy-copy tool. Existing manually supplied filenames keep resolving
as before; adding this example does not migrate or overwrite those files.

Included faces use reserved built-in choices, not filenames. An existing
campaign referencing a custom font with the same filename as a newly included
face still uses its own persistent file, when present. Without that file it
keeps the existing bitmap fallback instead of switching to the new stock face.

Additional approved faces (SIL Open Font License 1.1)
--------------------------------------------------
These static TTF bytes and internal names are unmodified. Only the packaged
filenames are prefixed. Each original license includes its own copyright
notice; retain all four OFL files. IBM's Reserved Font Name "Plex" is preserved.

Google Fonts snapshot for all three:
https://github.com/google/fonts/tree/c30b221beb0c1da59d563feb91ec8656a1fa972a/ofl

IBM Plex Mono Regular
Copyright 2017 IBM Corp.; Reserved Font Name "Plex".
Upstream: ibmplexmono/IBMPlexMono-Regular.ttf
Git blob: 0c9770d5183ba60dc4350d3e011b782a320761ae
Installed: easy-countdown-ibm-plex-mono-regular.ttf
Choice: builtin:ibm-plex-mono-regular
Font SHA256: 6a3412f058c7d8dfd9170c41e85ade48e5156ecb89356110ca57a0a27734af46
License: OFL-ibm-plex-mono.txt
License SHA256: 7e6b2818edbd8f6a01ae80641cc8f16a51080d08fb4e532be3a0b6f74adb07da

Barlow Condensed Regular
Copyright 2017 The Barlow Project Authors (https://github.com/jpt/barlow).
Upstream: barlowcondensed/BarlowCondensed-Regular.ttf
Git blob: ecbe0cc84118decc6a16216ad53d83d39888b175
Installed: easy-countdown-barlow-condensed-regular.ttf
Choice: builtin:barlow-condensed-regular
Font SHA256: 583cec5da3b84bc4dc7c9c72e2a565c94d34e431518b19d7e250b7830ad5f996
License: OFL-barlow-condensed.txt
License SHA256: 186d750eb496a4c17a76385f82be6aea2ac1cf2de074a811d63786cf374ea73f

Crimson Text Regular
Upstream: crimsontext/CrimsonText-Regular.ttf
Copyright and Reserved Font Name: see original OFL-crimson-text.txt.
Git blob: 6b474728a9a499233959959fb7d370df97733450
Installed: easy-countdown-crimson-text-regular.ttf
Choice: builtin:crimson-text-regular
Font SHA256: 48e6c5d5ad1d01599d374ecb817e15890d1feb3b8a3a88e527d44c90389e1f06
License: OFL-crimson-text.txt
License SHA256: 50fd67cddc097377a5c871e8452b778bc5aedfa3480a705cb27c5e3a078218df

Barlow Condensed Black (weight 900), original static face, unmodified.
Source: google/fonts commit c30b221beb0c1da59d563feb91ec8656a1fa972a
Path: ofl/barlowcondensed/BarlowCondensed-Black.ttf
Bundled: easy-countdown-barlow-condensed-black.ttf
SHA256: e74b750df582c608f35db467b711b2b60d2217618e85e60b72b42dfd00446cab
License: existing OFL-barlow-condensed.txt, unchanged original family notice.
The maintainer requested genuine heavier preset typography. No synthetic bold or runtime download.
