import fs from 'fs';
import path from 'path';

const scratchDir = path.resolve('scratch/test_html_inspect');
const htmlFiles = [];
function findHtml(d) {
  for (const f of fs.readdirSync(d, { withFileTypes: true })) {
    const fp = path.join(d, f.name);
    if (f.isDirectory()) findHtml(fp);
    else if (f.name.endsWith('.html')) htmlFiles.push(fp);
  }
}
findHtml(scratchDir);

let failCount = 0;
for (const hf of htmlFiles) {
  const content = fs.readFileSync(hf, 'utf-8');
  const matches = content.match(/<meta name="robots"[^>]*>/gi);
  if (!matches || matches.length !== 1 || !matches[0].includes('noindex, nofollow')) {
    failCount++;
    console.log('Mismatch in:', path.relative(scratchDir, hf), 'Matches:', matches);
  }
}
console.log(`Total HTML files: ${htmlFiles.length}, Mismatches: ${failCount}`);
