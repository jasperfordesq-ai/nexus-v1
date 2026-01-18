# PowerShell Script: Convert Images to WebP
# Converts JPG and PNG images to WebP format for 25-35% smaller file sizes

Write-Host "WebP Image Conversion Script" -ForegroundColor Cyan
Write-Host "============================" -ForegroundColor Cyan
Write-Host ""

# Check if cwebp is available
$cwebpPath = Get-Command cwebp -ErrorAction SilentlyContinue

if (-not $cwebpPath) {
    Write-Host "ERROR: cwebp not found!" -ForegroundColor Red
    Write-Host ""
    Write-Host "Please install cwebp:" -ForegroundColor Yellow
    Write-Host "1. Download from: https://developers.google.com/speed/webp/download" -ForegroundColor Yellow
    Write-Host "2. Or install via Chocolatey: choco install webp" -ForegroundColor Yellow
    Write-Host "3. Or install via Scoop: scoop install libwebp" -ForegroundColor Yellow
    Write-Host ""
    exit 1
}

Write-Host "cwebp found: $($cwebpPath.Source)" -ForegroundColor Green
Write-Host ""

# Configuration
$searchPaths = @(
    "c:\Home Directory\httpdocs\assets\img",
    "c:\Home Directory\uploads"
)

$quality = 85  # Quality setting (0-100, 85 is recommended)
$totalConverted = 0
$totalOriginalSize = 0
$totalWebPSize = 0
$skipped = 0

# Extensions to convert
$extensions = @("*.jpg", "*.jpeg", "*.png")

foreach ($basePath in $searchPaths) {
    if (-not (Test-Path $basePath)) {
        Write-Host "Skipping (not found): $basePath" -ForegroundColor Yellow
        continue
    }

    Write-Host "Processing: $basePath" -ForegroundColor Cyan
    Write-Host ""

    foreach ($ext in $extensions) {
        $files = Get-ChildItem -Path $basePath -Filter $ext -Recurse -ErrorAction SilentlyContinue

        foreach ($file in $files) {
            $webpPath = [System.IO.Path]::ChangeExtension($file.FullName, ".webp")

            # Skip if WebP already exists and is newer
            if (Test-Path $webpPath) {
                $webpFile = Get-Item $webpPath
                if ($webpFile.LastWriteTime -gt $file.LastWriteTime) {
                    $skipped++
                    continue
                }
            }

            try {
                # Convert to WebP
                $output = & cwebp -q $quality $file.FullName -o $webpPath 2>&1

                if (Test-Path $webpPath) {
                    $originalSize = $file.Length
                    $webpSize = (Get-Item $webpPath).Length
                    $savings = [math]::Round((($originalSize - $webpSize) / $originalSize) * 100, 1)

                    $totalOriginalSize += $originalSize
                    $totalWebPSize += $webpSize
                    $totalConverted++

                    $relativePath = $file.FullName.Replace("c:\Home Directory\", "")
                    Write-Host "✓ $relativePath" -ForegroundColor Green
                    Write-Host "  $([math]::Round($originalSize/1KB, 1)) KB → $([math]::Round($webpSize/1KB, 1)) KB ($savings% smaller)" -ForegroundColor Gray
                } else {
                    Write-Host "✗ Failed: $($file.Name)" -ForegroundColor Red
                }
            } catch {
                Write-Host "✗ Error converting $($file.Name): $($_.Exception.Message)" -ForegroundColor Red
            }
        }
    }
}

Write-Host ""
Write-Host "================================" -ForegroundColor Cyan
Write-Host "Conversion Complete!" -ForegroundColor Green
Write-Host "================================" -ForegroundColor Cyan
Write-Host "Images converted: $totalConverted" -ForegroundColor Green
Write-Host "Images skipped: $skipped" -ForegroundColor Yellow

if ($totalConverted -gt 0) {
    $totalSavingsKB = [math]::Round(($totalOriginalSize - $totalWebPSize) / 1KB, 1)
    $totalSavingsMB = [math]::Round(($totalOriginalSize - $totalWebPSize) / 1MB, 1)
    $savingsPercent = [math]::Round((($totalOriginalSize - $totalWebPSize) / $totalOriginalSize) * 100, 1)

    Write-Host ""
    Write-Host "Total original size: $([math]::Round($totalOriginalSize/1MB, 1)) MB" -ForegroundColor White
    Write-Host "Total WebP size: $([math]::Round($totalWebPSize/1MB, 1)) MB" -ForegroundColor White
    Write-Host "Total savings: $totalSavingsMB MB ($totalSavingsKB KB)" -ForegroundColor Cyan
    Write-Host "Percentage saved: $savingsPercent%" -ForegroundColor Cyan
}

Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "1. Update your HTML to use <picture> tags with WebP" -ForegroundColor Yellow
Write-Host "2. Or use the automatic WebP helper function" -ForegroundColor Yellow
Write-Host "================================" -ForegroundColor Cyan
