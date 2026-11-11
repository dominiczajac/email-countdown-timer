# Native CI dependencies

The Ubuntu 24.04 jobs use the runner's Apache/nginx and the versioned FPM
binary installed by the pinned `shivammathur/setup-php` action. Each workflow
runs the required binaries before provisioning. The actual HTTP/native tests,
runtime context checks, package version evidence and post-stop log gates remain
mandatory; a missing binary fails the job.

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
The pinned action also [registers versioned FPM binaries](https://github.com/shivammathur/setup-php/blob/f3e473d116dcccaddc5834248c87452386958240/src/scripts/linux.sh).
Future image changes must satisfy the same executable checks and native test
gates; this document does not claim a fixed future runner image version.
