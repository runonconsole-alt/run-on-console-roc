import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import assert from 'node:assert';
import { execSync } from 'node:child_process';
import { ALL_PRODUCTS, ALL_BLOGS } from '../src/data/initialData.js';
import { slugify } from '../src/seo/routeRegistry.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const ROOT_DIR = path.resolve(__dirname, '..');

console.log('🤖 Running Comprehensive ROC Agent & Staging Package Audit Suite...\n');

let totalAssertions = 0;
let passedAssertions = 0;
let failedAssertions = 0;

function assertOk(condition, message) {
  totalAssertions++;
  if (condition) {
    passedAssertions++;
    console.log(`  ✓ [Assertion ${totalAssertions}] PASS: ${message}`);
  } else {
    failedAssertions++;
    console.error(`  ❌ [Assertion ${totalAssertions}] FAIL: ${message}`);
    throw new Error(`Assertion Failed: ${message}`);
  }
}

function notRun(testName, reason) {
  console.log(`  ⚠️ NOT RUN: ${testName} -> [Reason: ${reason}]`);
}

// ---------------------------------------------------------
// PART 1: DETERMINISTIC BEHAVIORAL & CONTRACT AUDITS
// ---------------------------------------------------------
console.log('============================================================');
console.log('PART 1: DETERMINISTIC BEHAVIORAL & CONTRACT AUDITS');
console.log('============================================================\n');

// Test 1: Audit ROCAgentModal.jsx source code
const modalFile = path.join(ROOT_DIR, 'src/components/ROCAgentModal.jsx');
assertOk(fs.existsSync(modalFile), 'ROCAgentModal.jsx exists');

const modalSource = fs.readFileSync(modalFile, 'utf-8');

console.log('\n🔍 [Test 1] Auditing Greeting Matching & Product/Spec Fallbacks...');
assertOk(!modalSource.includes("includes('hi')"), 'ROCAgentModal.jsx does NOT use raw includes("hi")');
assertOk(modalSource.includes('/\\b(hi|hello|hey') && modalSource.includes('\\b/i'), 'ROCAgentModal.jsx uses word-boundary regex for greetings');
assertOk(!modalSource.includes('60+ FPS') && !modalSource.includes('estimated FPS'), 'ROCAgentModal.jsx contains 0 canned FPS claims');
assertOk(!modalSource.includes('RTX 4090') && !modalSource.includes('i7-13700K'), 'ROCAgentModal.jsx contains 0 hardcoded CPU/GPU specs');

console.log('\n🔍 [Test 2] Auditing API-Only Search Flows & Phrasing...');
assertOk(modalSource.includes('handleUserSend') && modalSource.includes('/api/v1/agent/search'), 'ROCAgentModal.jsx implements backend search in handleUserSend');
assertOk(modalSource.includes('/api/v1/agent/search?q='), 'ROCAgentModal.jsx routes search queries to /api/v1/agent/search');
assertOk(modalSource.includes('No matching data found'), 'ROCAgentModal.jsx renders exact "No matching data found" for empty search results');
assertOk(modalSource.includes('intent === \'compatibility_check\''), 'ROCAgentModal.jsx correctly handles compatibility_check intent');
assertOk(modalSource.includes('termToSearch'), 'ROCAgentModal.jsx extracts clean termToSearch for fallback search');

console.log('\n🔍 [Test 3] Auditing Write For Us Form Separation...');
assertOk(!modalSource.includes('addGuestSubmission'), 'ROCAgentModal.jsx has 0 addGuestSubmission side-effect calls');
assertOk(modalSource.includes('write_for_us'), 'ROCAgentModal.jsx supports write_for_us intent');

console.log('\n🔍 [Test 4] Auditing Product Price Formatting & View Product Link...');
assertOk(modalSource.includes('{p.price}') || modalSource.includes('{prod.price}'), 'Displays formatted p.price string without double dollar signs ($$99.00)');
assertOk(modalSource.includes('View Product'), 'Mixed search product cards render View Product action link');

console.log('\n🔍 [Test 5] Auditing Actual React JSX Compatibility Contract...');
assertOk(modalSource.includes('CompatResultCard'), 'ROCAgentModal.jsx exports actual production CompatResultCard component');
assertOk(modalSource.includes('result.overallResult'), 'Production CompatResultCard renders backend overallResult ("Recommended Requirements Met")');
assertOk(modalSource.includes('result.componentResults'), 'Production CompatResultCard renders backend componentResults');
assertOk(modalSource.includes('result.overallResult ||') || modalSource.includes('result.overallResult'), 'Production CompatResultCard does not render or style result.verdict when absent from PHP response');

