"""
Exhaustive admin key audit.
Walks every .tsx file under react-frontend/src/admin,
extracts the useTranslation namespace + every t('...') call,
and verifies each key exists in the locale file.
Reports ALL missing keys — no per-file rules.
"""
import json
import re
import os
from pathlib import Path

ADMIN_DIR = Path('react-frontend/src/admin')
LOCALE_DIR = Path('react-frontend/public/locales/en')

def flatten(obj, prefix=''):
    out = {}
    for k, v in obj.items():
        p = f'{prefix}.{k}' if prefix else k
        if isinstance(v, dict):
            out.update(flatten(v, p))
        else:
            out[p] = v
    return out

# Load all English locale files
locales = {}
for fp in LOCALE_DIR.glob('*.json'):
    with open(fp, encoding='utf-8') as f:
        locales[fp.stem] = flatten(json.load(f))

# Walk admin files
missing = []  # (namespace, key, file, line)
dynamic_warnings = []

for tsx in ADMIN_DIR.rglob('*.tsx'):
    if '__tests__' in str(tsx) or '.test.tsx' in str(tsx):
        continue
    text = tsx.read_text(encoding='utf-8')
    lines = text.splitlines()

    # Find useTranslation('namespace') — may have multiple
    namespaces = set(re.findall(r"useTranslation\(\s*['\"]([\w_]+)['\"]", text))
    if not namespaces:
        continue

    # Default to first namespace for t() calls (simplification)
    default_ns = next(iter(namespaces))

    # Find all t('key'...) and t("key"...)
    for i, line in enumerate(lines, 1):
        # Static: t('foo.bar') or t('foo.bar', ...)
        for m in re.finditer(r"\bt\(\s*['\"]([^'\"\n`]+)['\"]", line):
            key = m.group(1)
            # Skip things that don't look like dotted keys (aria labels, etc.)
            if key.startswith('admin:') or key.startswith('admin_nav:') or key.startswith('jobs:'):
                ns, key = key.split(':', 1)
            else:
                ns = default_ns
            if ns not in locales:
                continue
            if key not in locales[ns]:
                # Check if it's a plural pattern like "foo_one" where "foo_other" exists
                base = re.sub(r'_(one|other|few|many|two|zero)$', '', key)
                if base != key and any(f'{base}_{s}' in locales[ns] for s in ['one','other']):
                    continue
                missing.append((ns, key, str(tsx.relative_to('.')), i))
        # Template literal: t(`foo.${x}`)
        for m in re.finditer(r"\bt\(\s*`([^`\n]+)`", line):
            template = m.group(1)
            if '${' in template:
                dynamic_warnings.append((default_ns, template, str(tsx.relative_to('.')), i))

# Print results
by_ns = {}
seen = set()
for ns, k, f, line in missing:
    if (ns, k) in seen:
        continue
    seen.add((ns, k))
    by_ns.setdefault(ns, []).append((k, f, line))

print(f'Missing keys: {sum(len(v) for v in by_ns.values())} unique')
for ns in sorted(by_ns):
    print(f'\n[{ns}] {len(by_ns[ns])} missing:')
    for k, f, line in sorted(by_ns[ns]):
        print(f'  {k}  ({f}:{line})')

if dynamic_warnings:
    print(f'\nDynamic key patterns (manual check needed): {len(dynamic_warnings)}')
    seen_d = set()
    for ns, t, f, line in dynamic_warnings:
        if (ns, t) in seen_d: continue
        seen_d.add((ns, t))
        print(f'  [{ns}] {t}  ({f}:{line})')
