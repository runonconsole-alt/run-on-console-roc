import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { execSync } from 'child_process';
import { createZipWithForwardSlashes } from './create-zip-helper.mjs';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const ROOT_DIR = path.resolve(__dirname, '..');
const DIST_DIR = path.join(ROOT_DIR, 'dist');
const ZIP_FILE = path.join(ROOT_DIR, 'runonconsole-build.zip');
const BUILD_FIXED_ZIP = path.join(ROOT_DIR, 'runonconsole-build-fixed.zip');

console.log('🚀 Building Production Assets & Package...');
execSync('npx vite build', { cwd: ROOT_DIR, stdio: 'inherit' });
execSync('node scripts/generate-llmstxt.mjs', { cwd: ROOT_DIR, stdio: 'inherit' });
execSync('node scripts/prerender.mjs', { cwd: ROOT_DIR, stdio: 'inherit' });
execSync('node scripts/generate-sitemap.mjs', { cwd: ROOT_DIR, stdio: 'inherit' });
execSync('node scripts/generate-release-manifest.mjs', { cwd: ROOT_DIR, stdio: 'inherit' });

// Read active JS & CSS asset filenames from dist/index.html to filter out stale bundles
const indexHtmlPath = path.join(DIST_DIR, 'index.html');
let activeJsFile = '';
let activeCssFile = '';

if (fs.existsSync(indexHtmlPath)) {
  const indexHtml = fs.readFileSync(indexHtmlPath, 'utf-8');
  const jsMatch = indexHtml.match(/src="\/assets\/(index-[^"]+\.js)"/);
  const cssMatch = indexHtml.match(/href="\/assets\/(index-[^"]+\.css)"/);
  if (jsMatch) activeJsFile = jsMatch[1];
  if (cssMatch) activeCssFile = cssMatch[1];
}

const SCRATCH_DIR = path.join(ROOT_DIR, 'scratch', `frontend_zip_${Date.now()}`);
if (fs.existsSync(SCRATCH_DIR)) {
  try { fs.rmSync(SCRATCH_DIR, { recursive: true, force: true }); } catch (e) {}
}
fs.mkdirSync(SCRATCH_DIR, { recursive: true });

function copyFrontendOnly(src, dest) {
  fs.mkdirSync(dest, { recursive: true });
  const entries = fs.readdirSync(src, { withFileTypes: true });
  for (const entry of entries) {
    if (
      entry.name === '.htaccess' ||
      entry.name === 'api' ||
      entry.name.endsWith('.php') ||
      entry.name.endsWith('.sql') ||
      entry.name.endsWith('.log') ||
      entry.name === 'src' ||
      entry.name === 'node_modules' ||
      entry.name === '.env' ||
      entry.name.includes('diagnostic') ||
      entry.name.includes('migration')
    ) {
      continue;
    }

    const srcPath = path.join(src, entry.name);
    const destPath = path.join(dest, entry.name);

    try {
      if (entry.isDirectory()) {
        copyFrontendOnly(srcPath, destPath);
      } else if (entry.isFile()) {
        if (src.endsWith('assets')) {
          if (entry.name.endsWith('.js') && activeJsFile && entry.name !== activeJsFile) {
            continue;
          }
          if (entry.name.endsWith('.css') && activeCssFile && entry.name !== activeCssFile) {
            continue;
          }
        }
        fs.copyFileSync(srcPath, destPath);
      }
    } catch (e) {}
  }
}

copyFrontendOnly(DIST_DIR, SCRATCH_DIR);

createZipWithForwardSlashes(SCRATCH_DIR, BUILD_FIXED_ZIP);
try { fs.copyFileSync(BUILD_FIXED_ZIP, ZIP_FILE); } catch (e) {}

console.log('✅ Frontend ZIP runonconsole-build-fixed.zip created successfully!');
