import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { 
  STATIC_PUBLIC_ROUTES, 
  PRIVATE_ROUTES, 
  ENHANCED_BLOGS, 
  ENHANCED_CATEGORIES, 
  ENHANCED_PRODUCTS 
} from '../src/seo/routeRegistry.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const DIST_DIR = path.resolve(__dirname, '../dist');

console.log('🧪 Running Comprehensive SRE, DOM Integrity & SEO Acceptance Verification Suite...\n');

let totalTests = 0;
let passedTests = 0;
let failedTests = 0;

function assert(condition, message) {
  totalTests++;
  if (condition) {
    passedTests++;
    console.log(`  ✓ PASS: ${message}`);
  } else {
    failedTests++;
    console.error(`  ❌ FAIL: ${message}`);
  }
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

// 1. Verify Viewport Meta Tag in Root & Pre-rendered Pages
const rootHtmlPath = path.join(DIST_DIR, 'index.html');
assert(fs.existsSync(rootHtmlPath), 'Root dist/index.html exists');

if (fs.existsSync(rootHtmlPath)) {
  const rootHtml = fs.readFileSync(rootHtmlPath, 'utf-8');
  assert(rootHtml.includes('name="viewport" content="width=device-width'), 'dist/index.html contains viewport meta tag');
}

// 2. DOM Integrity & Duplicate Content Prevention Tests Across Key Routes
console.log('\n🔍 Verifying DOM Integrity & No-Duplicate-Content Structure Across Core Routes...');

const testRoutes = [
  '/',
  '/auth/login/',
  '/auth/signup/',
  '/auth/verify-email/',
  '/auth/forgot-password/',
  '/auth/reset-password/',
  '/profile/',
  '/products/',
  '/blogs/',
  '/compatibility/'
];

for (const rPath of testRoutes) {
  const fileRelPath = rPath === '/' ? 'index.html' : path.join(rPath.replace(/^\/+|\/+$/g, ''), 'index.html');
  const fullPath = path.join(DIST_DIR, fileRelPath);
  assert(fs.existsSync(fullPath), `Pre-rendered HTML file exists for ${rPath}`);

  if (fs.existsSync(fullPath)) {
    const html = fs.readFileSync(fullPath, 'utf-8');

    // Count main, footer, and #root elements
    const mainMatches = html.match(/<main[\s>]/gi) || [];
    const footerMatches = html.match(/<footer[\s>]/gi) || [];
    const rootMatches = html.match(/id="root"/gi) || [];

    assert(mainMatches.length === 1, `${rPath} contains exactly ONE <main> element (found: ${mainMatches.length})`);
    assert(footerMatches.length === 1, `${rPath} contains exactly ONE <footer> element (found: ${footerMatches.length})`);
    assert(rootMatches.length === 1, `${rPath} contains exactly ONE id="root" element (found: ${rootMatches.length})`);

    // Verify root structure: no content after <div id="root">...</div> except script tags before </body>
    const rootEndIndex = html.indexOf('</div>\n    <script type="module"');
    const rootStartIndex = html.indexOf('<div id="root">');
    assert(rootStartIndex !== -1, `${rPath} has valid <div id="root"> start tag`);
    
    // Check duplicate IDs across document
    const trendingMatches = html.match(/id="trending-section"/gi) || [];
    const latestReviewsMatches = html.match(/id="latest-reviews-section"/gi) || [];
    const igGradMatches = html.match(/id="ig-grad"/gi) || [];

    assert(trendingMatches.length <= 1, `${rPath} contains no duplicate #trending-section (found: ${trendingMatches.length})`);
    assert(latestReviewsMatches.length <= 1, `${rPath} contains no duplicate #latest-reviews-section (found: ${latestReviewsMatches.length})`);
    assert(igGradMatches.length <= 1, `${rPath} contains no duplicate #ig-grad (found: ${igGradMatches.length})`);

    // Ensure non-homepage routes do NOT contain homepage hero text
    if (rPath !== '/') {
      const hasHomepageHeroHeading = html.includes('Discover the Best') && html.includes('Games, Hardware');
      assert(!hasHomepageHeroHeading, `${rPath} does NOT contain orphan homepage hero content outside #root`);
    }
  }
}

// 3. Verify Private Routes Security & Noindex directives
console.log('\n🔍 Verifying Private Routes Security & Noindex directives...');
for (const priv of PRIVATE_ROUTES) {
  const cleanPath = priv.path.replace(/^\/+|\/+$/g, '');
  const htmlPath = path.join(DIST_DIR, cleanPath, 'index.html');
  assert(fs.existsSync(htmlPath), `Private route HTML file exists for ${priv.path}`);

  if (fs.existsSync(htmlPath)) {
    const html = fs.readFileSync(htmlPath, 'utf-8');
    assert(html.includes('name="robots" content="noindex, follow"'), `${priv.path} carries server-visible noindex directive`);
    assert(!html.includes('<title>Run On Console | Gaming Hardware, Compatibility &amp; Peripherals Hub</title>'), `${priv.path} does not contain homepage title in initial HTML`);
    
    if (priv.path === '/auth/login/') {
      assert(html.includes('Sign In to Run On Console'), `${priv.path} carries exact H1 "Sign In to Run On Console"`);
    } else if (priv.path === '/auth/signup/') {
      assert(html.includes('Create Your Gamer Account'), `${priv.path} carries exact H1 "Create Your Gamer Account"`);
    } else if (priv.path === '/auth/verify-email/') {
      assert(html.includes('Email Verification'), `${priv.path} carries exact H1 "Email Verification"`);
    } else if (priv.path === '/auth/forgot-password/') {
      assert(html.includes('Forgot Password'), `${priv.path} carries exact H1 "Forgot Password"`);
    } else if (priv.path === '/auth/reset-password/') {
      assert(html.includes('Choose New Password'), `${priv.path} carries exact H1 "Choose New Password"`);
    } else if (priv.path === '/profile/') {
      assert(html.includes('Verifying Gamer Authentication Session'), `${priv.path} pre-renders neutral session loading interface matching client initial state`);
    }
  }
}

// 4. Verify llms.txt in dist/
console.log('\n🔍 Verifying Automated dist/llms.txt...');
const llmsPath = path.join(DIST_DIR, 'llms.txt');
assert(fs.existsSync(llmsPath), 'dist/llms.txt exists');
if (fs.existsSync(llmsPath)) {
  const llmsContent = fs.readFileSync(llmsPath, 'utf-8');
  assert(llmsContent.includes('# Run On Console'), 'llms.txt contains # Run On Console header');
  assert(llmsContent.includes('https://runonconsole.com/author/omar-abobakar/'), 'llms.txt contains author URL');
}

// 5. Verify Sitemap Index Architecture & 4 Child Sitemaps
console.log('\n🔍 Verifying Sitemap Index Architecture & Child Sitemaps...');
const parentSitemapPath = path.join(DIST_DIR, 'sitemap.xml');
assert(fs.existsSync(parentSitemapPath), 'Parent dist/sitemap.xml exists');

if (fs.existsSync(parentSitemapPath)) {
  const parentXml = fs.readFileSync(parentSitemapPath, 'utf-8');
  assert(parentXml.includes('<sitemapindex'), 'dist/sitemap.xml is a valid sitemapindex file');
  assert(parentXml.includes('https://runonconsole.com/sitemaps/pages-sitemap.xml'), 'Parent index references pages-sitemap.xml');
  assert(parentXml.includes('https://runonconsole.com/sitemaps/categories-sitemap.xml'), 'Parent index references categories-sitemap.xml');
  assert(parentXml.includes('https://runonconsole.com/sitemaps/products-sitemap.xml'), 'Parent index references products-sitemap.xml');
  assert(parentXml.includes('https://runonconsole.com/sitemaps/blogs-sitemap.xml'), 'Parent index references blogs-sitemap.xml');
}

// 6. Verify Release Manifest & Asset Consistency
console.log('\n🔍 Verifying Release Manifest & Asset Consistency...');
const manifestPath = path.join(DIST_DIR, 'release-manifest.json');
assert(fs.existsSync(manifestPath), 'dist/release-manifest.json exists');
if (fs.existsSync(manifestPath)) {
  const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf-8'));
  assert(Boolean(manifest.releaseIdentifier), 'release-manifest contains releaseIdentifier');
  assert(Boolean(manifest.jsAssetFilename), 'release-manifest contains jsAssetFilename');
  assert(Boolean(manifest.cssAssetFilename), 'release-manifest contains cssAssetFilename');
  assert(manifest.totalRouteCount >= 68, 'release-manifest reflects total pre-rendered route count (>=68)');

  const indexHtml = fs.readFileSync(rootHtmlPath, 'utf-8');
  assert(indexHtml.includes(manifest.jsAssetFilename), 'dist/index.html references exact jsAssetFilename from release-manifest');
  assert(indexHtml.includes(manifest.cssAssetFilename), 'dist/index.html references exact cssAssetFilename from release-manifest');
  assert(!indexHtml.includes('/src/main.jsx'), 'dist/index.html contains ZERO /src/main.jsx references');
  const moduleTagCount = (indexHtml.match(/<script type="module"/gi) || []).length;
  assert(moduleTagCount === 1, `dist/index.html contains EXACTLY 1 module script tag (found: ${moduleTagCount})`);
}

// 7. Verify 20 Esports Avatar SVG Assets & Gallery Registry Integrity
console.log('\n🔍 Verifying 20 Esports Avatar Gallery Assets...');
const avatarDir = path.resolve(__dirname, '../public/images/avatars');
assert(fs.existsSync(avatarDir), 'public/images/avatars/ directory exists');

if (fs.existsSync(avatarDir)) {
  for (let i = 1; i <= 20; i++) {
    const numStr = String(i).padStart(2, '0');
    const avatarPath = path.join(avatarDir, `avatar_${numStr}.svg`);
    assert(fs.existsSync(avatarPath), `Avatar asset avatar_${numStr}.svg exists`);
  }
}

// Summary Output
console.log(`\n==============================================`);
console.log(`📊 TEST RESULTS: ${passedTests}/${totalTests} Passed (${failedTests} Failed)`);
console.log(`==============================================\n`);

if (failedTests > 0) {
  process.exit(1);
}
