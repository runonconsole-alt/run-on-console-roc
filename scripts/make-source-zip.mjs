import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { createZipWithForwardSlashes } from './create-zip-helper.mjs';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const ROOT_DIR = path.resolve(__dirname, '..');
const SOURCE_ZIP = path.join(ROOT_DIR, 'runonconsole-source.zip');
const SOURCE_FIXED_ZIP = path.join(ROOT_DIR, 'runonconsole-source-fixed.zip');
const SCRATCH_DIR = path.join(ROOT_DIR, 'scratch', `source_pkg_${Date.now()}`);

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

const includeDirs = ['src', 'public', 'scripts'];
for (const dir of includeDirs) {
  const srcDir = path.join(ROOT_DIR, dir);
  if (fs.existsSync(srcDir)) {
    copyDirRecursive(srcDir, path.join(SCRATCH_DIR, dir), ['.git', 'node_modules', 'dist', 'scratch']);
  }
}

const includeFiles = [
  'package.json',
  'vite.config.js',
  'tailwind.config.js',
  'postcss.config.js',
  'index.html',
  'DEPLOYMENT-README.txt',
  'ROC_AGENT_README.md'
];

for (const file of includeFiles) {
  const filePath = path.join(ROOT_DIR, file);
  if (fs.existsSync(filePath)) {
    fs.copyFileSync(filePath, path.join(SCRATCH_DIR, file));
  }
}

createZipWithForwardSlashes(SCRATCH_DIR, SOURCE_FIXED_ZIP);
try { fs.copyFileSync(SOURCE_FIXED_ZIP, SOURCE_ZIP); } catch (e) {}

console.log('✅ Complete Full Source Project ZIP runonconsole-source-fixed.zip created successfully!');
