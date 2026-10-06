Add-Type -AssemblyName System.Drawing

$imagesDir = "c:\Users\HP 650 G4\OneDrive\Documents\GameForgeHub\public\images"
$files = Get-ChildItem -Path $imagesDir -File -Include *.jpg,*.jpeg,*.png,*.webp -Recurse

Write-Host "Optimizing existing public image renditions to under 100000 bytes..."

foreach ($file in $files) {
    $fileLen = $file.Length
    $fileName = $file.Name
    $filePath = $file.FullName

    if ($fileLen -gt 100000) {
        Write-Host "Processing: $fileName ($fileLen bytes)"
        
        $img = [System.Drawing.Image]::FromFile($filePath)
        $origWidth = $img.Width
        $origHeight = $img.Height
        $aspectRatio = $origWidth / $origHeight

        # Target dimensions
        $targetWidth = [Math]::Min($origWidth, 1200)
        $targetHeight = [int]($targetWidth / $aspectRatio)
        $quality = 75L

        $tempPath = $filePath + ".tmp"
        $finalSuccess = $false
        
        # Iterative compression loop
        do {
            $bmp = new-object System.Drawing.Bitmap $targetWidth, $targetHeight
            $graph = [System.Drawing.Graphics]::FromImage($bmp)
            $graph.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
            $graph.DrawImage($img, 0, 0, $targetWidth, $targetHeight)
            $graph.Dispose()

            $jpegCodec = [System.Drawing.Imaging.ImageCodecInfo]::GetImageEncoders() | Where-Object { $_.MimeType -eq "image/jpeg" }
            $encoderParams = New-Object System.Drawing.Imaging.EncoderParameters(1)
            $encoderParams.Param[0] = New-Object System.Drawing.Imaging.EncoderParameter([System.Drawing.Imaging.Encoder]::Quality, $quality)

            $bmp.Save($tempPath, $jpegCodec, $encoderParams)
            $bmp.Dispose()

            $size = (Get-Item $tempPath).Length

            if ($size -le 100000) {
                $finalSuccess = $true
                break
            }

            if ($quality -gt 35L) {
                $quality -= 15L
            } else {
                $targetWidth = [int]($targetWidth * 0.8)
                $targetHeight = [int]($targetHeight * 0.8)
                $quality = 75L
            }
        } while ($targetWidth -gt 100 -and $targetHeight -gt 100)

        $img.Dispose()

        if ($finalSuccess) {
            Move-Item -Path $tempPath -Destination $filePath -Force
            $finalSize = (Get-Item $filePath).Length
            Write-Host "Optimized: $fileName -> $finalSize bytes ($targetWidth x $targetHeight)"
        } else {
            Remove-Item -Path $tempPath -Force -ErrorAction SilentlyContinue
            Write-Host "Failed to compress $fileName below 100KB limit."
        }
    } else {
        Write-Host "Already compliant: $fileName ($fileLen bytes)"
    }
}
