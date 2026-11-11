#!/usr/bin/env python3
"""Own one isolated Docker network/database/LSAPI server, never a real site."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import secrets
import subprocess
import sys
import time

OWNER_LABEL = 'email-countdown-timer.ols-owner'
ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / 'tests/cache-profiles'))
from server_contracts import MYSQL_IMAGE, OLS_IMAGE


def owned_paths():
    if os.environ.get('ECD_INTEGRATION_DISPOSABLE') != '1':
        raise ValueError('disposable_only')
    paths = [Path(os.environ[x]) for x in ('RUNNER_TEMP', 'ECD_CACHE_WP', 'ECD_CACHE_EVIDENCE')]
    if any(not p.is_absolute() or not re.fullmatch(r'/[A-Za-z0-9_./-]+', str(p)) or p.resolve(strict=True) != p for p in paths):
        raise ValueError('invalid_owned_path')
    temp, wp, evidence = paths
    if wp.parent != temp or not wp.name.startswith('ect-cache.') or evidence != temp / 'cache-evidence':
        raise ValueError('invalid_owned_layout')
    profile, jit = os.environ['ECD_CACHE_PROFILE'], os.environ['ECD_CACHE_JIT']
    if profile not in ('baseline', 'litespeed-cache') or jit not in ('1235', 'disable'):
        raise ValueError('invalid_profile')
    return temp, wp, evidence, profile, jit


def docker(*args, timeout=300, missing=False):
    env = dict(os.environ)
    for key in ('DOCKER_HOST', 'DOCKER_CONTEXT', 'DOCKER_TLS', 'DOCKER_TLS_VERIFY', 'DOCKER_CERT_PATH'):
        env.pop(key, None)
    result = subprocess.run(['docker', '--host=unix:///var/run/docker.sock', *args], env=env,
                            capture_output=True, text=True, timeout=timeout)
    if result.returncode and not missing:
        # Fixed classification in annotations; original Docker diagnosis stays local.
        evidence = owned_paths()[2]
        with (evidence / 'docker-errors.log').open('a') as stream:
            stream.write(result.stdout + result.stderr)
        raise RuntimeError('docker_' + args[0] + '_failed')
    return result


def image_ready(reference):
    if docker('image', 'inspect', reference, missing=True).returncode:
        docker('pull', '--platform=linux/amd64', reference)


def save_state(evidence, state):
    path = evidence / 'docker-state.json'
    fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
    with os.fdopen(fd, 'w') as stream:
        stream.write(json.dumps(state, indent=2) + '\n')


def load_state(evidence):
    path = evidence / 'docker-state.json'
    if path.is_symlink():
        raise ValueError('invalid_state')
    state = json.loads(path.read_text())
    token = state.get('token', '')
    if not isinstance(token, str) or not re.fullmatch('[a-f0-9]{24}', token):
        raise ValueError('invalid_owner')
    if state.get('database') != 'ect-ols-db-' + token or state.get('server') != 'ect-ols-web-' + token or state.get('network') != 'ect-ols-net-' + token:
        raise ValueError('invalid_owned_resources')
    return state


def inspect_owned(kind, name, token):
    response = docker(kind, 'inspect', name, missing=True)
    if response.returncode:
        if re.search(r'no such (?:object|container|network)', response.stdout + response.stderr, re.I):
            return None
        raise RuntimeError('resource_inspection_failed')
    data = json.loads(response.stdout)[0]
    labels = data.get('Labels') if kind == 'network' else data['Config'].get('Labels')
    if not isinstance(labels, dict) or labels.get(OWNER_LABEL) != token:
        raise ValueError('resource_not_owned')
    return data


def start_database(temp, evidence):
    if (evidence / 'docker-state.json').exists():
        raise ValueError('state_already_exists')
    token = secrets.token_hex(12)
    state = {'token': token, 'database': 'ect-ols-db-' + token, 'server': 'ect-ols-web-' + token, 'network': 'ect-ols-net-' + token}
    save_state(evidence, state)
    image_ready(MYSQL_IMAGE)
    image_ready(OLS_IMAGE)
    docker('network', 'create', '--label', OWNER_LABEL + '=' + token, state['network'])
    docker('run', '-d', '--platform=linux/amd64', '--name', state['database'], '--label', OWNER_LABEL + '=' + token,
           '--network', state['network'], '--network-alias', 'database', '-p', '127.0.0.1:3307:3306',
           '-e', 'MYSQL_ROOT_PASSWORD=cache-lab-disposable-only', '-e', 'MYSQL_DATABASE=ect_cache_lab',
           '-e', 'MYSQL_ROOT_HOST=%', MYSQL_IMAGE)
    for _ in range(40):
        if not docker('exec', state['database'], 'mysqladmin', 'ping', '-h127.0.0.1',
                      '-pcache-lab-disposable-only', missing=True, timeout=5).returncode:
            return
        time.sleep(1)
    raise RuntimeError('database_not_ready')


def configurations(temp, wp, evidence, profile, jit):
    directory = temp / 'ols-config'
    directory.mkdir(exist_ok=False)
    main = f'''serverName ecd-disposable
user ecdlab
group ecdlab
httpdWorkers 1
priority 0
enableChroot 0
inMemBufSize 60M
swappingDir /tmp/lshttpd/swap
mime conf/mime.properties
showVersionNumber 0
disableWebAdmin 1
indexFiles index.php,index.html
errorlog {evidence}/ols-error.log {{
  logLevel INFO
  rollingSize 10M
  enableStderrLog 1
}}
accesslog {evidence}/ols-access.log {{
  logFormat %h %t "%m %U" %>s %b
  rollingSize 10M
}}
tuning {{
  maxConnections 256
  maxSSLConnections 256
  connTimeout 40
  maxKeepAliveReq 1000
  keepAliveTimeout 5
  enableGzipCompress 1
  enableDynGzipCompress 1
}}
CGIRLimit {{
  maxCGIInstances 20
  minUID 11
  minGID 10
  priority 0
  CPUSoftLimit 0
  CPUHardLimit 0
  memSoftLimit 0
  memHardLimit 0
  procSoftLimit 400
  procHardLimit 450
}}
extprocessor lsphp {{
  type lsapi
  address uds://tmp/lshttpd/ecd.sock
  maxConns 4
  env PHP_LSAPI_CHILDREN=4
  env LSAPI_MAX_PROCESS_TIME=35
  env ECD_LITESPEED_DB_HOST=database:3306
  initTimeout 30
  retryTimeout 0
  persistConn 1
  respBuffer 0
  autoStart 1
  path /usr/local/lsws/lsphp84/bin/lsphp
  backlog 20
  instances 1
}}
scripthandler {{
  add lsapi:lsphp php
}}
virtualhost fixture {{
  vhRoot {wp}/
  configFile {directory}/vhost.conf
  allowSymbolLink 0
  enableScript 1
  restrained 1
}}
listener fixture {{
  address *:8095
  secure 0
  map fixture *
}}
module cache {{
  ls_enabled {int(profile == 'litespeed-cache')}
  storagePath /tmp/ecd-lscache
  checkPublicCache 1
  checkPrivateCache 0
  maxCacheObjSize 10000000
  maxStaleAge 0
  qsCache 1
  reqCookieCache 0
  respCookieCache 0
  ignoreReqCacheCtrl 0
  ignoreRespCacheCtrl 0
  enableCache 0
  expireInSeconds 1800
  enablePrivateCache 0
}}
'''
    vhost = f'''docRoot {wp}/
enableGzip 1
index {{
  useServer 0
  indexFiles index.php
  autoIndex 0
}}
rewrite {{
  enable 1
  autoLoadHtaccess 1
}}
phpIniOverride {{
  php_admin_value max_execution_time 30
  php_admin_value memory_limit 256M
  php_admin_flag log_errors On
  php_admin_flag display_errors Off
  php_admin_value error_log {evidence}/php-errors.log
}}
accesslog {evidence}/http.log {{
  useServer 0
  logFormat %h %t "%m %U" %>s %b
  rollingSize 10M
}}
'''
    ini = f'''max_execution_time=30
memory_limit=256M
log_errors=1
display_errors=0
error_log={evidence}/php-errors.log
opcache.enable=1
opcache.jit={jit}
opcache.jit_buffer_size=256M
'''
    for name, text in [('httpd.conf', main), ('vhost.conf', vhost), ('zz-ecd.ini', ini)]:
        (directory / name).write_text(text)
    return directory


def start_server(temp, wp, evidence, profile, jit):
    state = load_state(evidence)
    inspect_owned('network', state['network'], state['token'])
    configuration = configurations(temp, wp, evidence, profile, jit)
    uid, gid = os.getuid(), os.getgid()
    if not 0 < uid < 65534 or not 0 < gid < 65534:
        raise ValueError('unprivileged_task_user_required')
    docker('run', '-d', '--platform=linux/amd64', '--name', state['server'], '--label', OWNER_LABEL + '=' + state['token'],
           '--network', state['network'], '-p', '127.0.0.1:8095:8095',
           '--mount', f'type=bind,src={temp},dst={temp}',
           '--mount', f'type=bind,src={ROOT}/tests/litespeed/bootstrap.sh,dst=/ecd-bootstrap.sh,readonly',
           '--entrypoint', '/bin/sh', OLS_IMAGE, '/ecd-bootstrap.sh', str(uid), str(gid), str(configuration))
    for _ in range(40):
        response = subprocess.run(['curl', '-fsS', '--max-time', '2', 'http://localhost:8095/wp-login.php'], capture_output=True)
        if response.returncode == 0:
            break
        time.sleep(1)
    else:
        raise RuntimeError('server_not_ready')
    info = inspect_owned('container', state['server'], state['token'])
    image_id = docker('image', 'inspect', OLS_IMAGE, '--format', '{{.Id}}').stdout.strip()
    architecture = docker('image', 'inspect', OLS_IMAGE, '--format', '{{.Os}}/{{.Architecture}}').stdout.strip()
    if info['Image'] != image_id:
        raise ValueError('executed_image_mismatch')
    version_result = docker('exec', state['server'], '/usr/local/lsws/bin/openlitespeed', '-v')
    version = version_result.stdout + version_result.stderr
    php = docker('exec', state['server'], '/usr/local/lsws/lsphp84/bin/php', '-r', 'echo PHP_VERSION;').stdout.strip()
    packages = docker('exec', state['server'], 'dpkg-query', '-W', '-f=${Package} ${Version}\n', 'lsphp84', 'lsphp84-imagick').stdout
    config_hash = hashlib.sha256((configuration / 'httpd.conf').read_bytes()).hexdigest()
    executed_config_hash = docker('exec', state['server'], 'sha256sum', '/usr/local/lsws/conf/httpd_config.conf').stdout.split()[0]
    if executed_config_hash != config_hash:
        raise ValueError('executed_configuration_mismatch')
    proof = {'name': 'openlitespeed', 'version_output': version, 'php_lsapi': php, 'image_reference': OLS_IMAGE,
             'image_id': image_id, 'executed_image_id': info['Image'], 'architecture': architecture,
             'cache_enabled': profile == 'litespeed-cache', 'query_cache': True,
             'connection_timeout': 40, 'php_workers': 4, 'lsapi_process_limit': 35,
             'configuration_sha256': config_hash, 'executed_configuration_sha256': executed_config_hash,
             'packages': dict(line.split(' ', 1) for line in packages.strip().splitlines())}
    (evidence / 'server.json').write_text(json.dumps(proof, indent=2) + '\n')


def stop_server(evidence):
    state = load_state(evidence)
    info = inspect_owned('container', state['server'], state['token'])
    if info is None:
        raise ValueError('missing_server')
    if info['State']['Running']:
        docker('stop', '--time', '20', state['server'], timeout=30)
    info = inspect_owned('container', state['server'], state['token'])
    result = {key: info['State'][key] for key in ('Running', 'ExitCode', 'OOMKilled')}
    (evidence / 'server-stop.json').write_text(json.dumps(result) + '\n')
    logs = docker('logs', state['server'])
    (evidence / 'server-container.log').write_text(logs.stdout + logs.stderr)
    docker('cp', state['server'] + ':/usr/local/lsws/logs/stderr.log', str(evidence / 'lsapi-stderr.log'))


def clean(evidence):
    if not (evidence / 'docker-state.json').exists():
        return
    state = load_state(evidence)
    for key in ('server', 'database'):
        if inspect_owned('container', state[key], state['token']) is not None:
            docker('rm', '-f', '-v', state[key])
    if inspect_owned('network', state['network'], state['token']) is not None:
        docker('network', 'rm', state['network'])


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('action', choices=('database', 'start', 'stop', 'clean'))
    action = parser.parse_args().action
    temp, wp, evidence, profile, jit = owned_paths()
    if action == 'database':
        start_database(temp, evidence)
    elif action == 'start':
        start_server(temp, wp, evidence, profile, jit)
    elif action == 'stop':
        stop_server(evidence)
    else:
        clean(evidence)


if __name__ == '__main__':
    main()
