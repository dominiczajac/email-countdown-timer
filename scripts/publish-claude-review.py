#!/usr/bin/env python3
"""Publish only a completed model result, as data, from the trusted checkout."""
import json
import os
from pathlib import Path
import re
import subprocess

MAX_EXECUTION_BYTES = 32 * 1024 * 1024


def read_execution(filename, temporary):
    path = Path(filename)
    expected = Path(temporary).resolve() / 'claude-execution-output.json'
    if path.is_symlink() or path.resolve() != expected or not path.is_file():
        raise ValueError('Expected the pinned action execution file')
    if path.stat().st_size > MAX_EXECUTION_BYTES:
        raise ValueError('Execution record exceeds the publication budget')
    return json.loads(path.read_text(encoding='utf-8'))


def review_body(messages, head):
    if not isinstance(head, str) or not re.fullmatch(r'[0-9a-f]{40}', head):
        raise ValueError('Expected the immutable reviewed head')
    if not isinstance(messages, list):
        raise ValueError('Expected the pinned action message array')
    results = [item for item in messages if isinstance(item, dict) and item.get('type') == 'result']
    if len(results) != 1:
        raise ValueError('Expected one completed review result')
    result = results[0]
    text = result.get('result')
    if (result.get('subtype') != 'success' or result.get('is_error') is not False
            or not isinstance(text, str) or not text.strip()):
        raise ValueError('Refusing to publish an incomplete or failed review')
    body = ('<!-- email-countdown-timer-claude-review:v1 -->\n'
            '**Claude static review** (published by the workflow)\n\n'
            f'**Reviewed head:** `{head}`\n\n' + text.strip()
            + '\n\nStatic automated review; not a test pass or human approval.\n')
    if len(body) > 60000:
        raise ValueError('Review exceeds the comment budget; manual inspection is required')
    return body


def publish(repository, number, body):
    if not re.fullmatch(r'[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+', repository) or type(number) is not int or number < 1:
        raise ValueError('Expected a repository and positive PR number')
    # Model text is JSON on stdin, never a shell command or an argument fragment.
    result = subprocess.run(['gh', 'api', '--method', 'POST',
                             f'repos/{repository}/issues/{number}/comments', '--input', '-'],
                            input=json.dumps({'body': body}), text=True,
                            capture_output=True, check=True, timeout=30)
    return json.loads(result.stdout)['html_url']


def main():
    workspace = Path(os.environ['GITHUB_WORKSPACE']).resolve()
    name = os.environ['REVIEW_CONTEXT']
    if Path(name).name != name or not name.startswith('.email-countdown-review-'):
        raise ValueError('Expected the trusted prepared context directory')
    context = workspace / name
    if context.is_symlink() or not context.is_dir() or not context.resolve().is_relative_to(workspace):
        raise ValueError('Review context must remain in the trusted workspace')
    metadata_file = context / 'context.json'
    if metadata_file.is_symlink() or not metadata_file.is_file():
        raise ValueError('Expected regular prepared review metadata')
    metadata = json.loads(metadata_file.read_text(encoding='utf-8'))
    number = int(os.environ['REVIEW_PR'])
    if type(metadata['number']) is not int or metadata['number'] != number:
        raise ValueError('Review context does not match the requested PR')
    messages = read_execution(os.environ['CLAUDE_EXECUTION_FILE'], os.environ['RUNNER_TEMP'])
    print(publish(os.environ['REVIEW_REPOSITORY'], number, review_body(messages, metadata['head_sha'])))


if __name__ == '__main__':
    main()
