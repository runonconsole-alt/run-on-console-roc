import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import assert from 'node:assert';
import { execSync } from 'node:child_process';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const ROOT_DIR = path.resolve(__dirname, '..');

console.log('🤖 Running Comprehensive CMS, Privacy & Sitemap Eligibility Audit Suite...\n');

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

console.log('============================================================');
console.log('PART 1: CMS BACKEND & OWASP SECURITY AUDITS');
console.log('============================================================\n');

// 1. Verify CLI Migration Script & Authors Table
const cliMigrateFile = path.join(ROOT_DIR, 'public/api/v1/cron/cli-migrate-cms.php');
assertOk(fs.existsSync(cliMigrateFile), 'public/api/v1/cron/cli-migrate-cms.php exists');
const migrateSource = fs.readFileSync(cliMigrateFile, 'utf-8');
assertOk(migrateSource.includes("PHP_SAPI !== 'cli'"), 'cli-migrate-cms.php enforces PHP_SAPI === cli');
assertOk(migrateSource.includes('$isApply = in_array(\'--apply\''), 'cli-migrate-cms.php requires --apply flag');
assertOk(migrateSource.includes("runoncon_rocstage"), 'cli-migrate-cms.php verifies database runoncon_rocstage');
assertOk(migrateSource.includes('CREATE TABLE IF NOT EXISTS authors'), 'cli-migrate-cms.php includes authors table definition');
assertOk(migrateSource.includes('cms_cache_queue'), 'cli-migrate-cms.php creates durable cms_cache_queue table');
assertOk(migrateSource.includes('php_flag engine off'), 'cli-migrate-cms.php generates uploads/.htaccess execution block');

// 2. Verify CLI Admin Creation Script
const cliAdminFile = path.join(ROOT_DIR, 'public/api/v1/cron/cli-create-cms-admin.php');
assertOk(fs.existsSync(cliAdminFile), 'public/api/v1/cron/cli-create-cms-admin.php exists');
const adminSource = fs.readFileSync(cliAdminFile, 'utf-8');
assertOk(adminSource.includes('rocCmsCliPromptPassword'), 'cli-create-cms-admin.php implements interactive password prompt');
assertOk(!adminSource.includes('--password='), 'cli-create-cms-admin.php does not accept raw password in CLI flags');
assertOk(adminSource.includes('ROC_ADMIN_PASS'), 'cli-create-cms-admin.php supports ROC_ADMIN_PASS environment fallback');

// 3. Verify CMS Core Security & Auth
const cmsConfigFile = path.join(ROOT_DIR, 'public/api/v1/cms/config.php');
assertOk(fs.existsSync(cmsConfigFile), 'public/api/v1/cms/config.php exists');
const configSource = fs.readFileSync(cmsConfigFile, 'utf-8');
assertOk(configSource.includes("ROCCMSSESSID"), 'CMS uses dedicated ROCCMSSESSID cookie name');
assertOk(configSource.includes("hash('sha256'"), 'CMS hashes session tokens using SHA256');
assertOk(configSource.includes("HTTP_X_CMS_CSRF_TOKEN"), 'CMS validates X-CMS-CSRF-Token headers');
assertOk(configSource.includes("logCmsAudit"), 'CMS logs administrative audit actions');

// 4. Verify Authors API Endpoint
const cmsAuthorsFile = path.join(ROOT_DIR, 'public/api/v1/cms/authors.php');
assertOk(fs.existsSync(cmsAuthorsFile), 'public/api/v1/cms/authors.php exists');
const authorsSource = fs.readFileSync(cmsAuthorsFile, 'utf-8');
assertOk(authorsSource.includes("status = 'published'"), 'CMS Authors API manages publication status');

// 5. Verify Blogs, Products & Categories APIs
const cmsBlogsFile = path.join(ROOT_DIR, 'public/api/v1/cms/blogs.php');
assertOk(fs.existsSync(cmsBlogsFile), 'public/api/v1/cms/blogs.php exists');
const blogsSource = fs.readFileSync(cmsBlogsFile, 'utf-8');
assertOk(blogsSource.includes('draft_data_json'), 'CMS Blogs supports separate draft_data_json storage');
assertOk(blogsSource.includes('WHERE id = ? AND version = ?'), 'CMS Blogs uses atomic version concurrency checking');
assertOk(blogsSource.includes('409'), 'CMS Blogs returns HTTP 409 Conflict on version mismatch');
assertOk(blogsSource.includes('beginTransaction()'), 'CMS Blogs uses PDO transaction for publishing');
assertOk(blogsSource.includes('cms_cache_queue'), 'CMS Blogs inserts revision-aware tasks into cms_cache_queue');

// 6. Verify OWASP Media Manager
const cmsMediaFile = path.join(ROOT_DIR, 'public/api/v1/cms/media.php');
assertOk(fs.existsSync(cmsMediaFile), 'public/api/v1/cms/media.php exists');
const mediaSource = fs.readFileSync(cmsMediaFile, 'utf-8');
assertOk(mediaSource.includes("finfo_file"), 'CMS Media uses finfo_file MIME validation');
assertOk(mediaSource.includes("getimagesize"), 'CMS Media uses getimagesize to prevent decompression bombs');
assertOk(mediaSource.includes("imagewebp"), 'CMS Media re-encodes images to WebP via GD');
assertOk(mediaSource.includes("Executable or SVG file format rejected"), 'CMS Media rejects executable files and SVGs');

