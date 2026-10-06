import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const ROOT_DIR = path.resolve(__dirname, '..');

console.log('🤖 Running Part 2: HTTPS & Hostname Redirect Audit Suite...\n');

let total = 0;
let passed = 0;

function assertOk(condition, message) {
  total++;
  if (condition) {
    passed++;
    console.log(`  ✓ [Assertion ${total}] PASS: ${message}`);
  } else {
    console.error(`  ❌ [Assertion ${total}] FAIL: ${message}`);
    process.exit(1);
  }
}

const htaccessPath = path.join(ROOT_DIR, 'public/.htaccess');
assertOk(fs.existsSync(htaccessPath), 'public/.htaccess file exists');

const content = fs.readFileSync(htaccessPath, 'utf-8');

// 1. Preferred Production Origin URL check
assertOk(content.includes('https://www.runonconsole.com/$1'), '.htaccess targets preferred production origin https://www.runonconsole.com');

// 2. GET/HEAD 301 vs Non-GET 308 Method Preservation
assertOk(content.includes('RewriteCond %{REQUEST_METHOD} ^(GET|HEAD)$') && content.includes('[R=301,L,QSA]'), 'GET and HEAD requests receive HTTP 301 Permanent Redirect');
assertOk(content.includes('RewriteCond %{REQUEST_METHOD} !^(GET|HEAD)$') && content.includes('[R=308,L,QSA]'), 'POST, PUT and write requests receive HTTP 308 Permanent Redirect to preserve method & body');

// 3. Both Non-www and Www covered
assertOk(content.includes('RewriteCond %{HTTP_HOST} ^runonconsole\\.com$'), 'Non-www production hostname (runonconsole.com) handled');
assertOk(content.includes('RewriteCond %{HTTP_HOST} ^www\\.runonconsole\\.com$'), 'Www production hostname (www.runonconsole.com) handled');

// 4. Single-hop redirects (No chaining)
const matches301 = (content.match(/\[R=301,L,QSA\]/g) || []).length;
const matches308 = (content.match(/\[R=308,L,QSA\]/g) || []).length;
assertOk(matches301 >= 3, 'Includes single-hop 301 rules for non-www HTTP, non-www HTTPS, and www HTTP');
assertOk(matches308 >= 3, 'Includes single-hop 308 rules for POST requests on non-www HTTP, non-www HTTPS, and www HTTP');

// 5. Staging Environment Isolation (Staging does NOT redirect to production)
assertOk(content.includes('RewriteCond %{HTTP_HOST} ^staging\\.runonconsole\\.com$'), 'Staging domain staging.runonconsole.com handled separately');
assertOk(content.includes('https://staging.runonconsole.com/$1'), 'Staging HTTP -> HTTPS redirect preserves staging.runonconsole.com hostname');

console.log('\n============================================================');
console.log(`SUMMARY: All ${total} HTTPS & Hostname Redirect Assertions PASSED! 🎉`);
console.log('============================================================\n');
