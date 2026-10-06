import fs from 'node:fs';
import path from 'node:path';
import { execSync } from 'node:child_process';

const ROOT_DIR = process.cwd();
const targetZip = path.join(ROOT_DIR, 'backend-chat-hotfix.zip');
const tempDir = path.join(ROOT_DIR, 'temp_backend_chat_hotfix');

console.log('🚀 Creating Backend Chat Hotfix ZIP (backend-chat-hotfix.zip)...');

if (fs.existsSync(tempDir)) {
  fs.rmSync(tempDir, { recursive: true, force: true });
}
if (fs.existsSync(targetZip)) {
  fs.unlinkSync(targetZip);
}

const filesToPackage = [
  'api/v1/agent/conversations.php'
];

for (const relPath of filesToPackage) {
  const srcPath = path.join(ROOT_DIR, 'public', relPath);
  const destPath = path.join(tempDir, relPath);
  fs.mkdirSync(path.dirname(destPath), { recursive: true });
  fs.copyFileSync(srcPath, destPath);
}

const psCommand = `
$tempFolder = '${tempDir.replace(/\\/g, '\\\\')}';
$zipPath = '${targetZip.replace(/\\/g, '\\\\')}';

Add-Type -AssemblyName System.IO.Compression;
Add-Type -AssemblyName System.IO.Compression.FileSystem;

$zip = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create);

$files = @(
  'api/v1/agent/conversations.php'
);

foreach ($relPath in $files) {
  $fileToZip = Get-Item (Join-Path $tempFolder ($relPath -replace '/', '\\'));
  [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $fileToZip.FullName, $relPath, [System.IO.Compression.CompressionLevel]::Optimal);
}

$zip.Dispose();
`;

execSync(`powershell -NoProfile -Command "${psCommand.replace(/\r?\n/g, ' ')}"`);

console.log(`✅ Backend Chat Hotfix ZIP created successfully at: ${targetZip}`);

const verifyOutput = String(execSync(`powershell -NoProfile -Command "Add-Type -AssemblyName System.IO.Compression.FileSystem; [System.IO.Compression.ZipFile]::OpenRead('${targetZip.replace(/\\/g, '\\\\')}').Entries | Select-Object -ExpandProperty FullName"`, { encoding: 'utf-8' }));
const entries = verifyOutput.split(/\r?\n/).map(l => l.trim()).filter(Boolean);

console.log('📦 ZIP Entries:', entries);
if (entries.length === 1 && entries.every(e => !e.includes('\\'))) {
  console.log('✓ VERIFIED: backend-chat-hotfix.zip contains EXACTLY 1 modified PHP file with 100% forward slashes.');
} else {
  console.error('❌ ZIP VERIFICATION FAILED! Unexpected entries:', entries);
  process.exit(1);
}
