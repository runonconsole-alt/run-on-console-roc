import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { createZipWithForwardSlashes } from './create-zip-helper.mjs';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const ROOT_DIR = path.resolve(__dirname, '..');

console.log('🚀 Packaging Backend PHP Repair ZIP (runonconsole-backend-php-fixed.zip)...');

const apiSourceDir = path.join(ROOT_DIR, 'public', 'api');
const targetZip = path.join(ROOT_DIR, 'runonconsole-backend-php-fixed.zip');

// We package the api directory structure so it extracts neatly into public/api or api/
// To maintain clean api/ relative paths inside ZIP:
const tempPkgDir = path.join(ROOT_DIR, 'scratch', `backend_php_pkg_${Date.now()}`);

if (fs.existsSync(tempPkgDir)) {
  fs.rmSync(tempPkgDir, { recursive: true, force: true });
}

fs.mkdirSync(path.join(tempPkgDir, 'api'), { recursive: true });

function copyRecursive(src, dst) {
  const stats = fs.statSync(src);
  if (stats.isDirectory()) {
    if (!fs.existsSync(dst)) fs.mkdirSync(dst, { recursive: true });
    for (const file of fs.readdirSync(src)) {
      copyRecursive(path.join(src, file), path.join(dst, file));
    }
  } else if (stats.isFile()) {
    fs.copyFileSync(src, dst);
  }
}

copyRecursive(apiSourceDir, path.join(tempPkgDir, 'api'));

createZipWithForwardSlashes(tempPkgDir, targetZip);

// Cleanup scratch pkg folder
try {
  fs.rmSync(tempPkgDir, { recursive: true, force: true });
} catch (e) {}

console.log(`✅ Backend PHP Repair ZIP created successfully at: ${targetZip}`);
