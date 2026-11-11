#!/bin/sh
# Private image process only; the host mounts one disposable lab directory.
set -eu
task_uid=$1
task_gid=$2
configuration=$3
case "$task_uid:$task_gid" in *[!0-9:]*|'') exit 1;; esac
groupadd -o -g "$task_gid" ecdlab
useradd -o -u "$task_uid" -g ecdlab -M -s /usr/sbin/nologin ecdlab
mkdir -p /tmp/lshttpd/swap /tmp/ecd-lscache
touch /usr/local/lsws/logs/stderr.log
chown -R ecdlab:ecdlab /tmp/lshttpd /tmp/ecd-lscache /usr/local/lsws/logs
cp "$configuration/httpd.conf" /usr/local/lsws/conf/httpd_config.conf
cp "$configuration/zz-ecd.ini" /usr/local/lsws/lsphp84/etc/php/8.4/mods-available/zz-ecd.ini
chmod 0644 /usr/local/lsws/lsphp84/etc/php/8.4/mods-available/zz-ecd.ini
/usr/local/lsws/bin/openlitespeed -t
/usr/local/lsws/bin/lswsctrl start
trap '/usr/local/lsws/bin/lswsctrl stop; exit 0' TERM INT
tail -F /usr/local/lsws/logs/stderr.log &
wait $!
