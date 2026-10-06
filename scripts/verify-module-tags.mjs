import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const DIST_DIR = path.resolve(__dirname, '../dist');

let totalHtmlFiles = 0;
let exactOneTagFiles = 0;
let badTagFiles = 0;

function auditDirectory(dir) {
  const entries = fs.readdirSync(dir, { withFileTypes: true });
  for (const entry of entries) {
    const fullPath = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      auditDirectory(fullPath);
    } else if (entry.isFile() && entry.name.endsWith('.html')) {
      if (entry.name.startsWith('google') && entry.name.endsWith('.html')) {
        continue; // Skip Google verification token file
      }
      totalHtmlFiles++;
      const content = fs.readFileSync(fullPath, 'utf-8');
      const moduleMatch = content.match(/<script type="module"/gi) || [];
      const mainJsxMatch = content.match(/\/src\/main\.jsx/gi) || [];

      if (moduleMatch.length === 1 && mainJsxMatch.length === 0) {
        exactOneTagFiles++;
      } else {
        badTagFiles++;
        console.error(`❌ BAD FILE: ${fullPath} (Module tags: ${moduleMatch.length}, main.jsx: ${mainJsxMatch.length})`);
      }
    }
  }
}

auditDirectory(DIST_DIR);

console.log(`\n==============================================`);
console.log(`📊 MODULE TAG AUDIT: ${exactOneTagFiles}/${totalHtmlFiles} HTML Files Contain EXACTLY 1 Module Tag (0 /src/main.jsx)`);
console.log(`==============================================\n`);

if (badTagFiles > 0) {
  process.exit(1);
}
