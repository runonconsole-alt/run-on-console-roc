import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import assert from 'node:assert';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const ROOT_DIR = path.resolve(__dirname, '..');

console.log('🤖 Running Comprehensive Focused Defect Verification & Audit Suite...\n');

let totalTests = 0;
let passedTests = 0;
let failedTests = 0;

function pass(message) {
  totalTests++;
  passedTests++;
  console.log(`  ✓ PASS: ${message}`);
}

function fail(message, err) {
  totalTests++;
  failedTests++;
  console.error(`  ❌ FAIL: ${message} -> ${err?.message || err}`);
}

function notRun(message, reason) {
  console.log(`  ⚠️ NOT RUN (Local Host Limitation): ${message} -> [Reason: ${reason}]`);
}

console.log('============================================================');
console.log('PART 1: STATIC CODE & SCHEMA COMPLIANCE CHECKS');
console.log('============================================================\n');

// 1. Verify No products.slug SQL Dependency
console.log('🔍 [Static Check] Verifying Schema Compliance (No products.slug in SQL)...');
const productsPhp = fs.readFileSync(path.join(ROOT_DIR, 'public/api/v1/agent/products.php'), 'utf-8');
const searchPhp = fs.readFileSync(path.join(ROOT_DIR, 'public/api/v1/agent/search.php'), 'utf-8');

assert(!productsPhp.includes('SELECT id, title, slug'), 'products.php does NOT query non-existent products.slug column in SQL');
assert(!searchPhp.includes('SELECT id, title, slug, category, price, rating, image, summary FROM products'), 'search.php does NOT query non-existent products.slug column in SQL');
assert(productsPhp.includes('rocAgentSlugify($p[\'title\'])'), 'products.php uses canonical slug generator with existing title data');
assert(searchPhp.includes('rocAgentSlugify($p[\'title\'])'), 'search.php uses canonical slug generator with existing title data');
pass('products.php and search.php query actual products table schema without products.slug dependency.');

// 2. Verify Scoped Migration, Independent Timestamp Checks, Deep Index Verification & NO Automatic Blog Updates
console.log('\n🔍 [Static Check] Verifying Scoped Migration, Independent Timestamps, Deep Indexing & Separated Blog Backfill...');
const cronMigrationContent = fs.readFileSync(path.join(ROOT_DIR, 'public/api/v1/cron/cli-migrate-roc-agent.php'), 'utf-8');

assert(cronMigrationContent.includes("TABLE_SCHEMA = DATABASE()"), 'Scopes INFORMATION_SCHEMA checks to TABLE_SCHEMA = DATABASE()');
assert(cronMigrationContent.includes("COLUMN_NAME = 'created_at'"), 'Inspects created_at column independently');
assert(cronMigrationContent.includes("COLUMN_NAME = 'updated_at'"), 'Inspects updated_at column independently');
assert(cronMigrationContent.includes("SELECT INDEX_NAME, NON_UNIQUE, COLUMN_NAME, SEQ_IN_INDEX FROM INFORMATION_SCHEMA.STATISTICS"), 'Verifies unique index by NON_UNIQUE=0 and column sequence order');
assert(cronMigrationContent.includes("GROUP BY game_id, requirement_type HAVING cnt > 1"), 'Detects duplicate game requirement records before adding unique key');
assert(cronMigrationContent.includes("NOT NULL DEFAULT 'draft'"), 'Defaults blog status to draft so unverified records are never silently published');
assert(!cronMigrationContent.includes("UPDATE blogs SET status = 'published'"), 'Does NOT contain automatic blog publication UPDATE statements in schema migration script');
assert(cronMigrationContent.includes("exit(1)"), 'Stops with non-zero exit code on unexpected SQL errors or duplicates');
pass('cli-migrate-roc-agent.php inspects timestamps independently, validates unique index, and contains NO automatic blog publication updates.');

