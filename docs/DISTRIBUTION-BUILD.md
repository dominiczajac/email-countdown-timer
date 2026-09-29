# Reproducible installation ZIP

The installation artifact is different from GitHub's automatic source archive. Use the commands below from an identified, reviewed source revision. Building a ZIP does not publish a release, tag or WordPress.org submission.

```sh
bash scripts/build-zip.sh --output dist/easy-countdown.zip --report dist/distribution.json
python3 tests/test-build.py
```

Python 3.9+ and its standard library are required **only for development packaging**, not on the WordPress host. CI uses the Python version available on its pinned runner family. The report records Python/zlib versions, package version, file count, archive SHA-256 and each shipped file hash. With identical sources and the same Python/zlib toolchain, builds are byte-repeatable: paths are sorted, timestamps fixed at 1980-01-01 and permissions normalized to regular 0644 files. Different compression toolchains are not promised to emit identical ZIP bytes; file hashes remain comparable.

## One source of distribution contents

`scripts/distribution-files.txt` is the explicit allowlist. The builder refuses missing required files, unlisted files under includes/assets, duplicate/unsafe paths, source symlinks and inconsistent header/constant/readme version or license metadata. It never includes font binaries, uploads, repository history, development documentation, tests or CI files. An intentional new runtime file requires a manifest update. Development files may exist outside runtime folders; they are not packed.

The ZIP contains exactly one top-level `easy-countdown/` directory. No manually installed font or configuration is copied from a developer's machine. Failed input validation preserves an existing output archive; publication uses a temporary file and atomic replace. Build outputs inside the source tree are restricted to `dist/`, which is ignored by Git. This is packaging hygiene, not signing, provenance attestation or protection against a compromised build machine.

Plugin Check installs the generated ZIP, verifies its installed files against `distribution.json`, then checks that distribution. The ZIP, report, complete source snapshot and PCP output are retained in the same workflow artifact. Lifecycle, admin-browser and full HTTP tests also install the canonical ZIP rather than a separate hand-copied file list. Test-only fonts and observers are added only afterward to disposable test instances; they never enter the install ZIP.

```sh
python3 scripts/verify-distribution.py dist/distribution.json /path/to/installed/easy-countdown
```

Use that verification command on a clean extracted/installed package before test fixtures or site-managed fonts are added. Extra or modified files deliberately cause failure. Do not use a failed comparison as a reason to delete a real site's files automatically.

## Release checklist and current limits

Read current-head CI, Plugin Check, browser and HTTP results before a protected merge. Download the exact PCP-tested ZIP when possible; otherwise verify all contents against its report. Do not describe a historic green run as a test of a later source tree. Native GD/Imagick and real WordPress tests run in CI; local absence of those extensions must be reported.

Before directory submission, confirm the owner's WordPress.org username for `Contributors`, 2FA, slug and contact address. A GitHub username is not proof of WordPress.org identity. Genuine screenshots and any directory graphics should be published separately as WordPress.org presentation assets, not silently added to runtime assets. Never include private site screenshots or fonts from the container.

References: [WordPress readmes](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/), [plugin assets](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/), [privacy guide API](https://developer.wordpress.org/reference/functions/wp_add_privacy_policy_content/).
