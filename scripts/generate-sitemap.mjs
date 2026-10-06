import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';
import { 
  STATIC_PUBLIC_ROUTES, 
  ENHANCED_BLOGS, 
  ENHANCED_CATEGORIES, 
  ENHANCED_PRODUCTS 
} from '../src/seo/routeRegistry.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const PUBLIC_DIR = path.resolve(__dirname, '../public');
const DIST_DIR = path.resolve(__dirname, '../dist');
const CACHE_FILE = path.join(__dirname, '.sitemap-cache.json');

console.log('🗺️ Generating Production Sitemap Index Architecture for Run On Console...');

// Ensure sitemaps directory exists in public & dist
const publicSitemapsDir = path.join(PUBLIC_DIR, 'sitemaps');
if (!fs.existsSync(publicSitemapsDir)) {
  fs.mkdirSync(publicSitemapsDir, { recursive: true });
}

let distSitemapsDir = null;
if (fs.existsSync(DIST_DIR)) {
  distSitemapsDir = path.join(DIST_DIR, 'sitemaps');
  if (!fs.existsSync(distSitemapsDir)) {
    fs.mkdirSync(distSitemapsDir, { recursive: true });
  }
}

// Read previous sitemap cache if available
let cache = {};
if (fs.existsSync(CACHE_FILE)) {
  try {
    cache = JSON.parse(fs.readFileSync(CACHE_FILE, 'utf-8'));
  } catch (e) {
    cache = {};
  }
}

function computeHash(content) {
  return crypto.createHash('sha256').update(content, 'utf-8').digest('hex');
}

function escapeXml(unsafe) {
  return unsafe.replace(/[<>&'"]/g, c => {
    switch (c) {
      case '<': return '&lt;';
      case '>': return '&gt;';
      case '&': return '&amp;';
      case '\'': return '&apos;';
      case '"': return '&quot;';
    }
  });
}

function generateChildSitemapXml(entries) {
  const urlNodes = entries.map(e => `  <url>
    <loc>${escapeXml(e.loc)}</loc>
    <lastmod>${e.lastmod}</lastmod>
  </url>`).join('\n');

  return `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${urlNodes}
</urlset>
`;
}

// 1. Build Pages Sitemap entries
const pagesEntries = STATIC_PUBLIC_ROUTES.map(r => ({
  loc: r.canonical,
  lastmod: r.lastmod || '2024-05-20T10:00:00+00:00'
}));

// 2. Build Categories Sitemap entries
const categoriesEntries = ENHANCED_CATEGORIES.map(c => ({
  loc: c.url,
  lastmod: c.lastmod || '2024-05-15T10:00:00+00:00'
}));

// 3. Build Products Sitemap entries
const productsEntries = ENHANCED_PRODUCTS.map(p => ({
  loc: p.url,
  lastmod: p.lastmod || '2024-05-18T10:00:00+00:00'
}));

// 4. Build Blogs Sitemap entries
const blogsEntries = ENHANCED_BLOGS.map(b => ({
  loc: b.url,
  lastmod: b.lastmod || '2024-05-20T10:00:00+00:00'
}));

// Generate XML content strings
const pagesXml = generateChildSitemapXml(pagesEntries);
const categoriesXml = generateChildSitemapXml(categoriesEntries);
const productsXml = generateChildSitemapXml(productsEntries);
const blogsXml = generateChildSitemapXml(blogsEntries);

// Compute content hashes
const childSitemaps = [
  { name: 'pages-sitemap.xml', xml: pagesXml, count: pagesEntries.length },
  { name: 'categories-sitemap.xml', xml: categoriesXml, count: categoriesEntries.length },
  { name: 'products-sitemap.xml', xml: productsXml, count: productsEntries.length },
  { name: 'blogs-sitemap.xml', xml: blogsXml, count: blogsEntries.length }
];

const sitemapIndexEntries = [];

for (const item of childSitemaps) {
  const currentHash = computeHash(item.xml);
  const cachedData = cache[item.name] || {};
  
  let lastmod = cachedData.lastmod;
  
  // If hash changed or no cached lastmod, calculate new timestamp
  if (!cachedData.hash || cachedData.hash !== currentHash || !lastmod) {
    lastmod = '2026-08-29T14:30:00+00:00';
    cache[item.name] = {
      hash: currentHash,
      lastmod
    };
  }

  sitemapIndexEntries.push({
    loc: `https://runonconsole.com/sitemaps/${item.name}`,
    lastmod
  });

  // Write child sitemap to public/sitemaps/ and dist/sitemaps/
  const publicPath = path.join(publicSitemapsDir, item.name);
  fs.writeFileSync(publicPath, item.xml, 'utf-8');

  if (distSitemapsDir) {
    const distPath = path.join(distSitemapsDir, item.name);
    fs.writeFileSync(distPath, item.xml, 'utf-8');
  }

  console.log(`  ✓ Written sitemaps/${item.name} (${item.count} URLs)`);
}

// Build Parent Sitemap Index XML
const parentIndexXml = `<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${sitemapIndexEntries.map(e => `  <sitemap>
    <loc>${escapeXml(e.loc)}</loc>
    <lastmod>${e.lastmod}</lastmod>
  </sitemap>`).join('\n')}
</sitemapindex>
`;

// Write parent sitemap.xml to public/ and dist/
fs.writeFileSync(path.join(PUBLIC_DIR, 'sitemap.xml'), parentIndexXml, 'utf-8');
if (fs.existsSync(DIST_DIR)) {
  fs.writeFileSync(path.join(DIST_DIR, 'sitemap.xml'), parentIndexXml, 'utf-8');
}

// Persist updated build cache
fs.writeFileSync(CACHE_FILE, JSON.stringify(cache, null, 2), 'utf-8');

console.log('✅ Parent sitemap.xml index and 4 child sitemaps generated successfully!');
