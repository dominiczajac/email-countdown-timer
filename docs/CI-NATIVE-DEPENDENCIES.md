# Native CI dependencies

The Ubuntu 24.04 jobs use the runner's Apache/nginx and the versioned FPM
binary installed by the pinned `shivammathur/setup-php` action. Each workflow
runs the required binaries before provisioning. The actual HTTP/native tests,
runtime context checks, package version evidence and post-stop log gates remain
mandatory; a missing binary fails the job.

The cache profile and multisite reports keep nginx's `dpkg` version under
`native_packages`. FPM can be a cached binary without a matching `dpkg` record;
`native_binaries` therefore records its exact executable path, SHA256 and
version output. The actual HTTP SAPI/PHP/JIT/GD context checks are unchanged.

The runner image already contains the web servers, and setup-php's cached PHP
build contains FPM. Adding `ppa:ondrej/php` again unnecessarily contacts the
Launchpad API. PR #34 runs `43065825704`, `43068529733` and `43068529671`
retained failures before the affected tests: Azure mirror DNS resolution and
Launchpad API timeouts. These failures are not passing compatibility evidence.

GDB and the native mail tools still require Ubuntu packages.
`scripts/ci-ubuntu-packages.sh` is restricted to hosted Linux actions on
Ubuntu 24.04 amd64. It uses a private list of official HTTPS Ubuntu archive,
updates and security sources, with the image's Ubuntu archive keyring and
separate package indexes. Normal TLS and APT signature verification remain
enabled. Index errors fail the job; there is no unsigned, stale-index or
successful no-op fallback. The script does not rewrite the runner's repository
configuration, install another PHP build or alter the tests' acceptance rules.

The preinstalled server contract is documented by the
[runner image inventory](https://github.com/actions/runner-images/blob/ubuntu24/20261101.360/images/ubuntu/Ubuntu2404-Readme.md)
and its [PHP build script](https://github.com/actions/runner-images/blob/ubuntu24/20261101.360/images/ubuntu/scripts/build/install-php.sh).
The pinned action also [registers versioned FPM binaries](https://github.com/shivammathur/setup-php/blob/eb7c497e18156a6bbabfef1d3a82760b9eda3962/src/scripts/linux.sh).
Future image changes must satisfy the same executable checks and native test
gates; this document does not claim a fixed future runner image version.


## Pinned action maintenance

The workflows pin setup-php **2.40.0** to commit
`eb7c497e18156a6bbabfef1d3a82760b9eda3962`, the commit resolved from the
upstream signed release tag. Its Node 24 action retains the required versioned
FPM/cached-build contract. PHP minors, extensions, WP-CLI 2.12.0, OPcache/JIT
cells, native limits, isolated Ubuntu dependency sources and acceptance gates
are unchanged. The Ubuntu 26.04 and third-party runner branches in the upstream
release are outside this repository's hosted Ubuntu 24.04 native fixture scope.

Changing CI actions does not change the 32-file WordPress installation ZIP or
its font/license bytes. Keep the named Gate A package and maintainer acceptance
receipt; separately record validation of the updated CI toolchain. Current-head
CI and the cache/multisite/LSAPI workflows must finish successfully, and their
actual uploaded evidence must remain downloadable and parseable before merge.
