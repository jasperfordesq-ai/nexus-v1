// Temporary script — fixes missing 't' in useCallback/useEffect/useMemo dep arrays
import { readFileSync, writeFileSync } from 'fs';
import { basename } from 'path';

const lines = readFileSync('src/t_deps_missing.txt', 'utf8').trim().split('\n');

// Group by file
const byFile = {};
for (const line of lines) {
  const lastColon = line.lastIndexOf(':');
  const filePath = line.substring(0, lastColon);
  const lineNum = parseInt(line.substring(lastColon + 1));
  if (!byFile[filePath]) byFile[filePath] = [];
  byFile[filePath].push(lineNum);
}

let totalFixed = 0;
let totalFiles = 0;

for (const [winPath, hookLines] of Object.entries(byFile)) {
  // Keep original Windows path for Node.js file I/O on Windows
  const unixPath = winPath;

  try {
    const content = readFileSync(unixPath, 'utf8');
    const fileLines = content.split('\n');
    let changed = false;

    for (const hookLine of hookLines) {
      // Scan forward from the hook line to find the closing dep array
      let fixed = false;
      for (let i = hookLine - 1; i < Math.min(hookLine + 80, fileLines.length) && !fixed; i++) {
        const line = fileLines[i];

        // Match single-line dep array: }, [dep1, dep2]); or }, []);
        const singleMatch = line.match(/^(\s*},?\s*\[)([^\]]*)(\]\s*[;),])/);
        if (singleMatch) {
          const deps = singleMatch[2].trim();
          const depList = deps ? deps.split(',').map(d => d.trim()).filter(Boolean) : [];

          if (!depList.includes('t')) {
            const newDeps = deps ? `${deps}, t` : 't';
            fileLines[i] = `${singleMatch[1]}${newDeps}${singleMatch[3]}`;
            changed = true;
            totalFixed++;
            fixed = true;
          } else {
            fixed = true; // already has t
          }
          break;
        }
      }
    }

    if (changed) {
      writeFileSync(unixPath, fileLines.join('\n'), 'utf8');
      console.log(`Fixed: ${basename(unixPath)}`);
      totalFiles++;
    }
  } catch (e) {
    console.error(`Error processing ${unixPath}: ${e.message}`);
  }
}

console.log(`\nTotal fixes: ${totalFixed} across ${totalFiles} files`);
