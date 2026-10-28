"""Promote prepared notes, or use release notes, without duplicating releases."""

import json
import os
import re
from pathlib import Path


def update(contents, release):
    tag = release['tag_name']
    if not re.fullmatch(r'v?\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?', tag):
        raise ValueError('Unsupported release tag')
    if re.search(r'^## \[' + re.escape(tag) + r'\]', contents, re.M):
        return contents
    heading = re.search(r'^## \[Unreleased\]\(([^\n)]+)\.\.\.HEAD\)\s*\n', contents, re.M)
    if heading is None:
        raise ValueError('Missing Unreleased comparison heading')
    start = heading.end()
    following = re.search(r'^## \[', contents[start:], re.M)
    end = start + following.start() if following else len(contents)
    notes = contents[start:end].strip() or (release.get('body') or '').strip()
    if not notes:
        raise ValueError('Release has no prepared notes or release body')
    previous = heading.group(1)
    compare = previous.rsplit('/', 1)[0] + '/' + tag
    date = release['published_at'][:10]
    replacement = (
        f'## [Unreleased]({compare}...HEAD)\n\n'
        f'## [{tag}]({previous}...{tag}) - {date}\n\n{notes}\n\n'
    )
    return contents[:heading.start()] + replacement + contents[end:]


if __name__ == '__main__':
    event = json.loads(Path(os.environ['GITHUB_EVENT_PATH']).read_text(encoding='utf-8'))
    path = Path('CHANGELOG.md')
    contents = path.read_text(encoding='utf-8')
    result = update(contents, event['release'])
    if result != contents:
        path.write_text(result, encoding='utf-8')
