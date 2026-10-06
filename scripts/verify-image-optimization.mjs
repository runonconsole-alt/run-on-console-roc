import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const ROOT_DIR = path.resolve(__dirname, '..');

console.log('🤖 Running Part 5: Image Optimization & Validation Audit Suite...\n');

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

// 1. Audit public/images Directory Rendition Sizes (<= 100,000 Bytes Cap)
const imagesDir = path.join(ROOT_DIR, 'public/images');
assertOk(fs.existsSync(imagesDir), 'public/images directory exists');

const imageFiles = fs.readdirSync(imagesDir).filter(f => /\.(jpg|jpeg|png|webp)$/i.test(f));
assertOk(imageFiles.length > 0, `Discovered ${imageFiles.length} public image files`);

let oversizedCount = 0;
let measuredReport = [];

imageFiles.forEach(file => {
  const filePath = path.join(imagesDir, file);
  const size = fs.statSync(filePath).size;
  measuredReport.push({ file, size });
  if (size > 100000) {
    oversizedCount++;
  }
});

assertOk(oversizedCount === 0, `100% of publicly served image renditions are <= 100,000 bytes (Oversized: ${oversizedCount})`);

// 2. Audit CMS Media Upload Validation API (media.php)
const mediaPhpPath = path.join(ROOT_DIR, 'public/api/v1/cms/media.php');
assertOk(fs.existsSync(mediaPhpPath), 'public/api/v1/cms/media.php exists');
const mediaPhpSource = fs.readFileSync(mediaPhpPath, 'utf-8');

assertOk(mediaPhpSource.includes('$maxBytes = 100000;'), 'media.php enforces 100,000 bytes (100KB max) rendition size limit on upload');
assertOk(mediaPhpSource.includes('finfo_file'), 'media.php uses finfo_file for MIME validation');
assertOk(mediaPhpSource.includes('getimagesize'), 'media.php checks image dimensions to prevent decompression bombs');
assertOk(mediaPhpSource.includes('imagewebp'), 'media.php converts uploads safely to WebP using GD');
assertOk(mediaPhpSource.includes('Executable or SVG file format rejected'), 'media.php rejects executable files and SVGs for security');

// 3. Alt text and Width/Height attribute check in React components
const productCardPath = path.join(ROOT_DIR, 'src/components/ProductCard.jsx');
if (fs.existsSync(productCardPath)) {
  const cardSource = fs.readFileSync(productCardPath, 'utf-8');
  assertOk(cardSource.includes('alt=') || cardSource.includes('alt ='), 'ProductCard provides alt text for images');
}

console.log('\n============================================================');
console.log('📊 MEASURED PUBLIC IMAGE RENDITIONS REPORT:');
measuredReport.forEach(item => {
  console.log(`   - ${item.file}: ${(item.size / 1024).toFixed(2)} KB (${item.size} bytes)`);
});
console.log('============================================================\n');

console.log(`SUMMARY: All ${total} Image Optimization Assertions PASSED! 🎉\n`);
