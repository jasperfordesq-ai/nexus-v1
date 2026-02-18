# ===========================================
# Quick Deploy - Upload specific folders/files
# ===========================================
# Usage:
#   .\scripts\quick-deploy.ps1 src          # Deploy src folder
#   .\scripts\quick-deploy.ps1 httpdocs     # Deploy httpdocs folder
#   .\scripts\quick-deploy.ps1 views        # Deploy views folder
#   .\scripts\quick-deploy.ps1 src,views    # Deploy multiple
#   .\scripts\quick-deploy.ps1 src/Controllers  # Deploy subfolder
# ===========================================

param(
    [Parameter(Mandatory=$true, Position=0)]
    [string]$Target
)

$SSH_USER = "jasper"
$SSH_HOST = "20.224.171.253"
$SSH_PORT = 22
$REMOTE_PATH = "/opt/nexus-php"
$ProjectRoot = "C:\xampp\htdocs\staging"

# Handle multiple targets (comma-separated)
$targets = $Target -split ","

foreach ($t in $targets) {
    $t = $t.Trim()
    $localPath = Join-Path $ProjectRoot $t

    if (-not (Test-Path $localPath)) {
        Write-Host "Not found: $t" -ForegroundColor Red
        continue
    }

    # Determine remote path
    $remoteDest = $REMOTE_PATH
    if ($t -like "*/*") {
        # Subfolder - preserve parent structure
        $parentFolder = Split-Path $t -Parent
        $remoteDest = "$REMOTE_PATH/$parentFolder"
    }

    Write-Host "Uploading $t..." -ForegroundColor Cyan

    if (Test-Path $localPath -PathType Container) {
        # It's a folder
        scp -r -P $SSH_PORT "$localPath" "${SSH_USER}@${SSH_HOST}:${remoteDest}/"
    } else {
        # It's a file
        scp -P $SSH_PORT "$localPath" "${SSH_USER}@${SSH_HOST}:${remoteDest}/"
    }

    if ($LASTEXITCODE -eq 0) {
        Write-Host "Done: $t" -ForegroundColor Green
    } else {
        Write-Host "Failed: $t" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "Deployment complete!" -ForegroundColor Green
