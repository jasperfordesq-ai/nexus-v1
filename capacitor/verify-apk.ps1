$apkPath = "c:\Home Directory\httpdocs\downloads\nexus-latest.apk"

Write-Host "=== APK Verification ===" -ForegroundColor Cyan
Write-Host ""

# Check file exists and size
$file = Get-Item $apkPath
Write-Host "File: $($file.Name)"
Write-Host "Size: $([math]::Round($file.Length / 1MB, 2)) MB"
Write-Host "Modified: $($file.LastWriteTime)"
Write-Host ""

# Check ZIP signature
$bytes = [System.IO.File]::ReadAllBytes($apkPath)
$sig = [System.Text.Encoding]::ASCII.GetString($bytes[0..1])
if ($sig -eq "PK") {
    Write-Host "ZIP Signature: VALID (PK header found)" -ForegroundColor Green
} else {
    Write-Host "ZIP Signature: INVALID" -ForegroundColor Red
}

# List key files in APK
Write-Host ""
Write-Host "Key APK contents:" -ForegroundColor Cyan
Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::OpenRead($apkPath)
$important = @("AndroidManifest.xml", "classes.dex", "resources.arsc")
foreach ($name in $important) {
    $found = $zip.Entries | Where-Object { $_.Name -eq $name }
    if ($found) {
        Write-Host "  [OK] $name ($([math]::Round($found.Length / 1KB, 1)) KB)" -ForegroundColor Green
    } else {
        Write-Host "  [MISSING] $name" -ForegroundColor Red
    }
}

# Check for assets
$assets = $zip.Entries | Where-Object { $_.FullName -like "assets/*" }
Write-Host "  [OK] assets/ folder ($($assets.Count) files)" -ForegroundColor Green

$zip.Dispose()

Write-Host ""
Write-Host "=== Verification Complete ===" -ForegroundColor Cyan