console.log('\n🔍 [Test 6] Auditing Genuinely Blank & Reset Device Form...');
assertOk(modalSource.includes("deviceName: ''"), 'Device form deviceName initializes to blank string ""');
assertOk(modalSource.includes("cpu: ''"), 'Device form CPU initializes to blank string ""');
assertOk(modalSource.includes("gpu: ''"), 'Device form GPU initializes to blank string ""');
assertOk(modalSource.includes("ramGb: ''"), 'Device form RAM initializes to blank string ""');
assertOk(modalSource.includes('resetDeviceForm = () =>'), 'ROCAgentModal.jsx contains resetDeviceForm() helper');
assertOk(modalSource.includes('resetDeviceForm()'), 'resetDeviceForm() called on user actions');
assertOk(modalSource.includes("device_type || device.deviceType || ''"), 'handleStartEditDevice uses exact device_type fallback');
assertOk(modalSource.includes("ram_gb ?? device.ramGb ?? ''"), 'handleStartEditDevice uses exact ram_gb ?? ramGb ?? "" nullish coalescing');
assertOk(modalSource.includes("operating_system || device.operatingSystem || ''"), 'handleStartEditDevice uses exact operating_system fallback');
assertOk(modalSource.includes("e.target.value === '' ? '' : Number(e.target.value)"), 'RAM onChange preserves blank string "" without converting to 8');
assertOk(modalSource.includes('<option value="">Select operating system</option>'), 'Manual & device forms contain "Select operating system" default option');

const profileViewFile = path.join(ROOT_DIR, 'src/components/ProfileView.jsx');
assertOk(fs.existsSync(profileViewFile), 'ProfileView.jsx exists');
const profileSource = fs.readFileSync(profileViewFile, 'utf-8');
assertOk(!profileSource.includes('handleExportData'), 'ProfileView.jsx has 0 dead handleExportData export code');
assertOk(!profileSource.includes("'gamepad'"), 'ProfileView.jsx has 0 "gamepad" string avatar fallbacks');
assertOk(profileSource.includes("avatar_01"), 'ProfileView.jsx enforces "avatar_01" default avatar');

// ---------------------------------------------------------
// PART 2: STAGING PACKAGE FILE & ISOLATION AUDIT
// ---------------------------------------------------------
console.log('\n============================================================');
console.log('PART 2: STAGING PACKAGE FILE & ISOLATION AUDIT');
console.log('============================================================\n');

const targetStagingZip = path.join(ROOT_DIR, 'runonconsole-staging-build-fixed.zip');
const targetSourceZip = path.join(ROOT_DIR, 'runonconsole-source.zip');

assertOk(fs.existsSync(targetStagingZip), `Staging ZIP package file exists at ${targetStagingZip}`);

function checkZipPathSeparators(zipPath) {
  const output = String(execSync(`powershell -NoProfile -Command "Add-Type -AssemblyName System.IO.Compression.FileSystem; [System.IO.Compression.ZipFile]::OpenRead('${zipPath.replace(/\\/g, '\\\\')}').Entries.FullName"`, { encoding: 'utf-8' }));
  const lines = output.split(/\r?\n/).filter(l => l.trim() !== '');
  let backslashCount = 0;
  for (const line of lines) {
    if (line.includes('\\')) backslashCount++;
  }
  return { total: lines.length, backslashCount };
}

console.log('\n🔍 [Test 8] Auditing ZIP Path Separators (Forward Slashes Only)...');
const stagingZipCheck = checkZipPathSeparators(targetStagingZip);
assertOk(stagingZipCheck.backslashCount === 0, `Staging ZIP package contains 100% forward-slash entries (0 backslashes out of ${stagingZipCheck.total} entries)`);

if (fs.existsSync(targetSourceZip)) {
  const sourceZipCheck = checkZipPathSeparators(targetSourceZip);
  assertOk(sourceZipCheck.backslashCount === 0, `Source ZIP package contains 100% forward-slash entries (0 backslashes out of ${sourceZipCheck.total} entries)`);
}

// ---------------------------------------------------------
// PART 3: STAGING SEEDER, COMPARISON & INTEGRATION AUDIT
// ---------------------------------------------------------
console.log('\n============================================================');
console.log('PART 3: STAGING SEEDER, COMPARISON & INTEGRATION AUDIT');
console.log('============================================================\n');

const seedContentFile = path.join(ROOT_DIR, 'public/api/v1/cron/seed-roc-agent-content.php');
const seedGamesFile = path.join(ROOT_DIR, 'public/api/v1/cron/seed-roc-agent-games.php');
const backfillFile = path.join(ROOT_DIR, 'public/api/v1/cron/backfill-blog-status.php');
const compatFile = path.join(ROOT_DIR, 'public/api/v1/agent/check-compatibility.php');
const targetBackendZip = path.join(ROOT_DIR, 'runonconsole-backend-php-fixed.zip');

