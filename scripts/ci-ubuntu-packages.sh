#!/usr/bin/env bash
# Hosted Ubuntu dependencies only. Keep the runner's mirror/PPA configuration intact.
set -euo pipefail
[[ "${GITHUB_ACTIONS:-}" == true && "${RUNNER_OS:-}" == Linux && "${RUNNER_TEMP:-}" == /* && -d "$RUNNER_TEMP" ]] || exit 1
[[ "$#" -gt 0 ]] || exit 1
for package in "$@"; do
  [[ "$package" =~ ^[a-z0-9][a-z0-9+.-]*$ ]] || exit 1
done
. /etc/os-release
[[ "$ID" == ubuntu && "$VERSION_ID" == 24.04 && "$VERSION_CODENAME" == noble && "$(dpkg --print-architecture)" == amd64 ]] || exit 1
keyring=/usr/share/keyrings/ubuntu-archive-keyring.gpg
[[ -r "$keyring" ]] || exit 1
apt_dir="$(mktemp -d "$RUNNER_TEMP/ecd-ubuntu-apt.XXXXXX")"
chmod 755 "$apt_dir"
mkdir -p "$apt_dir/lists/partial"
cat > "$apt_dir/ubuntu.list" <<EOF
deb [signed-by=$keyring] https://archive.ubuntu.com/ubuntu noble main restricted universe multiverse
deb [signed-by=$keyring] https://archive.ubuntu.com/ubuntu noble-updates main restricted universe multiverse
deb [signed-by=$keyring] https://security.ubuntu.com/ubuntu noble-security main restricted universe multiverse
EOF
options=(
  -o "Dir::Etc::sourcelist=$apt_dir/ubuntu.list"
  -o Dir::Etc::sourceparts=-
  -o "Dir::State::lists=$apt_dir/lists"
  -o APT::Update::Error-Mode=any
  -o Acquire::Retries=2
  -o Acquire::https::Timeout=30
)
sudo apt-get "${options[@]}" update -qq
sudo apt-get "${options[@]}" install -y --no-install-recommends "$@"
