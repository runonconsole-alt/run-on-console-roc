import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { execSync } from 'child_process';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const ROOT_DIR = path.resolve(__dirname, '..');
const PUBLIC_API_V1 = path.join(ROOT_DIR, 'public', 'api', 'v1');
const REPAIR_ZIP = path.join(ROOT_DIR, 'run-on-console-php-v1-repair.zip');

const SCRATCH_DIR = path.join(ROOT_DIR, 'scratch', `php_repair_${Date.now()}`);
const API_V1_DEST = path.join(SCRATCH_DIR, 'api', 'v1');

function copyDirRecursive(src, dest) {
  fs.mkdirSync(dest, { recursive: true });
  const entries = fs.readdirSync(src, { withFileTypes: true });
  for (const entry of entries) {
    const srcPath = path.join(src, entry.name);
    const destPath = path.join(dest, entry.name);
    if (entry.isDirectory()) {
      copyDirRecursive(srcPath, destPath);
    } else if (entry.isFile()) {
      fs.copyFileSync(srcPath, destPath);
    }
  }
}

// 1. Create scratch target directory
fs.mkdirSync(API_V1_DEST, { recursive: true });

// 2. Copy actual DEPLOYMENT-README.txt into scratch directory
const rootReadme = path.join(ROOT_DIR, 'DEPLOYMENT-README.txt');
if (fs.existsSync(rootReadme)) {
  fs.copyFileSync(rootReadme, path.join(SCRATCH_DIR, 'DEPLOYMENT-README.txt'));
}

// 3. Copy account repair PHP files into api/v1/
const phpFiles = [
  'account-runtime.php',
  'auth.php',
  'config.php',
  'gaming-profile.php',
  'profile.php'
];

for (const file of phpFiles) {
  if (fs.existsSync(path.join(PUBLIC_API_V1, file))) {
    fs.copyFileSync(path.join(PUBLIC_API_V1, file), path.join(API_V1_DEST, file));
  }
}

// Copy cron folder (Only process-email-queue.php and PHPMailer engine)
fs.mkdirSync(path.join(API_V1_DEST, 'cron'), { recursive: true });
const cronFiles = ['process-email-queue.php', 'cli-migrate-roc-agent.php', 'backfill-blog-status.php', 'seed-roc-agent-games.php'];
for (const file of cronFiles) {
  if (fs.existsSync(path.join(PUBLIC_API_V1, 'cron', file))) {
    fs.copyFileSync(path.join(PUBLIC_API_V1, 'cron', file), path.join(API_V1_DEST, 'cron', file));
  }
}

if (fs.existsSync(path.join(PUBLIC_API_V1, 'cron', 'PHPMailer'))) {
  copyDirRecursive(path.join(PUBLIC_API_V1, 'cron', 'PHPMailer'), path.join(API_V1_DEST, 'cron', 'PHPMailer'));
}

// Copy oauth folder
fs.mkdirSync(path.join(API_V1_DEST, 'oauth'), { recursive: true });
const oauthFiles = ['google-start.php', 'google-callback.php', 'google-token.php'];
for (const file of oauthFiles) {
  if (fs.existsSync(path.join(PUBLIC_API_V1, 'oauth', file))) {
    fs.copyFileSync(path.join(PUBLIC_API_V1, 'oauth', file), path.join(API_V1_DEST, 'oauth', file));
  }
}

// Copy agent folder
if (fs.existsSync(path.join(PUBLIC_API_V1, 'agent'))) {
  copyDirRecursive(path.join(PUBLIC_API_V1, 'agent'), path.join(API_V1_DEST, 'agent'));
}

if (fs.existsSync(REPAIR_ZIP)) {
  try { fs.unlinkSync(REPAIR_ZIP); } catch (e) {}
}

// Create ZIP and normalize all entry paths to standard forward slashes (/)
const psCommand = `
$scratch = '${SCRATCH_DIR.replace(/\\/g, '/')}';
$zipPath = '${REPAIR_ZIP.replace(/\\/g, '/')}';

Add-Type -Assembly 'System.IO.Compression';
Add-Type -Assembly 'System.IO.Compression.FileSystem';

[System.IO.Compression.ZipFile]::CreateFromDirectory($scratch, $zipPath);

$zip = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Update);
$entries = @($zip.Entries);

foreach ($entry in $entries) {
    if ($entry.FullName.Contains('\\')) {
        $normalized = $entry.FullName.Replace('\\', '/');
        $ms = New-Object System.IO.MemoryStream;
        $stream = $entry.Open();
        $stream.CopyTo($ms);
        $stream.Close();
        $entry.Delete();

        $newEntry = $zip.CreateEntry($normalized);
        $ns = $newEntry.Open();
        $ms.Position = 0;
        $ms.CopyTo($ns);
        $ns.Close();
        $ms.Close();
    }
}
$zip.Dispose();
`;

execSync(`powershell -Command "${psCommand.replace(/\n/g, ' ')}"`);
console.log('✅ Complete PHP API ZIP run-on-console-php-v1-repair.zip created successfully (Standard forward-slash paths)!');