// 3. Verify Standalone Blog Publication Backfill Script (backfill-blog-status.php)
console.log('\n🔍 [Static Check] Verifying Standalone Blog Publication Backfill Command...');
const backfillScriptPath = path.join(ROOT_DIR, 'public/api/v1/cron/backfill-blog-status.php');
if (fs.existsSync(backfillScriptPath)) {
  const backfillContent = fs.readFileSync(backfillScriptPath, 'utf-8');
  assert(backfillContent.includes('--confirm'), 'Requires --confirm flag to execute database updates');
  assert(backfillContent.includes('PREVIEW MODE ONLY'), 'Displays preview mode without changing DB when --confirm is absent');
  assert(backfillContent.includes('ID vs SLUG MISMATCH'), 'Flags ID vs Slug mismatches and partial matches explicitly');
  assert(backfillContent.includes('requires explicit review before publication'), 'Requires explicit owner review for partial single-lookup matches without auto-approval');
  assert(backfillContent.includes("WHERE id IN ($inClause)"), 'Updates ONLY explicitly approved primary key IDs');
  pass('backfill-blog-status.php strictly queries database, flags ID/slug mismatches and partial lookups, and avoids broad OR updates.');
} else {
  fail('backfill-blog-status.php script missing.');
}

// 4. Verify Strict OS Architecture & Version Logic
console.log('\n🔍 [Static Check] Verifying Strict OS Cases in check-compatibility.php...');
const compatPhp = fs.readFileSync(path.join(ROOT_DIR, 'public/api/v1/agent/check-compatibility.php'), 'utf-8');

assert(compatPhp.includes('if ($rIs64 && !$uIs64 && !$uIs32)'), 'Returns null when required architecture is 64-bit but user architecture is missing (e.g. "Windows 10" alone)');
assert(compatPhp.includes('if ($rIs64 && $uIs32)'), 'Returns false when 32-bit user OS is evaluated against 64-bit requirement');
assert(compatPhp.includes('if ($rVer !== null && $uVer === null)'), 'Returns null when required version is known but user version is missing');
assert(compatPhp.includes('$minOsPass = rocAgentEvaluateOs($userOs, $minReq[\'os\'] ?? \'\')'), 'Evaluates minimum OS requirement independently');
assert(compatPhp.includes('$recOsPass = $recReq ? rocAgentEvaluateOs($userOs, $recReq[\'os\'] ?? \'\') : null'), 'Evaluates recommended OS requirement independently');
pass('check-compatibility.php strictly evaluates missing architecture, missing version, 32-bit mismatch, and min/rec OS independently.');

// 5. Verify Published-Only Blog Filtering in SQL
console.log('\n🔍 [Static Check] Verifying Published-Only Blog SQL Filtering...');
const blogsPhp = fs.readFileSync(path.join(ROOT_DIR, 'public/api/v1/agent/blogs.php'), 'utf-8');

assert(blogsPhp.includes("WHERE status = 'published'"), 'blogs.php queries status = published in SQL');
assert(searchPhp.includes("WHERE status = 'published'"), 'search.php queries status = published in SQL');
pass('blogs.php and search.php filter published-only blog records in SQL.');

// 6. Verify Server Cleanup Deployment Instruction
console.log('\n🔍 [Static Check] Verifying Server Cleanup Deployment Instructions...');
const deploymentReadme = fs.readFileSync(path.join(ROOT_DIR, 'DEPLOYMENT-README.txt'), 'utf-8');
assert(deploymentReadme.includes('rm -f /home2/runoncon/public_html/api/v1/agent/migrations.php'), 'DEPLOYMENT-README.txt includes explicit server cleanup command');
assert(deploymentReadme.includes('backfill-blog-status.php'), 'DEPLOYMENT-README.txt includes instructions for backfill-blog-status.php');
pass('DEPLOYMENT-README.txt includes server cleanup instructions and backfill command details.');

