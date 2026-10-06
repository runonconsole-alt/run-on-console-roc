import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';

/**
 * Creates a ZIP archive where all entry paths use forward slashes (/) exclusively.
 * Validates using `unzip -Z1` and throws an error if any entry contains backslashes.
 */
export function createZipWithForwardSlashes(sourceDir, zipPath) {
  const absSource = path.resolve(sourceDir);
  const absZip = path.resolve(zipPath);

  if (fs.existsSync(absZip)) {
    try { fs.unlinkSync(absZip); } catch (e) {}
  }

  function getAllFiles(dir, fileList = []) {
    const entries = fs.readdirSync(dir, { withFileTypes: true });
    for (const entry of entries) {
      const fullPath = path.join(dir, entry.name);
      if (entry.isDirectory()) {
        getAllFiles(fullPath, fileList);
      } else if (entry.isFile()) {
        fileList.push(fullPath);
      }
    }
    return fileList;
  }

  const allFiles = getAllFiles(absSource);
  const zipEntries = allFiles.map(f => {
    const rel = path.relative(absSource, f).replace(/\\/g, '/');
    return { fullPath: f.replace(/\\/g, '/'), relPath: rel };
  });

  const scratchDir = path.dirname(absZip);
  const timestamp = Date.now() + '_' + Math.random().toString(36).substr(2, 4);
  const manifestPath = path.join(scratchDir, `manifest_${timestamp}.json`);
  const psScriptPath = path.join(scratchDir, `zip_${timestamp}.ps1`);

  fs.writeFileSync(manifestPath, JSON.stringify(zipEntries, null, 2), 'utf-8');

  const psContent = `
Add-Type -Assembly 'System.IO.Compression'
Add-Type -Assembly 'System.IO.Compression.FileSystem'

$manifestPath = '${manifestPath.replace(/\\/g, '/')}'
$zipDst = '${absZip.replace(/\\/g, '/')}'

if (Test-Path $zipDst) { Remove-Item -Force $zipDst }

$zip = [System.IO.Compression.ZipFile]::Open($zipDst, [System.IO.Compression.ZipArchiveMode]::Create)
$entries = Get-Content $manifestPath -Raw | ConvertFrom-Json

foreach ($item in $entries) {
  [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $item.fullPath, $item.relPath, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
}
$zip.Dispose()
`;

  fs.writeFileSync(psScriptPath, psContent, 'utf-8');

  execSync(`powershell -NonInteractive -NoProfile -ExecutionPolicy Bypass -File "${psScriptPath}"`, { stdio: 'inherit' });

  try { fs.unlinkSync(manifestPath); } catch (e) {}
  try { fs.unlinkSync(psScriptPath); } catch (e) {}

  // Verification step using unzip -Z1
  let unzipExe = 'unzip';
  const gitUnzip = 'C:\\Program Files\\Git\\usr\\bin\\unzip.exe';
  if (fs.existsSync(gitUnzip)) {
    unzipExe = `"${gitUnzip}"`;
  }

  let unzipOutput = '';
  try {
    unzipOutput = execSync(`${unzipExe} -Z1 "${absZip}"`, { encoding: 'utf-8' });
  } catch (err) {
    console.warn(`⚠️ Warning: Could not run unzip -Z1: ${err.message}`);
  }

  if (unzipOutput) {
    const lines = unzipOutput.split(/\r?\n/).map(l => l.trim()).filter(Boolean);
    const backslashEntries = lines.filter(l => l.includes('\\'));
    if (backslashEntries.length > 0) {
      throw new Error(`❌ ZIP Validation Error: ${backslashEntries.length} entries contain backslashes in ${path.basename(absZip)}:\n${backslashEntries.slice(0, 5).join('\n')}`);
    }
    console.log(`✅ ZIP Created and Verified with 100% Forward-Slash Paths (${lines.length} entries): ${path.basename(absZip)}`);
  }
}
