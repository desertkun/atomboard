#!/usr/bin/env python3
"""Check source formatting without rewriting PHP string literals."""

from pathlib import Path

root = Path(__file__).resolve().parent.parent
sources = [root / 'imgboard.php', root / 'settings.default.php']
sources += sorted((root / 'inc').glob('*.php'))
sources += sorted((root / 'dev').glob('*.php'))
errors = []

for path in sources:
    if path.name == 'captcha.php':  # Bundled third-party code.
        continue
    data = path.read_bytes()
    if not data.endswith(b'\n'):
        errors.append(f'{path.relative_to(root)}: missing final newline')
    if b'\r' in data:
        errors.append(f'{path.relative_to(root)}: CR line ending')
    for number, line in enumerate(data.splitlines(), 1):
        if line.rstrip(b' \t') != line:
            errors.append(f'{path.relative_to(root)}:{number}: trailing whitespace')

if errors:
    raise SystemExit('\n'.join(errors))
print('Source formatting OK')
