import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { createZipWithForwardSlashes } from './create-zip-helper.mjs';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const ROOT_DIR = path.resolve(__dirname, '..');
const CMS_ZIP = path.join(ROOT_DIR, 'backend-cms-fixed.zip');
const SCRATCH_DIR = path.join(ROOT_DIR, 'scratch', `cms_pkg_${Date.now()}`);

function copyDirRecursive(src, dest, ignoreDirs = []) {
  fs.mkdirSync(dest, { recursive: true });
  const entries = fs.readdirSync(src, { withFileTypes: true });
  for (const entry of entries) {
    if (ignoreDirs.includes(entry.name)) continue;
    const srcPath = path.join(src, entry.name);
    const destPath = path.join(dest, entry.name);
    if (entry.isDirectory()) {
      copyDirRecursive(srcPath, destPath, ignoreDirs);
    } else if (entry.isFile()) {
      fs.copyFileSync(srcPath, destPath);
    }
  }
}

if (fs.existsSync(SCRATCH_DIR)) {
  try { fs.rmSync(SCRATCH_DIR, { recursive: true, force: true }); } catch (e) {}
}
fs.mkdirSync(SCRATCH_DIR, { recursive: true });

// Copy public/api/v1/cms and public/api/v1/cron
const apiCmsSrc = path.join(ROOT_DIR, 'public/api/v1/cms');
if (fs.existsSync(apiCmsSrc)) {
  copyDirRecursive(apiCmsSrc, path.join(SCRATCH_DIR, 'api/v1/cms'));
}

const apiCronSrc = path.join(ROOT_DIR, 'public/api/v1/cron');
if (fs.existsSync(apiCronSrc)) {
  copyDirRecursive(apiCronSrc, path.join(SCRATCH_DIR, 'api/v1/cron'));
}

// Copy public/.htaccess, public/index.php, public/sitemap.xml.php, public/cms
const rootFiles = ['.htaccess', 'index.php', 'sitemap.xml.php'];
for (const f of rootFiles) {
  const fp = path.join(ROOT_DIR, 'public', f);
  if (fs.existsSync(fp)) {
    fs.copyFileSync(fp, path.join(SCRATCH_DIR, f));
  }
}

const cmsHtmlDir = path.join(ROOT_DIR, 'public/cms');
if (fs.existsSync(cmsHtmlDir)) {
  copyDirRecursive(cmsHtmlDir, path.join(SCRATCH_DIR, 'cms'));
}

createZipWithForwardSlashes(SCRATCH_DIR, CMS_ZIP);
console.log(`✅ Created backend-cms-fixed.zip successfully.`);