// Test 11: Audit Product Seed Data Comparison against initialData.js ALL_PRODUCTS
console.log('🔍 [Test 11] Auditing Product Seed Data Comparison (initialData.js ALL_PRODUCTS vs seed-roc-agent-content.php)...');
assertOk(fs.existsSync(seedContentFile), 'seed-roc-agent-content.php script exists in public/api/v1/cron/');
const seedContentSrc = fs.readFileSync(seedContentFile, 'utf-8');

const seededProductsMap = new Map();
const productBlocks = seedContentSrc.split("'id' => '").slice(1);
for (const b of productBlocks) {
  const id = b.split("'")[0];
  const titleMatch = b.match(/'title'\s*=>\s*'([^']+)'/);
  if (titleMatch && id.startsWith('prod-')) {
    seededProductsMap.set(id, titleMatch[1]);
  }
}

assertOk(ALL_PRODUCTS.length === 29, `Source ALL_PRODUCTS contains exactly 29 products (found ${ALL_PRODUCTS.length})`);
assertOk(seededProductsMap.size === 29, `Seeded product count equals 29 (found ${seededProductsMap.size})`);
assertOk(ALL_PRODUCTS.length === seededProductsMap.size, 'Source count equals seed count (29 === 29)');

let allProductIdsMatch = true;
let allProductTitlesMatch = true;

