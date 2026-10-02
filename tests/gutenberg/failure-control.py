"""Cold-process diagnostic after a failed test; never changes its failed result.

Only the synthetic loopback fixture is accepted. Process restarts discard warm
OPcache/JIT state between plugin-off and plugin-on controls. No response bodies,
cookies, PHP environment variables or raw process dumps are published.
"""
import hashlib
import http.cookiejar
import json
import os
from pathlib import Path
import signal
import socket
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

assert os.environ.get("GITHUB_ACTIONS") == "true"
assert os.environ.get("ECD_INTEGRATION_DISPOSABLE") == "1"
root = Path(os.environ["RUNNER_TEMP"]).resolve()
site = Path(os.environ["ECD_BLOCK_WP_PATH"]).resolve()
out = Path(os.environ["ECD_BLOCK_EVIDENCE"]).resolve()
assert site.is_relative_to(root) and out.is_relative_to(root)
assert len(sys.argv) == 4
previous_pid = int(sys.argv[1])
service = Path(sys.argv[2]).resolve()
minor = sys.argv[3]
assert service.is_relative_to(root) and minor in ("8.1", "8.4")
assert previous_pid > 1 and (service / "fpm.conf").is_file()
base = "http://localhost:8093"
post = int(os.environ["ECD_BLOCK_POST_ID"])

def wp(*args):
    return subprocess.check_output(["wp", "--path=" + str(site), "--no-color", *args],
                                   text=True, timeout=20).strip()

assert wp("config", "get", "table_prefix") == "ectblock_"
assert wp("config", "get", "WP_ENVIRONMENT_TYPE") == "local"
assert wp("option", "get", "home") == base

def listener_open():
    with socket.socket() as sock:
        sock.settimeout(0.2)
        return sock.connect_ex(("127.0.0.1", 9003)) == 0

class LocalRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        parsed = urllib.parse.urlsplit(newurl)
        if parsed.scheme != "http" or parsed.netloc != "localhost:8093":
            raise RuntimeError("Non-loopback redirect refused")
        return super().redirect_request(req, fp, code, msg, headers, newurl)

result = {"diagnostic_only": True, "original_test_failed": True,
          "fresh_fpm_per_state": True, "max_editor_requests_per_state": 6, "states": []}
child = None
original_content = None
try:
    # Terminate only the harness-owned master, verified against its exact config.
    proc = Path("/proc") / str(previous_pid) / "cmdline"
    if proc.exists():
        assert str(service / "fpm.conf").encode() in proc.read_bytes()
        os.kill(previous_pid, signal.SIGTERM)
    for _ in range(50):
        if not listener_open():
            break
        time.sleep(0.1)
    assert not listener_open(), "Original FPM listener did not stop"
    original_content = wp("post", "get", str(post), "--field=post_content")
    wp("post", "update", str(post), "--post_content=")
    for enabled in (False, True):
        wp("plugin", "activate" if enabled else "deactivate", "easy-countdown")
        row = {"easy_countdown_active": enabled,
               "active_plugins": json.loads(wp("option", "get", "active_plugins", "--format=json")),
               "requests": []}
        result["states"].append(row)
        log_path = out / "fpm.log"
        log_offset = log_path.stat().st_size if log_path.exists() else 0
        environment = os.environ.copy()
        environment["PHP_INI_SCAN_DIR"] = "/etc/php/" + minor + "/cli/conf.d"
        try:
            with (out / "control-process.log").open("ab") as log:
                child = subprocess.Popen(
                    ["/usr/sbin/php-fpm" + minor, "-c", "/etc/php/" + minor + "/cli/php.ini",
                     "-y", str(service / "fpm.conf"), "-F"],
                    env=environment, stdout=log, stderr=log)
                for _ in range(50):
                    if listener_open():
                        break
                    if child.poll() is not None:
                        raise RuntimeError("Control FPM exited")
                    time.sleep(0.1)
                assert listener_open(), "Control FPM did not start"
                client = urllib.request.build_opener(
                    urllib.request.ProxyHandler({}), LocalRedirect(),
                    urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
                client.open(base + "/wp-login.php", timeout=40).close()
                data = urllib.parse.urlencode({"log": "ci-admin", "pwd": os.environ["ECD_BLOCK_PASSWORD"],
                    "wp-submit": "Log In", "redirect_to": base + "/wp-admin/", "testcookie": "1"}).encode()
                client.open(base + "/wp-login.php", data=data, timeout=40).close()
                for sample in range(1, 7):
                    request = {"sample": sample}
                    row["requests"].append(request)
                    start = time.monotonic()
                    try:
                        with client.open(base + "/wp-admin/post.php?post=" + str(post) + "&action=edit", timeout=40) as response:
                            body = response.read()
                            request.update(status=response.status, bytes=len(body),
                                body_sha256=hashlib.sha256(body).hexdigest(),
                                environment={key.lower(): value for key, value in response.headers.items()
                                             if key.lower().startswith("x-ect-test-")},
                                editor_bootstrap=b"wp.editPost.initializeEditor" in body or b"wp.editor.initializeEditor" in body)
                    except urllib.error.HTTPError as error:
                        request["status"] = error.code
                        error.close()
                    except Exception as error:
                        request["error_type"] = type(error).__name__
                    request["elapsed_ms"] = round((time.monotonic() - start) * 1000, 1)
                    if request.get("status") != 200 or not request.get("editor_bootstrap"):
                        break
        except Exception as error:
            row["error_type"] = type(error).__name__
        finally:
            if child is not None:
                child.terminate()
                try:
                    child.wait(timeout=5)
                except subprocess.TimeoutExpired:
                    child.kill()
                    child.wait(timeout=5)
                child = None
            with log_path.open("rb") as log:
                log.seek(log_offset)
                phase_log = log.read().decode("utf-8", "replace")
            row["native_crashes"] = phase_log.count("SIGSEGV")
except Exception as error:
    result["error_type"] = type(error).__name__
finally:
    try:
        wp("plugin", "activate", "easy-countdown")
        if original_content is not None:
            wp("post", "update", str(post), "--post_content=" + original_content)
    except Exception:
        result["restoration_failed"] = True
    (out / "failure-control.json").write_text(json.dumps(result, indent=2) + "\n")
