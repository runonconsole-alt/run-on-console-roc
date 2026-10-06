import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { execSync } from 'child_process';
import { createZipWithForwardSlashes } from './create-zip-helper.mjs';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const ROOT_DIR = path.resolve(__dirname, '..');
const STAGING_ZIP = path.join(ROOT_DIR, 'runonconsole-staging-build.zip');
const STAGING_FIXED_ZIP = path.join(ROOT_DIR, 'runonconsole-staging-build-fixed.zip');

console.log('🚀 Building Staging Frontend Assets & Packages in Fresh Isolated Output Directory...');

// 1. Create a brand-new empty temporary output directory for staging
const STAGING_OUT_DIR = path.join(ROOT_DIR, 'scratch', `staging_build_${Date.now()}`);
if (fs.existsSync(STAGING_OUT_DIR)) {
  fs.rmSync(STAGING_OUT_DIR, { recursive: true, force: true });
}
fs.mkdirSync(STAGING_OUT_DIR, { recursive: true });

// 2. Build Vite directly into the brand-new empty temporary output directory
console.log(`📦 Running Vite build into brand-new empty directory: ${STAGING_OUT_DIR}...`);
execSync(`npx vite build --outDir "${STAGING_OUT_DIR}"`, { cwd: ROOT_DIR, stdio: 'inherit' });

// 3. Generate llms.txt & SSR Pre-rendering in STAGING_OUT_DIR
execSync(`node scripts/generate-llmstxt.mjs --staging --outDir "${STAGING_OUT_DIR}"`, { cwd: ROOT_DIR, stdio: 'inherit' });
execSync(`node scripts/prerender.mjs --staging --outDir "${STAGING_OUT_DIR}"`, { cwd: ROOT_DIR, stdio: 'inherit' });

// 4. Perform Cleanup (remove PHP, sql, maps, sitemaps, verification files) BEFORE manifest generation
function cleanStagingOutput(dir) {
  const entries = fs.readdirSync(dir, { withFileTypes: true });
  for (const entry of entries) {
    const fullPath = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      if (entry.name === 'api' || entry.name === 'sitemaps') {
        try { fs.rmSync(fullPath, { recursive: true, force: true }); } catch (e) {}
      } else {
        cleanStagingOutput(fullPath);
      }
    } else if (entry.isFile()) {
      const ext = path.extname(entry.name).toLowerCase();
      const name = entry.name.toLowerCase();
      if (
        ext === '.php' || 
        name === '.htaccess' || 
        ext === '.env' || 
        ext === '.sql' || 
        ext === '.jsx' || 
        ext === '.map' || 
        name.includes('sitemap') || 
        name.startsWith('google') || 
        name.startsWith('bing')
      ) {
        try { fs.unlinkSync(fullPath); } catch (e) {}
      }
    }
  }
}
cleanStagingOutput(STAGING_OUT_DIR);

// Replace any residual production domain strings in text files with staging domain
function sanitizeProductionUrlsInTextFiles(dir) {
  const entries = fs.readdirSync(dir, { withFileTypes: true });
  for (const entry of entries) {
    const fullPath = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      sanitizeProductionUrlsInTextFiles(fullPath);
    } else if (entry.isFile()) {
      const ext = path.extname(entry.name).toLowerCase();
      if (['.html', '.js', '.css', '.json', '.txt', '.xml', '.svg'].includes(ext)) {
        try {
          const content = fs.readFileSync(fullPath, 'utf-8');
          if (content.includes('https://runonconsole.com')) {
            const updated = content.replace(/https:\/\/runonconsole\.com/g, 'https://staging.runonconsole.com');
            fs.writeFileSync(fullPath, updated, 'utf-8');
          }
        } catch (e) {}
      }
    }
  }
}
sanitizeProductionUrlsInTextFiles(STAGING_OUT_DIR);

// Explicitly write staging robots.txt (Disallow all crawlers)
fs.writeFileSync(path.join(STAGING_OUT_DIR, 'robots.txt'), 'User-agent: *\nDisallow: /\n', 'utf-8');

// 5. Generate Release Manifest AFTER cleanup so route count & asset list are 100% accurate!
execSync(`node scripts/generate-release-manifest.mjs --staging --outDir "${STAGING_OUT_DIR}"`, { cwd: ROOT_DIR, stdio: 'inherit' });

// 6. Trace final HTML files and package ONLY referenced assets
function filterAndKeepReferencedAssets(dir) {
  const htmlFiles = [];
  function findHtml(d) {
    for (const ent of fs.readdirSync(d, { withFileTypes: true })) {
      const p = path.join(d, ent.name);
      if (ent.isDirectory()) findHtml(p);
      else if (ent.isFile() && ent.name.endsWith('.html')) htmlFiles.push(p);
    }
  }
  findHtml(dir);

  let allHtmlContent = '';
  for (const hf of htmlFiles) {
    allHtmlContent += fs.readFileSync(hf, 'utf-8') + '\n';
  }

  const cssDir = path.join(dir, 'assets');
  let allCssContent = '';
  if (fs.existsSync(cssDir)) {
    for (const f of fs.readdirSync(cssDir)) {
      if (f.endsWith('.css')) {
        allCssContent += fs.readFileSync(path.join(cssDir, f), 'utf-8') + '\n';
      }
    }
  }

  const combinedRefs = allHtmlContent + allCssContent;

  if (fs.existsSync(cssDir)) {
    for (const assetFile of fs.readdirSync(cssDir)) {
      const assetPath = path.join(cssDir, assetFile);
      if (fs.statSync(assetPath).isFile()) {
        const isReferenced = combinedRefs.includes(assetFile);
        if (!isReferenced) {
          console.log(`🧹 Removing orphan asset unreferenced by HTML/CSS: ${assetFile}`);
          try { fs.unlinkSync(assetPath); } catch (e) {}
        }
      }
    }
  }
}
filterAndKeepReferencedAssets(STAGING_OUT_DIR);

// 7. Create ZIP Package with 100% Forward Slashes and unzip -Z1 validation
createZipWithForwardSlashes(STAGING_OUT_DIR, STAGING_FIXED_ZIP);
try { fs.copyFileSync(STAGING_FIXED_ZIP, STAGING_ZIP); } catch (e) {}

console.log('✅ Staging Frontend ZIP runonconsole-staging-build-fixed.zip created successfully!');
