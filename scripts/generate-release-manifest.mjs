import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const ROOT_DIR = path.resolve(__dirname, '..');
const DIST_DIR = path.resolve(ROOT_DIR, 'dist');
const isStaging = process.argv.includes('--staging') || process.env.VITE_APP_ENV === 'staging';
const outDirArgIdx = process.argv.indexOf('--outDir');
const customOutDir = outDirArgIdx !== -1 && process.argv[outDirArgIdx + 1] ? path.resolve(ROOT_DIR, process.argv[outDirArgIdx + 1]) : null;
const TARGET_DIST = customOutDir || DIST_DIR;

console.log(`📋 Generating ${isStaging ? 'STAGING' : 'Production'} Release Manifest for Run On Console...`);

if (!fs.existsSync(TARGET_DIST)) {
  console.error(`❌ Error: ${TARGET_DIST} directory not found!`);
  process.exit(1);
}

// Read exact active JS & CSS asset filenames from index.html
const indexHtmlPath = path.join(TARGET_DIST, 'index.html');
let jsFile = '';
let cssFile = '';

if (fs.existsSync(indexHtmlPath)) {
  const indexHtml = fs.readFileSync(indexHtmlPath, 'utf-8');
  const jsMatch = indexHtml.match(/src="\/assets\/(index-[^"]+\.js)"/);
  const cssMatch = indexHtml.match(/href="\/assets\/(index-[^"]+\.css)"/);
  if (jsMatch) jsFile = jsMatch[1];
  if (cssMatch) cssFile = cssMatch[1];
}

// Count total pre-rendered HTML files
function countHtmlFiles(dir) {
  let count = 0;
  try {
    const entries = fs.readdirSync(dir, { withFileTypes: true });
    for (const entry of entries) {
      const fullPath = path.join(dir, entry.name);
      if (entry.isDirectory()) {
        count += countHtmlFiles(fullPath);
      } else if (entry.isFile() && entry.name.endsWith('.html')) {
        count++;
      }
    }
  } catch (e) {}
  return count;
}

const routeCount = countHtmlFiles(TARGET_DIST);
const timestamp = new Date().toISOString();
const releaseId = isStaging ? `release-staging-${Date.now()}` : `release-v1.0.5-${Date.now()}`;

const manifest = {
  releaseIdentifier: releaseId,
  buildTimestamp: timestamp,
  jsAssetFilename: jsFile ? `/assets/${jsFile}` : '',
  cssAssetFilename: cssFile ? `/assets/${cssFile}` : '',
  totalRouteCount: routeCount,
  environment: isStaging ? 'staging' : 'production',
  canonicalDomain: isStaging ? 'https://staging.runonconsole.com' : 'https://runonconsole.com'
};

const targetPath = path.join(TARGET_DIST, 'release-manifest.json');
fs.writeFileSync(targetPath, JSON.stringify(manifest, null, 2), 'utf-8');

console.log(`✅ Release Manifest Generated Successfully! (${releaseId}, ${routeCount} Routes, Environment: ${manifest.environment}, JS: ${jsFile}, CSS: ${cssFile})`);
