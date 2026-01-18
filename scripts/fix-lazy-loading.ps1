# PowerShell script to fix lazy loading syntax errors
# Fixes: <?= ... ? loading="lazy"> to <?= ... ?>" loading="lazy">

$files = Get-ChildItem -Path "c:\Home Directory\views\modern" -Recurse -Filter *.php

$filesFixed = 0

foreach ($file in $files) {
    $content = Get-Content $file.FullName -Raw
    $originalContent = $content

    # Fix pattern: ? loading="lazy"> should be ?>" loading="lazy">
    $content = $content -replace '\?\s*loading="lazy">', '?>" loading="lazy"'

    if ($content -ne $originalContent) {
        Set-Content -Path $file.FullName -Value $content -NoNewline
        $filesFixed++
        Write-Host "Fixed: $($file.Name)" -ForegroundColor Green
    }
}

Write-Host " "
Write-Host "================================" -ForegroundColor Cyan
Write-Host "Files fixed: $filesFixed" -ForegroundColor Green
Write-Host "================================" -ForegroundColor Cyan