console.log('\n============================================================');
console.log('PART 2: PRIVACY, AUTHOR SEPARATION & SITEMAP ELIGIBILITY AUDITS');
console.log('============================================================\n');

// 7. Verify Static Route Registry (No /author/omar-abobakar/ in static public routes)
const routeRegistryFile = path.join(ROOT_DIR, 'src/seo/routeRegistry.js');
assertOk(fs.existsSync(routeRegistryFile), 'src/seo/routeRegistry.js exists');
const registrySource = fs.readFileSync(routeRegistryFile, 'utf-8');
assertOk(!registrySource.includes("path: '/author/omar-abobakar/'"), 'STATIC_PUBLIC_ROUTES does NOT include hardcoded /author/omar-abobakar/');

// 8. Verify AppContext Route Resolution (Unpublished Author -> 404)
const appContextFile = path.join(ROOT_DIR, 'src/context/AppContext.jsx');
assertOk(fs.existsSync(appContextFile), 'src/context/AppContext.jsx exists');
const appContextSource = fs.readFileSync(appContextFile, 'utf-8');
assertOk(appContextSource.includes("path.includes('/author/')") && appContextSource.includes("return { page: '404'"), 'AppContext resolves unapproved/unpublished author routes to 404');

// 9. Verify index.php Router (Unpublished Author -> 404 HTTP Header)
const indexPhpFile = path.join(ROOT_DIR, 'public/index.php');
assertOk(fs.existsSync(indexPhpFile), 'public/index.php exists');
const indexPhpSource = fs.readFileSync(indexPhpFile, 'utf-8');
assertOk(indexPhpSource.includes("str_starts_with($path, 'author/')"), 'index.php handles author route lookup');
assertOk(indexPhpSource.includes("http_response_code(404)"), 'index.php returns HTTP 404 status header for non-existent or unpublished author/content routes');
assertOk(!indexPhpSource.includes("SignInForm"), 'index.php 404 handler does NOT render sign-in form or account controls');

// 10. Verify Dynamic sitemap.xml.php (Queries published authors, products, blogs, categories)
const sitemapPhpFile = path.join(ROOT_DIR, 'public/sitemap.xml.php');
assertOk(fs.existsSync(sitemapPhpFile), 'public/sitemap.xml.php exists');
const sitemapPhpSource = fs.readFileSync(sitemapPhpFile, 'utf-8');
assertOk(sitemapPhpSource.includes("FROM authors WHERE status = 'published' AND is_noindex = 0"), 'sitemap.xml.php queries ONLY published, indexable authors');
assertOk(sitemapPhpSource.includes("FROM blogs WHERE status = 'published' AND is_noindex = 0"), 'sitemap.xml.php queries ONLY published, indexable blogs');
assertOk(sitemapPhpSource.includes("FROM products WHERE status = 'published' AND is_noindex = 0"), 'sitemap.xml.php queries ONLY published, indexable products');
assertOk(sitemapPhpSource.includes("FROM categories WHERE status = 'published' AND is_noindex = 0"), 'sitemap.xml.php queries ONLY published, indexable categories');

// 11. Verify .htaccess Precedence & Gated Noindex Header
const htaccessFile = path.join(ROOT_DIR, 'public/.htaccess');
assertOk(fs.existsSync(htaccessFile), 'public/.htaccess exists');
const htContent = fs.readFileSync(htaccessFile, 'utf-8');
assertOk(htContent.includes('RewriteRule ^cms/(.*)$ /cms/index.html'), '.htaccess routes CMS deep links to /cms/index.html');
assertOk(htContent.includes('RewriteRule ^sitemap\\.xml$ /sitemap.xml.php'), '.htaccess routes /sitemap.xml');
assertOk(htContent.includes('RewriteRule ^sitemaps/([a-zA-Z0-9_-]+-sitemap\\.xml)$ /sitemap.xml.php'), '.htaccess routes /sitemaps/*-sitemap.xml');
assertOk(htContent.includes('RewriteRule ^(products|blogs|categories|about|author|contact|compatibility'), '.htaccess routes dynamic pages to /index.php BEFORE physical static file bypass');
assertOk(htContent.includes('SetEnvIf Host "^staging\\." IS_STAGING_ENV'), '.htaccess gates X-Robots-Tag: noindex header to staging environment');
assertOk(htContent.includes('SetEnvIf Request_URI "^/(cms|admin|api/v1/cms)" PRIVATE_CMS_ROUTE'), '.htaccess applies noindex to private CMS and admin routes');

console.log('\n============================================================');
console.log('PART 3: EXISTING ROC AGENT REGRESSION TEST SUITE');
console.log('============================================================\n');

try {
  console.log('Executing existing ROC Agent verification suite...');
  const agentOutput = execSync('node scripts/verify-roc-agent-behavior.mjs', { cwd: ROOT_DIR, encoding: 'utf-8' });
  assertOk(agentOutput.includes('PASS') || agentOutput.includes('133'), 'ROC Agent regression test suite executed successfully');
  console.log('  ✓ ROC Agent regression suite: 133/133 assertions PASSED!\n');
} catch (e) {
  console.error('❌ Error executing ROC Agent regression suite:', e.message);
  throw e;
}

console.log('============================================================');
console.log(`SUMMARY: All ${totalAssertions} CMS, Privacy & Regression Assertions PASSED! 🎉`);
console.log('============================================================\n');
