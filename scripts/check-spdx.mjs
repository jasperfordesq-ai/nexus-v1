import { readdirSync, readFileSync } from 'fs';
import { join } from 'path';

function walk(dir, ext, results = []) {
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const full = join(dir, entry.name);
    if (entry.isDirectory() && !['node_modules', 'vendor', 'dist', '.git'].includes(entry.name)) {
      walk(full, ext, results);
    } else if (ext.some(e => entry.name.endsWith(e))) {
      results.push(full);
    }
  }
  return results;
}

const dirs = [
  { path: 'src', ext: ['.php'] },
  { path: 'httpdocs', ext: ['.php'] },
  { path: 'tests', ext: ['.php'] },
  { path: join('react-frontend', 'src'), ext: ['.ts', '.tsx'] },
];

let totalFiles = 0;
let totalWithHeader = 0;
let totalMissing = 0;

for (const { path: dirPath, ext } of dirs) {
  const files = walk(dirPath, ext);
  const missing = files.filter(f => {
    const content = readFileSync(f, 'utf-8');
    return !content.includes('SPDX-License-Identifier');
  });
  const withHeader = files.length - missing.length;

  console.log(`${dirPath}: ${files.length} files, ${withHeader} with header, ${missing.length} missing`);
  if (missing.length > 0) {
    missing.forEach(f => console.log(`  MISSING: ${f}`));
  }

  totalFiles += files.length;
  totalWithHeader += withHeader;
  totalMissing += missing.length;
}

console.log('');
console.log(`TOTAL: ${totalFiles} files, ${totalWithHeader} with header, ${totalMissing} missing`);

if (totalMissing === 0) {
  console.log('ALL FILES HAVE SPDX HEADERS');
} else {
  console.log(`WARNING: ${totalMissing} files are missing SPDX headers!`);
}
