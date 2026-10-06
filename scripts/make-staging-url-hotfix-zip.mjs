import fs from 'node:fs';
import path from 'node:path';
import { execSync } from 'node:child_process';

const ROOT_DIR = process.cwd();
const targetZip = path.join(ROOT_DIR, 'staging-url-hotfix.zip');
const tempDir = path.join(ROOT_DIR, 'temp_staging_url_hotfix');

console.log('🚀 Creating Staging URL Hotfix ZIP (staging-url-hotfix.zip)...');

// 1. Ensure clean temp state
if (fs.existsSync(tempDir)) {
  fs.rmSync(tempDir, { recursive: true, force: true });
}
if (fs.existsSync(targetZip)) {
  fs.unlinkSync(targetZip);
}

// 2. Copy the 4 modified PHP files from public/api/v1/ to tempDir
const filesToPackage = [
  'api/v1/account-runtime.php',
  'api/v1/agent/blogs.php',
  'api/v1/agent/products.php',
  'api/v1/agent/search.php'
];

for (const relPath of filesToPackage) {
  const srcPath = path.join(ROOT_DIR, 'public', relPath);
  const destPath = path.join(tempDir, relPath);
  fs.mkdirSync(path.dirname(destPath), { recursive: true });
  fs.copyFileSync(srcPath, destPath);
}

// 3. Package staging-url-hotfix.zip with 100% forward slashes using PowerShell ZipArchive
const psCommand = `
$tempFolder = '${tempDir.replace(/\\/g, '\\\\')}';
$zipPath = '${targetZip.replace(/\\/g, '\\\\')}';

Add-Type -AssemblyName System.IO.Compression;
Add-Type -AssemblyName System.IO.Compression.FileSystem;

$zip = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create);

$files = @(
  'api/v1/account-runtime.php',
  'api/v1/agent/blogs.php',
  'api/v1/agent/products.php',
  'api/v1/agent/search.php'
);

foreach ($relPath in $files) {
  $fileToZip = Get-Item (Join-Path $tempFolder ($relPath -replace '/', '\\'));
  [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $fileToZip.FullName, $relPath, [System.IO.Compression.CompressionLevel]::Optimal);
}

$zip.Dispose();
`;

execSync(`powershell -NoProfile -Command "${psCommand.replace(/\r?\n/g, ' ')}"`);

console.log(`✅ Staging URL Hotfix ZIP created successfully at: ${targetZip}`);

// 4. Verify ZIP contents and entry count
const verifyOutput = String(execSync(`powershell -NoProfile -Command "Add-Type -AssemblyName System.IO.Compression.FileSystem; [System.IO.Compression.ZipFile]::OpenRead('${targetZip.replace(/\\/g, '\\\\')}').Entries | Select-Object -ExpandProperty FullName"`, { encoding: 'utf-8' }));
const entries = verifyOutput.split(/\r?\n/).map(l => l.trim()).filter(Boolean);

console.log('📦 ZIP Entries:', entries);
if (entries.length === 4 && entries.every(e => !e.includes('\\'))) {
  console.log('✓ VERIFIED: staging-url-hotfix.zip contains EXACTLY 4 modified PHP files with 100% forward slashes.');
} else {
  console.error('❌ ZIP VERIFICATION FAILED! Unexpected entries:', entries);
  process.exit(1);
}
