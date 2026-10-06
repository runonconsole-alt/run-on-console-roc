import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createServer } from 'vite';
import { 
  STATIC_PUBLIC_ROUTES, 
  PRIVATE_ROUTES, 
  ENHANCED_BLOGS, 
  ENHANCED_CATEGORIES, 
  ENHANCED_PRODUCTS 
} from '../src/seo/routeRegistry.js';
import { getSeoMetadata } from '../src/seo/seoConfig.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const outDirArgIdx = process.argv.indexOf('--outDir');
const customOutDir = outDirArgIdx !== -1 && process.argv[outDirArgIdx + 1] ? path.resolve(__dirname, '..', process.argv[outDirArgIdx + 1]) : null;
const DIST_DIR = customOutDir || path.resolve(__dirname, '../dist');
const TEMPLATE_PATH = path.join(DIST_DIR, 'index.html');

const isStaging = process.argv.includes('--staging') || process.env.VITE_APP_ENV === 'staging';
const BASE_DOMAIN = isStaging ? 'https://staging.runonconsole.com' : 'https://runonconsole.com';

console.log(`🚀 Starting True React SSR Pre-Rendering Generation for Run On Console (${isStaging ? 'STAGING' : 'PRODUCTION'})...`);

if (!fs.existsSync(TEMPLATE_PATH)) {
  console.error('❌ Error: dist/index.html not found! Run "vite build" first.');
  process.exit(1);
}

// Store original clean Vite template HTML
const baseViteTemplate = fs.readFileSync(TEMPLATE_PATH, 'utf-8');

// Initialize Vite SSR Dev Server instance for module loading
const vite = await createServer({
  configFile: false,
  server: { middlewareMode: true, watch: { ignored: ['**/*'] } },
  appType: 'custom'
});

const { renderServer } = await vite.ssrLoadModule('./src/entry-server.jsx');

// Collect all target routes
const routes = [
  ...STATIC_PUBLIC_ROUTES.map(r => r.path),
  ...ENHANCED_BLOGS.map(b => `/blogs/${b.slug}/`),
  ...ENHANCED_CATEGORIES.map(c => `/categories/${c.slug}/`),
  ...ENHANCED_PRODUCTS.map(p => `/products/${p.slug}/`),
  ...PRIVATE_ROUTES.map(r => r.path),
  '/404.html'
];

const uniqueRoutes = Array.from(new Set(routes));

console.log(`📌 Pre-rendering ${uniqueRoutes.length} total routes using React DOM Server renderToString...`);

let renderedCount = 0;

for (const routePath of uniqueRoutes) {
  const is404Route = routePath === '/404.html';
  const seo = getSeoMetadata(is404Route ? '/404/' : routePath);
  
  const title = is404Route ? 'Page Not Found (404) | Run On Console' : (seo.title || 'Run On Console');
  const description = is404Route ? 'The requested page could not be found on Run On Console.' : (seo.description || 'Independent gaming hardware intelligence lab.');
  const rawCanonical = is404Route ? '' : (seo.canonical || 'https://runonconsole.com/');
  const canonical = isStaging ? rawCanonical.replace('https://runonconsole.com', 'https://staging.runonconsole.com') : rawCanonical;
  const robots = isStaging ? 'noindex, nofollow' : (is404Route ? 'noindex, follow' : (seo.robots || 'index, follow'));
  const ogType = seo.ogType || 'website';
  const rawOgImage = seo.ogImage || 'https://runonconsole.com/images/hero_cod.jpg';
  const ogImage = isStaging ? rawOgImage.replace('https://runonconsole.com', 'https://staging.runonconsole.com') : rawOgImage;
  
  const jsonLdString = (!is404Route && seo.jsonLd && seo.jsonLd.length > 0) 
    ? seo.jsonLd.map(s => {
        let jsonStr = JSON.stringify(s, null, 2);
        if (isStaging) jsonStr = jsonStr.replace(/https:\/\/runonconsole\.com/g, 'https://staging.runonconsole.com');
        return `<script type="application/ld+json">\n${jsonStr}\n</script>`;
      }).join('\n')
    : '';

  const canonicalLinkTag = canonical ? `<link rel="canonical" href="${escapeHtml(canonical)}" />` : '';

  const metaHeadTags = `
    <title>${escapeHtml(title)}</title>
    <meta name="title" content="${escapeHtml(title)}" />
    <meta name="description" content="${escapeHtml(description)}" />
    <meta name="robots" content="${escapeHtml(robots)}">
    ${canonicalLinkTag}

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="${escapeHtml(ogType)}" />
    ${canonical ? `<meta property="og:url" content="${escapeHtml(canonical)}" />` : ''}
    <meta property="og:title" content="${escapeHtml(title)}" />
    <meta property="og:description" content="${escapeHtml(description)}" />
    <meta property="og:image" content="${escapeHtml(ogImage)}" />
    <meta property="og:site_name" content="Run On Console" />

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image" />
    ${canonical ? `<meta name="twitter:url" content="${escapeHtml(canonical)}" />` : ''}
    <meta name="twitter:title" content="${escapeHtml(title)}" />
    <meta name="twitter:description" content="${escapeHtml(description)}" />
    <meta name="twitter:image" content="${escapeHtml(ogImage)}" />

    <!-- LLMS.txt AI Discovery Link -->
    <link rel="describedby" href="${BASE_DOMAIN}/llms.txt" type="text/plain" />

    ${jsonLdString}
  `;

  // Render actual React JSX component tree to HTML string
  let reactAppHtml = '';
  try {
    reactAppHtml = renderServer(routePath === '/404.html' ? '/nonexistent-404-render-route/' : routePath);
  } catch (err) {
    console.error(`⚠️ Error rendering React tree for route ${routePath}:`, err);
  }

  // Construct clean pristine HTML with route-specific content inside #root ONLY
  const html = buildPristineRouteHtml(baseViteTemplate, reactAppHtml, metaHeadTags);

  // Determine output filepath
  let targetFile;
  if (routePath === '/') {
    targetFile = path.join(DIST_DIR, 'index.html');
  } else if (routePath === '/404.html') {
    targetFile = path.join(DIST_DIR, '404.html');
  } else {
    const cleanDir = routePath.replace(/^\/+|\/+$/g, '');
    const routeDir = path.join(DIST_DIR, cleanDir);
    try {
      if (!fs.existsSync(routeDir)) {
        fs.mkdirSync(routeDir, { recursive: true });
      }
    } catch (e) {}
    targetFile = path.join(routeDir, 'index.html');
  }

  safeWriteFileSync(targetFile, html);
  renderedCount++;
}

