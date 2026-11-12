#!/usr/bin/env python3
"""Check the pinned CLI with synthetic files and a loopback-only fake model API."""
import argparse
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import json
import os
from pathlib import Path
import shlex
import subprocess
import tempfile
import threading


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--claude", default="claude")
    args = parser.parse_args()
    with tempfile.TemporaryDirectory(prefix="ecd-review-cli-") as temporary:
        root = Path(temporary)
        project = root / "project"
        project.mkdir()
        inside = project / "public.txt"
        inside.write_text("ECD_SYNTHETIC_ALLOWED_CONTENT\n")
        outside = root / "outside.txt"
        outside.write_text("ECD_SYNTHETIC_OUTSIDE_CONTENT\n")
        (project / ".git").mkdir()
        git_config = project / ".git/config"
        git_config.write_text("ECD_SYNTHETIC_GIT_CONTENT\n")
        link = project / "external-link.txt"
        link.symlink_to(outside)
        shell_marker = root / "shell-was-executed"
        hook_marker = root / "hook-was-executed"
        mcp_marker = root / "project-mcp-was-executed"
        (project / ".claude").mkdir()
        (project / ".claude/settings.json").write_text(json.dumps({"hooks": {"PreToolUse": [
            {"matcher": "Read", "hooks": [{"type": "command", "command": f"touch {hook_marker}"}]}
        ]}}))
        (project / ".mcp.json").write_text(json.dumps({"mcpServers": {"untrusted": {
            "command": "sh", "args": ["-c", f"touch {mcp_marker}"]
        }}}))
        requests = []
        sent = False

        class Handler(BaseHTTPRequestHandler):
            def log_message(self, *_):
                pass

            def do_POST(self):
                nonlocal sent
                body = json.loads(self.rfile.read(int(self.headers["Content-Length"])))
                if "count_tokens" in self.path:
                    payload = json.dumps({"input_tokens": 1}).encode()
                    self.send_response(200)
                    self.send_header("Content-Type", "application/json")
                    self.send_header("Content-Length", str(len(payload)))
                    self.end_headers()
                    self.wfile.write(payload)
                    return
                requests.append(body)
                names = {tool["name"] for tool in body.get("tools", [])}
                if "Read" in names and not sent:
                    sent = True
                    calls = [
                        {"type": "tool_use", "id": "toolu_inside", "name": "Read", "input": {"file_path": str(inside)}},
                        {"type": "tool_use", "id": "toolu_outside", "name": "Read", "input": {"file_path": str(outside)}},
                        {"type": "tool_use", "id": "toolu_git", "name": "Read", "input": {"file_path": str(git_config)}},
                        {"type": "tool_use", "id": "toolu_link", "name": "Read", "input": {"file_path": str(link)}},
                        {"type": "tool_use", "id": "toolu_grep_outside", "name": "Grep", "input": {"pattern": "ECD_SYNTHETIC", "path": str(outside), "output_mode": "content"}},
                        {"type": "tool_use", "id": "toolu_grep_git", "name": "Grep", "input": {"pattern": "ECD_SYNTHETIC", "path": str(git_config), "output_mode": "content"}},
                        {"type": "tool_use", "id": "toolu_glob_outside", "name": "Glob", "input": {"pattern": "*.txt", "path": str(root)}},
                        {"type": "tool_use", "id": "toolu_shell", "name": "Bash", "input": {"command": f"touch {shell_marker}"}},
                    ]
                    stop = "tool_use"
                else:
                    calls = [{"type": "text", "text": "Synthetic boundary check complete."}]
                    stop = "end_turn"
                message = {"id": "msg_synthetic", "type": "message", "role": "assistant",
                           "model": body.get("model", "synthetic"), "content": calls,
                           "stop_reason": stop, "stop_sequence": None,
                           "usage": {"input_tokens": 1, "output_tokens": 1}}
                if not body.get("stream"):
                    payload = json.dumps(message).encode()
                    self.send_response(200)
                    self.send_header("Content-Type", "application/json")
                    self.send_header("Content-Length", str(len(payload)))
                    self.end_headers()
                    self.wfile.write(payload)
                    return
                events = [("message_start", {"type": "message_start", "message": {**message, "content": [], "stop_reason": None}})]
                for index, content in enumerate(calls):
                    block = {**content, "input": {}} if content["type"] == "tool_use" else {"type": "text", "text": ""}
                    delta = {"type": "input_json_delta", "partial_json": json.dumps(content["input"])} if content["type"] == "tool_use" else {"type": "text_delta", "text": content["text"]}
                    events.extend([
                        ("content_block_start", {"type": "content_block_start", "index": index, "content_block": block}),
                        ("content_block_delta", {"type": "content_block_delta", "index": index, "delta": delta}),
                        ("content_block_stop", {"type": "content_block_stop", "index": index}),
                    ])
                events.extend([
                    ("message_delta", {"type": "message_delta", "delta": {"stop_reason": stop, "stop_sequence": None}, "usage": {"output_tokens": 1}}),
                    ("message_stop", {"type": "message_stop"}),
                ])
                payload = "".join(f"event: {name}\ndata: {json.dumps(value)}\n\n" for name, value in events).encode()
                self.send_response(200)
                self.send_header("Content-Type", "text/event-stream")
                self.send_header("Content-Length", str(len(payload)))
                self.end_headers()
                self.wfile.write(payload)

        server = ThreadingHTTPServer(("127.0.0.1", 0), Handler)
        thread = threading.Thread(target=server.serve_forever, daemon=True)
        thread.start()
        # Exercise the actual committed flags rather than a parallel test policy.
        workflow = (Path(__file__).resolve().parents[1] / ".github/workflows/claude.yml").read_text()
        block = workflow.split("          claude_args: >-\n", 1)[1]
        lines = []
        for line in block.splitlines():
            if line.strip() and not line.startswith("            "):
                break
            lines.append(line.strip())
        flags = shlex.split(" ".join(lines))
        environment = dict(os.environ)
        environment.pop("CLAUDE_CODE_OAUTH_TOKEN", None)
        environment.pop("ANTHROPIC_AUTH_TOKEN", None)
        environment.update({
            "ANTHROPIC_API_KEY": "sk-ant-api03-synthetic-loopback-test-only",
            "ANTHROPIC_BASE_URL": f"http://127.0.0.1:{server.server_port}",
            "CLAUDE_CONFIG_DIR": str(root / "config"),
            "DISABLE_NONESSENTIAL_TRAFFIC": "1", "DISABLE_AUTOUPDATER": "1",
        })
        command = [args.claude, "--print", "--output-format", "json", *flags,
                   "--mcp-config", '{"mcpServers":{}}', "--no-session-persistence", "Check the synthetic files."]
        try:
            result = subprocess.run(command, cwd=project, env=environment, capture_output=True, text=True, timeout=60)
        finally:
            server.shutdown()
            server.server_close()
            thread.join(timeout=2)
        if result.returncode:
            raise RuntimeError(f"Pinned CLI failed ({result.returncode}): {result.stderr[-3000:]} {result.stdout[-2000:]}")
        tool_requests = [body for body in requests if any(tool["name"] == "Read" for tool in body.get("tools", []))]
        assert len(tool_requests) >= 2, "Expected actual tool results to reach the loopback API"
        names = {tool["name"] for tool in tool_requests[0]["tools"]}
        assert "Read" in names and not names.intersection({"Bash", "Write", "Edit", "Agent", "Task", "WebFetch"}), names
        observed = json.dumps(tool_requests[1:])
        assert "ECD_SYNTHETIC_ALLOWED_CONTENT" in observed, "The legitimate source read must work"
        for forbidden in ("ECD_SYNTHETIC_OUTSIDE_CONTENT", "ECD_SYNTHETIC_GIT_CONTENT"):
            assert forbidden not in observed + result.stdout + result.stderr, "Restricted content was exposed"
        assert not any(path.exists() for path in (shell_marker, hook_marker, mcp_marker)), "Unexpected code/MCP execution"
        results = {part["tool_use_id"]: part for body in tool_requests[1:] for message in body.get("messages", [])
                   if isinstance(message.get("content"), list) for part in message["content"] if part.get("type") == "tool_result"}
        for tool_id in ("toolu_outside", "toolu_git", "toolu_link", "toolu_shell", "toolu_grep_outside", "toolu_grep_git", "toolu_glob_outside"):
            assert results.get(tool_id, {}).get("is_error"), f"Expected explicit denial for {tool_id}"
        print("PASS pinned CLI: source read, outside/symlink/Git denials, absent shell/write tools, disabled project hooks/MCP")


if __name__ == "__main__":
    main()
