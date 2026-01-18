$mysqlData = "C:\xampp\mysql\data"
$backupPath = "C:\xampp\mysql\data_backup_$(Get-Date -Format 'yyyyMMdd_HHmmss')"

Write-Host "=== Nexus MySQL Recovery Tool ===" -ForegroundColor Cyan

# 1. Stop Services
Write-Host "Step 1: Stopping Services..." -ForegroundColor Yellow
Stop-Process -Name mysqld -Force -ErrorAction SilentlyContinue
Stop-Process -Name httpd -Force -ErrorAction SilentlyContinue
Start-Sleep -Seconds 2

# 2. Backup Data
Write-Host "Step 2: Backing up MySQL Data to $backupPath..." -ForegroundColor Yellow
if (Test-Path $mysqlData) {
    Copy-Item -Path $mysqlData -Destination $backupPath -Recurse -Force
    Write-Host "Backup Complete." -ForegroundColor Green
} else {
    Write-Error "MySQL Data directory not found at $mysqlData!"
    exit 1
}

# 3. Clean Corrupted Logs
Write-Host "Step 3: Cleaning Corrupted Logs..." -ForegroundColor Yellow

# Delete aria_log files
$ariaLogs = Get-ChildItem -Path $mysqlData -Filter "aria_log.*"
foreach ($file in $ariaLogs) {
    Write-Host "Removing $($file.Name)..."
    Remove-Item $file.FullName -Force
}

# Delete InnoDB logs
$innodbLogs = Get-ChildItem -Path $mysqlData -Filter "ib_logfile*"
foreach ($file in $innodbLogs) {
     Write-Host "Removing $($file.Name)..."
     Remove-Item $file.FullName -Force
}

Write-Host "Log cleanup complete." -ForegroundColor Green

# 4. Restart MySQL
Write-Host "Step 4: Attempting to start MySQL..." -ForegroundColor Yellow
try {
    # Attempt to start via XAMPP's mysql_start.bat or manually execute mysqld provided it runs in bg
    # Better: Start the process and wait a bit
    $mysqlBin = "C:\xampp\mysql\bin\mysqld.exe"
    Start-Process -FilePath $mysqlBin -WindowStyle Hidden
    
    Write-Host "MySQL process launched. Waiting for initialization..."
    Start-Sleep -Seconds 5
    
    if (Get-Process mysqld -ErrorAction SilentlyContinue) {
        Write-Host "SUCCESS: MySQL is running!" -ForegroundColor Green
    } else {
        Write-Error "FAILURE: MySQL process started but died immediately. Check error logs."
    }
} catch {
    Write-Error "Failed to start MySQL: $_"
}

Write-Host "=== Operation Complete ===" -ForegroundColor Cyan
