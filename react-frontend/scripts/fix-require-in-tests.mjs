/**
 * fix-require-in-tests.mjs
 *
 * Fixes test files that use `const { api } = require('@/lib/api')` inside
 * test bodies, which fails in Vitest ESM because require() doesn't resolve
 * Vite path aliases (@/).
 *
 * Fix: add a top-level `import { api } from '@/lib/api'` and remove the
 * require() call from each test body.
 *
 * Run: node scripts/fix-require-in-tests.mjs
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const SRC_DIR = path.resolve(__dirname, '../src');

function findTestFiles(dir) {
  const results = [];
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) results.push(...findTestFiles(full));
    else if (/\.(test|spec)\.(tsx?|jsx?)$/.test(entry.name)) results.push(full);
  }
  return results;
}

const testFiles = findTestFiles(SRC_DIR);
let fixed = 0;

for (const filePath of testFiles) {
  let content = fs.readFileSync(filePath, 'utf-8');

  // Only process files that have require('@/lib/api') inside test bodies
  if (!content.includes("require('@/lib/api')") && !content.includes('require("@/lib/api")')) {
    continue;
  }

  // Check if already has a top-level import of api from @/lib/api
  const hasApiImport = /^import\s+.*\bapi\b.*from\s+['"]@\/lib\/api['"]/m.test(content);

  let newContent = content;

  // Add import if not present
  if (!hasApiImport) {
    // Find where to insert: after the last vi.mock('@/lib/api') line
    // or after the first import block
    const apiMockMatch = content.match(/vi\.mock\(['"]@\/lib\/api['"]/);
    if (apiMockMatch) {
      // Find the end of that vi.mock() call (closing semicolon line)
      const mockStart = content.indexOf(apiMockMatch[0]);
      // Find the next semicolon after the mock call end
      // Simple heuristic: find the line that ends with })); or });
      const afterMock = content.slice(mockStart);
      const mockEndMatch = afterMock.match(/\}\s*\)\s*\)\s*;|\}\s*\)\s*;/);
      if (mockEndMatch) {
        const insertAt = mockStart + mockEndMatch.index + mockEndMatch[0].length;
        newContent = content.slice(0, insertAt) + "\nimport { api } from '@/lib/api';" + content.slice(insertAt);
      } else {
        // Fallback: add after the first line of the mock
        const lineEnd = content.indexOf('\n', mockStart);
        newContent = content.slice(0, lineEnd + 1) + "import { api } from '@/lib/api';\n" + content.slice(lineEnd + 1);
      }
    } else {
      // No vi.mock found, add after first block of imports
      const lastImport = [...content.matchAll(/^import\s+/mg)].pop();
      if (lastImport) {
        const lineEnd = content.indexOf('\n', lastImport.index);
        newContent = content.slice(0, lineEnd + 1) + "import { api } from '@/lib/api';\n" + content.slice(lineEnd + 1);
      }
    }
  }

  // Remove all `const { api } = require('@/lib/api');` lines
  newContent = newContent.replace(/^\s*const\s+\{[^}]*\}\s*=\s*require\(['"]@\/lib\/api['"]\);\s*\n/gm, '');
  // Also handle without semicolon at end
  newContent = newContent.replace(/^\s*const\s+\{[^}]*\}\s*=\s*require\(['"]@\/lib\/api['"]\)[;\s]*\n/gm, '');

  if (newContent !== content) {
    fs.writeFileSync(filePath, newContent);
    console.log(`FIXED: ${path.relative(SRC_DIR, filePath)}`);
    fixed++;
  }
}

console.log(`\nDone: ${fixed} files fixed.`);