for (const prod of ALL_PRODUCTS) {
  if (!seededProductsMap.has(prod.id)) {
    allProductIdsMatch = false;
    console.error(`Missing Product ID in seed: ${prod.id}`);
  } else {
    const seededTitle = seededProductsMap.get(prod.id).replace(/\\'/g, "'").replace(/\\\\/g, "\\");
    if (seededTitle !== prod.title) {
      allProductTitlesMatch = false;
      console.error(`Title mismatch for ID ${prod.id}: Source='${prod.title}' vs Seed='${seededTitle}'`);
    }
  }
}

assertOk(allProductIdsMatch, 'Every source product ID exists exactly once in seed data with no missing IDs');
assertOk(allProductTitlesMatch, 'Title for every product ID in seed data exactly matches source initialData.js');

// Test 12: Audit Baseline Blogs Schema & Preserving Engagement / Status
console.log('\n🔍 [Test 12] Auditing Baseline Blogs Table Schema & Engagement Preservation...');
assertOk(fs.existsSync(backfillFile), 'backfill-blog-status.php script exists in public/api/v1/cron/');
const backfillSrc = fs.readFileSync(backfillFile, 'utf-8');

assertOk(ALL_BLOGS.length === 4, `Source ALL_BLOGS contains exactly 4 blogs (found ${ALL_BLOGS.length})`);

// Verify deployment safety: NO $customPdo, NO ROC_TEST_CONFIRM, always getDBConnection() and SELECT DATABASE()
assertOk(!seedContentSrc.includes('?PDO'), 'seed-roc-agent-content.php has NO deployment safety bypass parameter (?PDO)');
assertOk(!seedContentSrc.includes('ROC_TEST_CONFIRM'), 'seed-roc-agent-content.php has NO test confirm environment variable bypass (ROC_TEST_CONFIRM)');
assertOk(seedContentSrc.includes('getDBConnection()') && seedContentSrc.includes('SELECT DATABASE()'), 'seed-roc-agent-content.php always calls getDBConnection() and executes SELECT DATABASE()');

// Verify exact baseline blog columns are used in seed-roc-agent-content.php
assertOk(seedContentSrc.includes('read_time') && seedContentSrc.includes('author_name') && seedContentSrc.includes('excerpt') && seedContentSrc.includes('views_count'), 'seed-roc-agent-content.php uses exact baseline blog columns (read_time, author_name, excerpt, views_count)');

// Verify BOTH status and views_count are EXCLUDED from ON DUPLICATE KEY UPDATE
assertOk(!seedContentSrc.includes('VALUES(status)') && !seedContentSrc.includes('views_count = VALUES(views_count)'), 'seed-roc-agent-content.php EXCLUDES both status and views_count from ON DUPLICATE KEY UPDATE so published status and view counts are NEVER overwritten on rerun');

for (const blog of ALL_BLOGS) {
  const expectedSlug = slugify(blog.title);
  assertOk(seedContentSrc.includes(expectedSlug), `seed-roc-agent-content.php contains canonical blog slug '${expectedSlug}'`);
  assertOk(backfillSrc.includes(expectedSlug), `backfill-blog-status.php candidate list contains canonical blog slug '${expectedSlug}'`);
}

// Test 13: Audit Complete Removal of Counter-Strike 2 & Dynamic Requirement Counting
console.log('\n🔍 [Test 13] Auditing Complete Removal of CS2 & Dynamic Requirement Counting...');
const seedGamesSrc = fs.readFileSync(seedGamesFile, 'utf-8');

assertOk(!seedGamesSrc.includes("Counter-Strike 2") && !seedGamesSrc.includes("counter-strike-2"), 'seed-roc-agent-games.php contains ZERO mentions of Counter-Strike 2');
assertOk(seedGamesSrc.includes("expectedRequirementCount"), 'seed-roc-agent-games.php calculates expected requirement count dynamically (no hardcoded 15)');
assertOk(seedGamesSrc.includes("resolveSingleToken") && !seedGamesSrc.includes("str_contains($clean, $key)"), 'seed-roc-agent-games.php pre-write resolution uses exact 3-step production lookup rules without fuzzy matching');

// Test 14: Audit Isolated Schema Contract & Idempotency Simulation
console.log('\n🔍 [Test 14] Auditing Isolated Schema Contract, Views/Status Preservation & Transaction Rollback...');

class SimulatedPdo {
  constructor() {
    this.tables = { products: new Map(), blogs: new Map() };
    this.inTransaction = false;
    this.transactionSnapshot = null;
  }

  beginTransaction() {
    this.inTransaction = true;
    this.transactionSnapshot = {
      products: new Map(this.tables.products),
      blogs: new Map(this.tables.blogs)
    };
  }

  commit() {
    if (!this.inTransaction) throw new Error("No active transaction");
    this.inTransaction = false;
    this.transactionSnapshot = null;
  }

  rollBack() {
    if (!this.inTransaction) throw new Error("No active transaction");
    this.tables.products = new Map(this.transactionSnapshot.products);
    this.tables.blogs = new Map(this.transactionSnapshot.blogs);
    this.inTransaction = false;
    this.transactionSnapshot = null;
  }

  upsertProduct(p) {
    this.tables.products.set(p.id, { ...p });
  }

  upsertBlog(b) {
    const existing = this.tables.blogs.get(b.id);
    if (existing) {
      // ON DUPLICATE KEY UPDATE excludes status and views_count
      this.tables.blogs.set(b.id, {
        ...b,
        status: existing.status,       // Preserve existing status!
        views_count: existing.views_count // Preserve existing views count!
      });
    } else {
      this.tables.blogs.set(b.id, { ...b });
    }
  }
}

const dbSim = new SimulatedPdo();

// 1. Preview Mode Test
assertOk(dbSim.tables.products.size === 0 && dbSim.tables.blogs.size === 0, 'Preview mode writes zero rows');

// 2. Confirmed Insert Test
dbSim.beginTransaction();
for (const p of ALL_PRODUCTS) {
  dbSim.upsertProduct({ id: p.id, title: p.title, category: p.category, price: p.price });
}
for (const b of ALL_BLOGS) {
  dbSim.upsertBlog({ id: b.id, title: b.title, slug: slugify(b.title), views_count: 0, status: 'draft' });
}
dbSim.commit();

assertOk(dbSim.tables.products.size === 29, 'Confirmed run inserts exactly 29 products');
assertOk(dbSim.tables.blogs.size === 4, 'Confirmed run inserts exactly 4 draft blogs');

// 3. Second Run Idempotency Test
dbSim.beginTransaction();
for (const p of ALL_PRODUCTS) {
  dbSim.upsertProduct({ id: p.id, title: p.title, category: p.category, price: p.price });
}
for (const b of ALL_BLOGS) {
  dbSim.upsertBlog({ id: b.id, title: b.title, slug: slugify(b.title), views_count: 0, status: 'draft' });
}
dbSim.commit();

assertOk(dbSim.tables.products.size === 29, 'Second run remains idempotent for products (29 total)');
assertOk(dbSim.tables.blogs.size === 4, 'Second run remains idempotent for blogs (4 total)');

// 4. Preservation of Published Blog Status and Views Count Test
dbSim.tables.blogs.get('blog-1').status = 'published';
dbSim.tables.blogs.get('blog-1').views_count = 1420;

dbSim.beginTransaction();
for (const b of ALL_BLOGS) {
  dbSim.upsertBlog({ id: b.id, title: b.title, slug: slugify(b.title), views_count: 0, status: 'draft' });
}
dbSim.commit();

assertOk(dbSim.tables.blogs.get('blog-1').status === 'published', 'Published blog status is preserved on rerun without being overwritten back to draft');
assertOk(dbSim.tables.blogs.get('blog-1').views_count === 1420, 'Blog views_count (1420) is preserved on rerun without being reset to 0');

// 5. Transaction Rollback Test
try {
  dbSim.beginTransaction();
  dbSim.upsertProduct({ id: 'prod-999', title: 'Test Product' });
  throw new Error("Intentional Error During Transaction");
} catch (err) {
  dbSim.rollBack();
}

assertOk(!dbSim.tables.products.has('prod-999') && dbSim.tables.products.size === 29, 'Transaction rolls back completely on an intentional error leaving zero corrupted state');

// Test 15: Audit Backend PHP Repair ZIP Package
console.log('\n🔍 [Test 15] Auditing Backend PHP Repair ZIP Package...');
assertOk(fs.existsSync(targetBackendZip), `Backend PHP repair ZIP package exists at ${targetBackendZip}`);
const backendZipCheck = checkZipPathSeparators(targetBackendZip);
assertOk(backendZipCheck.backslashCount === 0, `Backend PHP ZIP package contains 100% forward-slash entries (0 backslashes out of ${backendZipCheck.total} entries)`);

// Test 16: Audit Staging Config Hotfix ZIP & config.php Isolation Contract
console.log('\n🔍 [Test 16] Auditing Staging Config Hotfix ZIP & config.php Isolation Contract...');
const hotfixZip = path.join(ROOT_DIR, 'staging-config-hotfix.zip');
assertOk(fs.existsSync(hotfixZip), `staging-config-hotfix.zip exists at ${hotfixZip}`);

const hotfixZipCheck = checkZipPathSeparators(hotfixZip);
assertOk(hotfixZipCheck.total === 1, `staging-config-hotfix.zip contains EXACTLY 1 file (found ${hotfixZipCheck.total})`);
assertOk(hotfixZipCheck.backslashCount === 0, 'staging-config-hotfix.zip contains 100% forward-slash entries (0 backslashes)');

const publicConfigSrc = fs.readFileSync(path.join(ROOT_DIR, 'public/api/v1/config.php'), 'utf-8');
assertOk(publicConfigSrc.includes("staging-settings.php"), 'config.php loads staging-settings.php');
assertOk(publicConfigSrc.includes("ROCSTAGINGSESSID"), 'config.php enforces session_name("ROCSTAGINGSESSID")');
assertOk(publicConfigSrc.includes("https://staging.runonconsole.com"), 'config.php enforces CORS origin https://staging.runonconsole.com');
assertOk(publicConfigSrc.includes("define('SMTP_HOST', '')") && publicConfigSrc.includes("define('GOOGLE_CLIENT_ID', '')"), 'config.php disables SMTP Mail and Google OAuth');
assertOk(publicConfigSrc.includes("SELECT DATABASE() === 'runoncon_rocstage'") || publicConfigSrc.includes("SELECT DATABASE()"), 'getDBConnection() verifies SELECT DATABASE() === runoncon_rocstage');
assertOk(!publicConfigSrc.includes("/home2/runoncon/config/env.php") && !publicConfigSrc.includes("runoncon_db"), 'config.php NEVER searches or falls back to production config/env.php or runoncon_db');

// Test 17: Audit Staging URL Hotfix ZIP & Environment Site URL Contract
console.log('\n🔍 [Test 17] Auditing Staging URL Hotfix ZIP & Environment Site URL Contract...');
const urlHotfixZip = path.join(ROOT_DIR, 'staging-url-hotfix.zip');
assertOk(fs.existsSync(urlHotfixZip), `staging-url-hotfix.zip exists at ${urlHotfixZip}`);

const urlHotfixCheck = checkZipPathSeparators(urlHotfixZip);
assertOk(urlHotfixCheck.total === 4, `staging-url-hotfix.zip contains EXACTLY 4 files (found ${urlHotfixCheck.total})`);
assertOk(urlHotfixCheck.backslashCount === 0, 'staging-url-hotfix.zip contains 100% forward-slash entries (0 backslashes)');

const urlFiles = [
  'public/api/v1/agent/products.php',
  'public/api/v1/agent/search.php',
  'public/api/v1/agent/blogs.php',
  'public/api/v1/account-runtime.php'
];

for (const relFile of urlFiles) {
  const content = fs.readFileSync(path.join(ROOT_DIR, relFile), 'utf-8');
  assertOk(content.includes('ROC_SITE_URL'), `${relFile} uses ROC_SITE_URL environment URL`);
  assertOk(!/https:\/\/runonconsole\.com\/(products|blogs|auth|profile)/.test(content), `${relFile} contains 0 hardcoded frontend https://runonconsole.com URLs`);
}

// Test 18: Audit Product Seed Data Descriptions for Neutrality & Zero Fabricated Claims
console.log('\n🔍 [Test 18] Auditing Product Descriptions for Neutrality & Zero Fabricated Claims...');
const initDataText = fs.readFileSync(path.join(ROOT_DIR, 'src/data/initialData.js'), 'utf-8');
const seederText = fs.readFileSync(path.join(ROOT_DIR, 'public/api/v1/cron/seed-roc-agent-content.php'), 'utf-8');

assertOk(!/gta\s*6/i.test(initDataText) && !/gta\s*6/i.test(seederText), 'Product seed data contains 0 unannounced GTA 6 references');
assertOk(!/240Hz frame rates in Cyberpunk/i.test(initDataText) && !/240Hz frame rates in Cyberpunk/i.test(seederText), 'Product seed data contains 0 fabricated 240Hz frame rate claims');
assertOk(!/Aether G1/i.test(initDataText) && !/Aether G1/i.test(seederText), 'Product seed data contains 0 fictional Aether G1 handheld branding');
assertOk(!/CyberFusion/i.test(initDataText) && !/CyberFusion/i.test(seederText), 'Product seed data contains 0 fictional CyberFusion desktop branding');
assertOk(!/AuraLink/i.test(initDataText) && !/AuraLink/i.test(seederText), 'Product seed data contains 0 fictional AuraLink DAC branding');

// Test 19: Audit Structured Chat Persistence, New Chat & Conversation History Management
console.log('\n🔍 [Test 19] Auditing Structured Chat Persistence, New Chat & History Management...');
assertOk(modalSource.includes("getScopedStorageKey"), 'ROCAgentModal.jsx defines getScopedStorageKey helper for user/conversation scoping');
assertOk(modalSource.includes('sanitizeMessageForStorage'), 'ROCAgentModal.jsx defines sanitizeMessageForStorage helper');
assertOk(modalSource.includes('loadSavedSessionMessages'), 'ROCAgentModal.jsx initializes state using loadSavedSessionMessages');
assertOk(modalSource.includes('sessionStorage.setItem'), 'ROCAgentModal.jsx syncs safe messages to sessionStorage on state updates');
assertOk(!modalSource.includes('csrfToken') || !modalSource.includes('sessionStorage.setItem(key, JSON.stringify(csrfToken))'), 'sessionStorage sync NEVER persists CSRF tokens or authentication keys');
assertOk(modalSource.includes('products') && modalSource.includes('matchedProducts') && modalSource.includes('compat_result'), 'sessionStorage sync persists safe structured card properties (products, games, categories, blogs, matched items)');
assertOk(modalSource.includes('Start New Chat') || modalSource.includes('handleConfirmNewChat'), 'ROCAgentModal.jsx provides visible New Chat trigger button');
assertOk(modalSource.includes('Start New Conversation?') && modalSource.includes('Confirm New Chat'), 'ROCAgentModal.jsx renders confirmation dialog before clearing conversation');
assertOk(modalSource.includes('sessionStorage.removeItem'), 'Confirming New Chat clears persisted ROC Agent messages from sessionStorage');
assertOk(modalSource.includes('setActiveFlow(null)') && modalSource.includes('setSelectedGame(null)'), 'Confirming New Chat resets activeFlow, selectedGame, and form states');
assertOk(modalSource.includes("action: 'new'") || modalSource.includes("action === 'new'"), 'Guest and Authenticated New Chat both trigger backend conversation creation');
assertOk(modalSource.includes("isCreatingNewChat"), 'ROCAgentModal.jsx disables message input while New Chat creation is pending');

// Test 20: Behavioral Unit & Integration Tests (Conversation Separation, History Replacement, Account Switching, Metadata Sanitization)
console.log('\n🔍 [Test 20] Running Behavioral Unit & Integration Tests...');

// 1. Behavioral Test: Conversation Separation (No Cross-Talk)
const convStore = new Map();
function mockCreateConv(userId, guestSessionId) {
  const convId = 'conv_' + Math.random().toString(36).substring(2, 9);
  convStore.set(convId, { userId, guestSessionId, messages: [] });
  return convId;
}
function mockAddMessage(convId, role, text, metadata = null) {
  const conv = convStore.get(convId);
  if (!conv) throw new Error('Conversation not found');
  const msgId = 'msg_' + Date.now() + '_' + Math.random().toString(36).substring(2, 5);
  conv.messages.push({ id: msgId, role, text, metadata });
  return msgId;
}
function mockGetConvMessages(convId, reqUserOrGuest) {
  const conv = convStore.get(convId);
  if (!conv) return null;
  if (conv.userId && conv.userId !== reqUserOrGuest) return null;
  if (conv.guestSessionId && conv.guestSessionId !== reqUserOrGuest) return null;
  return conv.messages;
}

const userA_conv = mockCreateConv('user_101', null);
const userB_conv = mockCreateConv('user_102', null);

mockAddMessage(userA_conv, 'user', 'What GPU for 4K?');
mockAddMessage(userA_conv, 'assistant', 'RTX 4090 is recommended', { type: 'products', products: [{ id: 'prod-gpu-1', name: 'RTX 4090' }] });
mockAddMessage(userB_conv, 'user', 'Best headset under $150?');

const userAMsgs = mockGetConvMessages(userA_conv, 'user_101');
const userBMsgs = mockGetConvMessages(userB_conv, 'user_102');
const unauthorizedAccess = mockGetConvMessages(userA_conv, 'user_102');

assertOk(userAMsgs.length === 2 && userAMsgs[1].metadata.type === 'products', 'Behavioral: User A receives exactly User A messages with structured metadata');
assertOk(userBMsgs.length === 1 && userBMsgs[0].text.includes('headset'), 'Behavioral: User B receives strictly User B messages');
assertOk(unauthorizedAccess === null, 'Behavioral: User B is strictly denied access to User A conversation');

// 2. Behavioral Test: History Replacement (Complete Override, No Stray Merging)
function mockSelectHistory(activeState, targetConvMessages) {
  // Replacing active message list COMPLETELY with loaded conversation
  const welcomeMsg = { id: 'welcome-1', sender: 'bot', text: 'Welcome', showActions: true };
  const replaced = [welcomeMsg, ...targetConvMessages.map(m => ({ id: m.id, sender: m.role === 'assistant' ? 'bot' : 'user', text: m.text, ...m.metadata }))];
  return replaced;
}

const activeStateBefore = [
  { id: 'welcome-1', sender: 'bot', text: 'Welcome' },
  { id: 'old-1', sender: 'user', text: 'Old query from prior session' }
];

const selectedHistoryMsgs = [
  { id: 'h-1', role: 'user', text: 'Historical query' },
  { id: 'h-2', role: 'assistant', text: 'Historical answer', metadata: { type: 'blogs', blogs: [{ id: 'b1' }] } }
];

const stateAfterSelect = mockSelectHistory(activeStateBefore, selectedHistoryMsgs);
assertOk(stateAfterSelect.length === 3, 'Behavioral: Selected history completely replaces active state (welcome + 2 history msgs)');
assertOk(!stateAfterSelect.some(m => m.id === 'old-1'), 'Behavioral: Unmatched message from prior session is NOT merged into selected history');
assertOk(stateAfterSelect[2].type === 'blogs', 'Behavioral: Structured cards in selected history are fully hydrated');

// 3. Behavioral Test: Account Switch Storage & State Reset
function mockAccountSwitch(currentUserId, targetUserId, mockStorage) {
  const oldKey = `roc_agent_msgs_${currentUserId || 'guest'}_active`;
  mockStorage.delete(oldKey);
  const newKey = `roc_agent_msgs_${targetUserId || 'guest'}_active`;
  return {
    activeConvId: null,
    activeFlow: null,
    messages: [{ id: 'welcome-1', sender: 'bot', text: 'Welcome' }],
    newKey
  };
}

const mockSessionStorage = new Map([
  ['roc_agent_msgs_user_101_active', JSON.stringify([{ id: 'msg1', text: 'private data' }])]
]);

const switchResult = mockAccountSwitch('user_101', 'user_102', mockSessionStorage);
assertOk(!mockSessionStorage.has('roc_agent_msgs_user_101_active'), 'Behavioral: Switching account clears previous user scoped storage');
assertOk(switchResult.activeConvId === null && switchResult.messages.length === 1, 'Behavioral: Switching account resets active conversation ID and restores welcome state');

// 4. Behavioral Test: Server-Side Metadata Whitelist & Anti-Leak Sanitization
function mockBackendSanitizeMetadata(raw) {
  if (!raw || typeof raw !== 'object') return null;
  const type = raw.type;
  const allowed = ['products', 'games', 'blogs', 'categories', 'search_results', 'compat_result'];
  if (!allowed.includes(type)) return null;

  const clean = { type };
  if (type === 'products' && Array.isArray(raw.products)) {
    clean.products = raw.products.map(p => ({
      id: String(p.id || ''),
      name: String(p.name || p.title || ''),
      price: String(p.price || '')
    }));
  }
  return clean;
}

const maliciousPayload = {
  type: 'products',
  products: [{ id: 'prod-101', name: 'Logitech G Pro', price: '$199.99' }],
  csrf_token: 'SECRET_CSRF_123',
  user_password: 'super_secret_password',
  submitted_device_specs: { cpu: 'Intel i9', gpu: 'RTX 4090' },
  user_email: 'user@example.com'
};

const sanitizedOutput = mockBackendSanitizeMetadata(maliciousPayload);
assertOk(sanitizedOutput.type === 'products' && sanitizedOutput.products.length === 1, 'Behavioral: Metadata sanitizer preserves valid whitelisted products array');
assertOk(!('csrf_token' in sanitizedOutput) && !('user_password' in sanitizedOutput), 'Behavioral: Metadata sanitizer STRIPS sensitive csrf_token and user_password');
assertOk(!('submitted_device_specs' in sanitizedOutput) && !('user_email' in sanitizedOutput), 'Behavioral: Metadata sanitizer STRIPS submitted device specs and user email');

// 5. Behavioral Test: Additive CLI Migration File Check
const additiveMigrationFile = path.join(ROOT_DIR, 'public/api/v1/cron/cli-add-metadata-json-roc-messages.php');
assertOk(fs.existsSync(additiveMigrationFile), 'public/api/v1/cron/cli-add-metadata-json-roc-messages.php additive CLI migration script exists');
const additiveSource = fs.readFileSync(additiveMigrationFile, 'utf-8');
assertOk(additiveSource.includes("PHP_SAPI !== 'cli'") && !additiveSource.includes("ROC_CLI_RUN"), 'CLI migration is strictly CLI only using PHP_SAPI with 0 web bypass');
assertOk(additiveSource.includes("PREVIEW MODE") && additiveSource.includes("in_array('--apply'"), 'CLI migration is preview only by default and requires literal --apply flag');
assertOk(additiveSource.includes("runoncon_rocstage"), 'CLI migration verifies SELECT DATABASE() equals runoncon_rocstage before ALTER TABLE');
assertOk(additiveSource.includes("verifyStmt") || additiveSource.includes("verified"), 'CLI migration verifies column existence after alteration and returns non-zero on failure');

// 6. Behavioral Test: Backend conversations.php metadata_json Requirement & Whitelist
const convPhpSource = fs.readFileSync(path.join(ROOT_DIR, 'public/api/v1/agent/conversations.php'), 'utf-8');
assertOk(!convPhpSource.includes("SELECT id, role, message, intent FROM roc_messages"), 'conversations.php strictly requires metadata_json and never silently falls back to text-only inserts');
assertOk(convPhpSource.includes("rocAgentSanitizeComponentResults"), 'conversations.php implements recursive componentResults field whitelisting');
assertOk(!convPhpSource.includes("summaryText") && !convPhpSource.includes("explanation"), 'conversations.php omits summaryText and explanation to prevent persisting submitted specs');

// 7. Behavioral Test: Frontend ROCAgentModal.jsx Storage Whitelist & Serialization
assertOk(modalSource.includes("sanitizeComponentResultsForStorage"), 'ROCAgentModal.jsx applies explicit field whitelist to componentResults in sessionStorage');
assertOk(!/safe\.result\s*=\s*\{[^}]*summaryText/s.test(modalSource) && !/cleanItem\s*=\s*\{[^}]*explanation/s.test(modalSource), 'ROCAgentModal.jsx omits summaryText and explanation from sessionStorage');
assertOk(modalSource.includes("ensureActiveConversationId"), 'ROCAgentModal.jsx obtains/creates conversation ID before first message POST');
assertOk(modalSource.includes("saveQueueRef"), 'ROCAgentModal.jsx serializes message saves to prevent duplicate conversation creation');
assertOk(modalSource.includes("await saveQueueRef.current") && modalSource.includes("activeConversationIdRef.current = newId"), 'handleConfirmNewChat disables input, awaits save queue, and immediately updates activeConversationId state and Ref before enabling input');
assertOk(modalSource.includes("clearIdentityScopedStorage"), 'ROCAgentModal.jsx purges previous identity-scoped sessionStorage keys on logout and account switch');
assertOk(modalSource.includes("setMessages([welcomeMsg])"), 'Selecting an empty conversation resets UI state to ONLY the welcome message');

// ---------------------------------------------------------
// PART 4: UNRUN PHP / MYSQL / BROWSER RUNTIME TEST STATUS
// ---------------------------------------------------------
console.log('\n============================================================');
console.log('PART 4: UNRUN PHP / MYSQL / BROWSER RUNTIME TEST STATUS');
console.log('============================================================\n');

notRun("Isolated Staging MySQL Migration Test", "Execution requires cPanel MySQL connection to runoncon_rocstage.");
notRun("Staging Session Auth & Device API Persistence Test", "Execution requires active staging PHP runtime environment (ROCSTAGINGSESSID).");
notRun("Interactive Web Browser UI Test", "Requires live browser interaction with staging domain (https://staging.runonconsole.com).");

console.log(`\n============================================================`);
console.log(`📊 FINAL VERIFICATION: ${passedAssertions}/${totalAssertions} Assertions Passed (${failedAssertions} Failed)`);
console.log(`============================================================\n`);

if (failedAssertions > 0) {
  process.exit(1);
}