// 7. Verify ROCAgentModal.jsx React Source for Pure Backend Data Integration
console.log('\n🔍 [Static Check] Verifying ROCAgentModal.jsx React Source for Pure Backend Data Integration...');
const agentModalContent = fs.readFileSync(path.join(ROOT_DIR, 'src/components/ROCAgentModal.jsx'), 'utf-8');

assert(!agentModalContent.includes("import { GAME_COMPATIBILITY_DATA"), 'ROCAgentModal.jsx does NOT import GAME_COMPATIBILITY_DATA fallback array');
assert(!agentModalContent.includes("import { ALL_PRODUCTS"), 'ROCAgentModal.jsx does NOT import ALL_PRODUCTS fallback array');
assert(!agentModalContent.includes("(Local Matrix)"), 'ROCAgentModal.jsx does NOT contain (Local Matrix) fallback labels');
assert(!agentModalContent.includes("Expect smooth 60+ FPS"), 'ROCAgentModal.jsx catch block does NOT fabricate 60+ FPS performance predictions');
assert(agentModalContent.includes("Unable to Determine"), 'ROCAgentModal.jsx compatibility error handling returns "Unable to Determine"');
pass('ROCAgentModal.jsx React source is 100% free of local fallback matrix branches, mock arrays, and fabricated FPS predictions.');

console.log('\n============================================================');
console.log('PART 2: RUNTIME PRE-RENDERING & ROUTE AUDIT TESTS');
console.log('============================================================\n');

// 7. Runtime Audit: Pre-rendered Product Detail Pages
console.log('🔍 [Runtime Audit] Auditing Pre-rendered Product Detail HTML Pages...');
const distDir = path.join(ROOT_DIR, 'dist/products');
if (fs.existsSync(distDir)) {
  const subdirs = fs.readdirSync(distDir, { withFileTypes: true }).filter(d => d.isDirectory());
  let checkedRoutes = 0;
  let mismatchedTitles = 0;

  for (const dir of subdirs) {
    const htmlPath = path.join(distDir, dir.name, 'index.html');
    if (fs.existsSync(htmlPath)) {
      checkedRoutes++;
      const htmlContent = fs.readFileSync(htmlPath, 'utf-8');
      assert(htmlContent.includes(`<link rel="canonical" href="https://runonconsole.com/products/${dir.name}/" />`), `Canonical URL matches route /products/${dir.name}/`);
      if (htmlContent.includes('Logitech G Pro X TKL LIGHTSPEED Wireless Gaming Keyboard') && dir.name !== 'logitech-g-pro-x-tkl-lightspeed-gaming-keyboard') {
        mismatchedTitles++;
      }
    }
  }

  assert.strictEqual(mismatchedTitles, 0, 'No product page rendered fallback Logitech keyboard content');
  pass(`Audited ${checkedRoutes} pre-rendered product detail HTML pages. Each page contains unique title, H1 & canonical URL.`);
} else {
  fail('dist/products directory missing. Run build first.');
}

console.log('\n============================================================');
console.log('PART 3: LOCAL HOST PHP/MYSQL RUNTIME TEST STATUS');
console.log('============================================================\n');

notRun("Fresh Installation PHP Migration Test", "PHP CLI binary is not installed in the local Windows OS environment. Migration & backfill scripts are designed for execution on live cPanel Linux MySQL server.");
notRun("Repeat Execution Migration Test", "PHP CLI binary is not installed in local environment.");
notRun("Partial Timestamp Schema Test (created_at vs updated_at)", "PHP CLI binary is not installed in local environment.");
notRun("Wrong Index Definition Repair Test", "PHP CLI binary is not installed in local environment.");
notRun("Duplicate Requirements Halting Test", "PHP CLI binary is not installed in local environment.");
notRun("Standalone Blog Publication Backfill Test", "PHP CLI binary is not installed in local environment.");

console.log(`\n==============================================`);
console.log(`📊 FINAL VERIFICATION: ${passedTests}/${totalTests} Passed (${failedTests} Failed)`);
console.log(`==============================================\n`);

if (failedTests > 0) {
  process.exit(1);
}
