param(
    [string]$SourceDir,
    [string]$ZipPath
)

Add-Type -Assembly "System.IO.Compression"
Add-Type -Assembly "System.IO.Compression.FileSystem"

if (Test-Path $ZipPath) {
    Remove-Item -Force $ZipPath
}

$zip = [System.IO.Compression.ZipFile]::Open($ZipPath, [System.IO.Compression.ZipArchiveMode]::Create)
$sourceDirClean = (Get-Item $SourceDir).FullName

$files = Get-ChildItem -Path $sourceDirClean -Recurse

foreach ($file in $files) {
    if (-not $file.PSIsContainer) {
        $relPath = $file.FullName.Substring($sourceDirClean.Length).TrimStart([char[]]@('\', '/')).Replace('\', '/')
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $file.FullName, $relPath, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
    }
}
$zip.Dispose()
