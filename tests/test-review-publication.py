#!/usr/bin/env python3
"""Completed-result validation and data-only GitHub publication."""
import importlib.util
import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import patch

SPEC = importlib.util.spec_from_file_location('publication', Path(__file__).resolve().parents[1] / 'scripts/publish-claude-review.py')
publication = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(publication)
HEAD = 'a' * 40


def success(text='No blocking findings.'):
    return [{'type': 'result', 'subtype': 'success', 'is_error': False, 'result': text}]


class PublicationTests(unittest.TestCase):
    def test_actual_result_is_bound_to_immutable_head(self):
        body = publication.review_body([{'type': 'assistant', 'message': 'intermediate'}, *success()], HEAD)
        self.assertIn(HEAD, body)
        self.assertIn('No blocking findings.', body)
        self.assertIn('not a test pass or human approval', body)
        self.assertNotIn('intermediate', body)

    def test_incomplete_failed_or_ambiguous_results_never_publish(self):
        for messages in ([], {}, success() * 2, [{'type': 'result', 'subtype': 'error_max_turns', 'is_error': True}],
                         [{**success()[0], 'is_error': True}], success(''), success(' '), success('x' * 60000)):
            with self.subTest(messages=str(messages)[:100]), self.assertRaises(ValueError):
                publication.review_body(messages, HEAD)
        with self.assertRaises(ValueError):
            publication.review_body(success(), 'main')

    def test_only_exact_regular_action_output_is_read(self):
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            output = root / 'claude-execution-output.json'
            output.write_text(json.dumps(success()))
            self.assertEqual(publication.read_execution(output, root), success())
            other = root / 'other.json'; other.write_text(json.dumps(success()))
            with self.assertRaises(ValueError):
                publication.read_execution(other, root)
            output.unlink(); output.symlink_to(other)
            with self.assertRaises(ValueError):
                publication.read_execution(output, root)

    def test_oversize_record_fails_before_parsing(self):
        with tempfile.TemporaryDirectory() as temporary:
            output = Path(temporary) / 'claude-execution-output.json'
            output.write_text(json.dumps(success()))
            with patch.object(publication, 'MAX_EXECUTION_BYTES', 1), self.assertRaises(ValueError):
                publication.read_execution(output, temporary)

    def test_review_is_json_data_on_stdin(self):
        text = 'Literal $(touch /never-run) and `git diff --output=x`\nSecond line.'
        response = subprocess.CompletedProcess([], 0, json.dumps({'html_url': 'https://github.com/example/repo/pull/39#issuecomment-1'}), '')
        with patch.object(publication.subprocess, 'run', return_value=response) as run:
            publication.publish('example/repo', 39, text)
        positional, keyword = run.call_args
        self.assertEqual(positional[0], ['gh', 'api', '--method', 'POST', 'repos/example/repo/issues/39/comments', '--input', '-'])
        self.assertEqual(json.loads(keyword['input']), {'body': text})
        self.assertNotIn('shell', keyword)

    def test_scope_validation_precedes_any_request(self):
        with patch.object(publication.subprocess, 'run') as run:
            for repository, number in (('../other/repo', 39), ('example/repo', 0), ('example/repo', True)):
                with self.assertRaises(ValueError):
                    publication.publish(repository, number, 'review')
            run.assert_not_called()

    def test_main_binds_action_result_to_prepared_pr(self):
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            context = root / '.email-countdown-review-synthetic'; context.mkdir()
            metadata = context / 'context.json'
            metadata.write_text(json.dumps({'number': 39, 'head_sha': HEAD}))
            execution = root / 'claude-execution-output.json'
            execution.write_text(json.dumps(success()))
            environment = {'GITHUB_WORKSPACE': str(root), 'REVIEW_CONTEXT': context.name,
                           'REVIEW_PR': '39', 'REVIEW_REPOSITORY': 'example/repo',
                           'CLAUDE_EXECUTION_FILE': str(execution), 'RUNNER_TEMP': str(root)}
            with patch.dict(os.environ, environment), patch.object(publication, 'publish', return_value='synthetic-comment') as publish:
                publication.main()
                self.assertEqual(publish.call_args.args[:2], ('example/repo', 39))
                self.assertIn(HEAD, publish.call_args.args[2])
                publish.reset_mock()
                metadata.write_text(json.dumps({'number': 40, 'head_sha': HEAD}))
                with self.assertRaises(ValueError):
                    publication.main()
                publish.assert_not_called()


if __name__ == '__main__':
    unittest.main(verbosity=2)
