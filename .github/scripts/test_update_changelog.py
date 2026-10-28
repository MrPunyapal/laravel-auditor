import unittest

from update_changelog import update


class ChangelogTest(unittest.TestCase):
    def setUp(self):
        self.release = {'tag_name': 'v0.3.0', 'published_at': '2026-10-28T17:55:36Z', 'body': 'Generated notes'}
        self.base = '# Release Notes\n\n## [Unreleased](https://example.com/compare/v0.2.0...HEAD)\n\n'
        self.history = '## [v0.2.0](old)\n\nOld notes\n'

    def test_prepared_notes_are_promoted_and_history_preserved(self):
        result = update(self.base + '### Added\n\nPrepared notes\n\n' + self.history, self.release)
        self.assertIn('Prepared notes', result)
        self.assertNotIn('Generated notes', result)
        self.assertIn('compare/v0.3.0...HEAD', result)
        self.assertIn('compare/v0.2.0...v0.3.0) - 2026-10-28', result)
        self.assertTrue(result.endswith(self.history))

    def test_generated_notes_and_reruns(self):
        result = update(self.base + self.history, self.release)
        self.assertIn('Generated notes', result)
        self.assertEqual(update(result, self.release), result)

    def test_missing_notes_fail_without_changing_history(self):
        self.release['body'] = None
        with self.assertRaises(ValueError):
            update(self.base + self.history, self.release)

    def test_missing_heading_and_invalid_tag_fail(self):
        with self.assertRaises(ValueError):
            update(self.history, self.release)
        self.release['tag_name'] = 'Release title'
        with self.assertRaises(ValueError):
            update(self.base, self.release)


if __name__ == '__main__':
    unittest.main()
