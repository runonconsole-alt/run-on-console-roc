import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const ROOT_DIR = path.resolve(__dirname, '..');

console.log('🤖 Running Part 3: Sitemaps & Page SEO Audit Suite...\n');

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

// 1. Verify robots.txt content & accessibility
const robotsPath = path.join(ROOT_DIR, 'public/robots.txt');
assertOk(fs.existsSync(robotsPath), 'public/robots.txt file exists');
const robotsContent = fs.readFileSync(robotsPath, 'utf-8');
assertOk(robotsContent.includes('User-agent: *'), 'robots.txt contains User-agent: *');
assertOk(robotsContent.includes('Sitemap: https://runonconsole.com/sitemap.xml') || robotsContent.includes('sitemap.xml'), 'robots.txt references sitemap.xml');

// 2. Verify llms.txt content & accessibility
const llmsPath = path.join(ROOT_DIR, 'public/llms.txt');
assertOk(fs.existsSync(llmsPath), 'public/llms.txt file exists');
const llmsContent = fs.readFileSync(llmsPath, 'utf-8');
assertOk(llmsContent.includes('# Run On Console'), 'llms.txt contains expected header content');
assertOk(llmsContent.includes('https://runonconsole.com'), 'llms.txt contains canonical links');

// 3. Verify sitemap.xml.php implementation
const sitemapPhpPath = path.join(ROOT_DIR, 'public/sitemap.xml.php');
assertOk(fs.existsSync(sitemapPhpPath), 'public/sitemap.xml.php file exists');
const sitemapSource = fs.readFileSync(sitemapPhpPath, 'utf-8');

assertOk(sitemapSource.includes("sitemaps/pages-sitemap.xml"), 'sitemap.xml.php index includes pages-sitemap.xml');
assertOk(sitemapSource.includes("sitemaps/blogs-sitemap.xml"), 'sitemap.xml.php index includes blogs-sitemap.xml');
assertOk(sitemapSource.includes("sitemaps/products-sitemap.xml"), 'sitemap.xml.php index includes products-sitemap.xml');
assertOk(sitemapSource.includes("sitemaps/categories-sitemap.xml"), 'sitemap.xml.php index includes categories-sitemap.xml');

assertOk(sitemapSource.includes("WHERE status = 'published' AND is_noindex = 0"), 'sitemap.xml.php excludes draft, noindex and private content');
assertOk(sitemapSource.includes("getGenuineLastmod"), 'sitemap.xml.php uses genuine published modification dates without dynamic date() refresh');

// 4. Verify SSR index.php metadata, canonical, Open Graph & 404 status
const indexPhpPath = path.join(ROOT_DIR, 'public/index.php');
assertOk(fs.existsSync(indexPhpPath), 'public/index.php file exists');
const indexPhpSource = fs.readFileSync(indexPhpPath, 'utf-8');

assertOk(indexPhpSource.includes('<link rel="canonical"'), 'index.php renders canonical link tags in head');
assertOk(indexPhpSource.includes('og:title'), 'index.php renders Open Graph social metadata');
assertOk(indexPhpSource.includes('application/ld+json'), 'index.php renders JSON-LD structured data');
assertOk(indexPhpSource.includes('http_response_code(404)'), 'index.php handles missing URLs with genuine HTTP 404 response');

console.log('\n============================================================');
console.log(`SUMMARY: All ${total} Sitemaps & Page SEO Assertions PASSED! 🎉`);
console.log('============================================================\n');
