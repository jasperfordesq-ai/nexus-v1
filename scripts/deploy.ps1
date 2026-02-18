# ===========================================
# NEXUS TimeBank - PowerShell Deployment Script
# ===========================================
# Usage: .\scripts\deploy.ps1
# Or selective: .\scripts\deploy.ps1 -Only httpdocs,src,views
# ===========================================

param(
    [string]$SSH_USER = "jasper",
    [string]$SSH_HOST = "20.224.171.253",
    [int]$SSH_PORT = 22,
    [string]$REMOTE_PATH = "/opt/nexus-php",
    [string[]]$Only = @()  # Deploy only specific folders
)

$ErrorActionPreference = "Stop"
$ProjectRoot = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)

# Folders to deploy (in order)
$DeployFolders = @(
    "httpdocs",
    "src",
    "views",
    "config",
    "migrations",
    "scripts"
)

# Single files to deploy
$DeployFiles = @(
    "bootstrap.php",
    "composer.json",
    "composer.lock"
)

# Files/folders to exclude within deployed folders
$Excludes = @(
    ".git",
    "node_modules",
    ".env",
    "*.log"
)

Write-Host "==========================================" -ForegroundColor Green
Write-Host "  NEXUS TimeBank Deployment" -ForegroundColor Green
Write-Host "==========================================" -ForegroundColor Green
Write-Host ""
Write-Host "Server:  $SSH_USER@$SSH_HOST" -ForegroundColor Yellow
Write-Host "Path:    $REMOTE_PATH" -ForegroundColor Yellow
Write-Host "Source:  $ProjectRoot" -ForegroundColor Yellow
Write-Host ""

# Test SSH connection
Write-Host "Testing SSH connection..." -ForegroundColor Yellow
$sshTest = ssh -p $SSH_PORT -o ConnectTimeout=10 "$SSH_USER@$SSH_HOST" "echo 'OK'" 2>&1
if ($sshTest -ne "OK") {
    Write-Host "SSH connection failed!" -ForegroundColor Red
    exit 1
}
Write-Host "SSH connection OK!" -ForegroundColor Green
Write-Host ""

# Filter folders if -Only specified
if ($Only.Count -gt 0) {
    $DeployFolders = $DeployFolders | Where-Object { $Only -contains $_ }
    Write-Host "Deploying only: $($DeployFolders -join ', ')" -ForegroundColor Cyan
}

# Confirm
$confirm = Read-Host "Deploy now? (y/n)"
if ($confirm -ne "y" -and $confirm -ne "Y") {
    Write-Host "Deployment cancelled."
    exit 0
}

Write-Host ""
Write-Host "Starting deployment..." -ForegroundColor Yellow
Write-Host ""

# Deploy each folder
foreach ($folder in $DeployFolders) {
    $localPath = Join-Path $ProjectRoot $folder
    if (Test-Path $localPath) {
        Write-Host "Uploading $folder..." -ForegroundColor Cyan
        scp -r -P $SSH_PORT "$localPath" "${SSH_USER}@${SSH_HOST}:${REMOTE_PATH}/"
        if ($LASTEXITCODE -eq 0) {
            Write-Host "  Done!" -ForegroundColor Green
        } else {
            Write-Host "  Failed!" -ForegroundColor Red
        }
    } else {
        Write-Host "Skipping $folder (not found)" -ForegroundColor DarkGray
    }
}

# Deploy single files
foreach ($file in $DeployFiles) {
    $localPath = Join-Path $ProjectRoot $file
    if (Test-Path $localPath) {
        Write-Host "Uploading $file..." -ForegroundColor Cyan
        scp -P $SSH_PORT "$localPath" "${SSH_USER}@${SSH_HOST}:${REMOTE_PATH}/"
        if ($LASTEXITCODE -eq 0) {
            Write-Host "  Done!" -ForegroundColor Green
        } else {
            Write-Host "  Failed!" -ForegroundColor Red
        }
    }
}

Write-Host ""
Write-Host "==========================================" -ForegroundColor Green
Write-Host "  Deployment Complete!" -ForegroundColor Green
Write-Host "==========================================" -ForegroundColor Green

# Ask about composer
$runComposer = Read-Host "Run 'composer install' on server? (y/n)"
if ($runComposer -eq "y" -or $runComposer -eq "Y") {
    Write-Host "Running composer install..." -ForegroundColor Yellow
    ssh -p $SSH_PORT "$SSH_USER@$SSH_HOST" "cd $REMOTE_PATH && composer install --no-dev --optimize-autoloader"
}

Write-Host ""
Write-Host "All done!" -ForegroundColor Green