await vite.close();

console.log(`✅ Pre-rendered ${renderedCount} static HTML files using True React SSR successfully!`);

function safeWriteFileSync(filePath, content) {
  try {
    fs.writeFileSync(filePath, content, 'utf-8');
  } catch (err) {
    try {
      fs.chmodSync(filePath, 0o666);
      fs.writeFileSync(filePath, content, 'utf-8');
    } catch (e2) {
      console.warn(`⚠️ Warning: Could not write ${filePath}: ${e2.message}`);
    }
  }
}

function buildPristineRouteHtml(templateHtml, appContent, headTags) {
  let html = templateHtml;

  // Clean existing title, meta tags, hardcoded JSON-LD scripts, and any module script tags from templateHtml
  html = html.replace(/<title>[\s\S]*?<\/title>/gi, '');
  html = html.replace(/<meta name="title"[\s\S]*?\/>/gi, '');
  html = html.replace(/<meta name="description"[\s\S]*?\/>/gi, '');
  html = html.replace(/<meta name="robots"[\s\S]*?>/gi, '');
  html = html.replace(/<link rel="canonical"[\s\S]*?\/>/gi, '');
  html = html.replace(/<meta property="og:[\s\S]*?\/>/gi, '');
  html = html.replace(/<meta name="twitter:[\s\S]*?\/>/gi, '');
  html = html.replace(/<script type="application\/ld\+json">[\s\S]*?<\/script>/gi, '');

  if (isStaging) {
    html = html.replace(/<script[\s\S]*?(gtm\.js|GTM-|clarity|gtag|googletagmanager)[\s\S]*?<\/script>/gi, '');
    html = html.replace(/<noscript>[\s\S]*?(gtm\.js|GTM-|googletagmanager)[\s\S]*?<\/noscript>/gi, '');
    html = html.replace(/<!-- Google Tag Manager -->[\s\S]*?<!-- End Google Tag Manager -->/gi, '');
    html = html.replace(/<!-- Microsoft Clarity Analytics -->[\s\S]*?<!-- End Microsoft Clarity Analytics -->/gi, '');
    html = html.replace(/<!-- Google tag \(gtag\.js\) GA4 -->[\s\S]*?<!-- End Google tag -->/gi, '');
    html = html.replace(/https:\/\/runonconsole\.com/g, 'https://staging.runonconsole.com');
  }

  // Extract production script tags from templateHtml (e.g. <script type="module" crossorigin src="/assets/index-*.js"></script>)
  const scriptTagsMatch = templateHtml.match(/<script type="module"[\s\S]*?<\/script>/gi);
  let scriptTagsHtml = '';
  
  if (scriptTagsMatch && scriptTagsMatch.length > 0) {
    const prodScripts = scriptTagsMatch.filter(s => !s.includes('/src/main.jsx'));
    if (prodScripts.length > 0) {
      scriptTagsHtml = Array.from(new Set(prodScripts))[0]; // Take EXACTLY ONE single module script tag
    }
  }

  if (!scriptTagsHtml) {
    const distIndexHtml = fs.readFileSync(templatePath, 'utf-8');
    const distScriptMatch = distIndexHtml.match(/<script type="module"[\s\S]*?<\/script>/gi);
    if (distScriptMatch) {
      const filtered = distScriptMatch.filter(s => !s.includes('/src/main.jsx'));
      scriptTagsHtml = Array.from(new Set(filtered))[0];
    }
  }

  if (!scriptTagsHtml || scriptTagsHtml.includes('/src/main.jsx')) {
    throw new Error('Production HTML must contain a valid compiled asset script tag without /src/main.jsx');
  }

  // Inject metadata into <head>
  html = html.replace('</head>', `${headTags}\n</head>`);

  // Extract document head up to <div id="root">
  const rootStartIndex = html.indexOf('<div id="root">');
  if (rootStartIndex === -1) {
    throw new Error('Template missing <div id="root"> marker');
  }

  let docBeforeRoot = html.substring(0, rootStartIndex);

  // Strip any module script tag inside <head> so it is only present once before </body>
  docBeforeRoot = docBeforeRoot.replace(/<script type="module"[\s\S]*?<\/script>/gi, '');

  // Construct pristine HTML body structure:
  // <body>
  //   <div id="root">ROUTE-SPECIFIC HTML</div>
  //   <script type="module" crossorigin src="/assets/index-*.js"></script>
  // </body>
  // </html>
  const pristineHtml = `${docBeforeRoot}<div id="root">${appContent}</div>\n    ${scriptTagsHtml}\n  </body>\n</html>`;

  // Verify module tag count is EXACTLY 1
  const moduleTagCount = (pristineHtml.match(/<script type="module"/gi) || []).length;
  if (moduleTagCount !== 1) {
    throw new Error(`SSR pre-render error: Generated HTML contains ${moduleTagCount} module script tags instead of 1.`);
  }

  return pristineHtml;
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
