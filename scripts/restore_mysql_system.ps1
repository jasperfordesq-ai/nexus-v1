$mysqlData = "C:\xampp\mysql\data"
$mysqlBackup = "C:\xampp\mysql\backup\mysql"
$mysqlSystem = "$mysqlData\mysql"
$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$corruptedDir = "$mysqlData\mysql_corrupted_$timestamp"

Write-Host "=== Nexus MySQL System Restoration ===" -ForegroundColor Cyan

# 1. Stop Services
Stop-Process -Name mysqld -Force -ErrorAction SilentlyContinue

# 2. Backup/Move Corrupted 'mysql' DB
if (Test-Path $mysqlSystem) {
    Write-Host "Moving corrupted 'mysql' folder to $corruptedDir..."
    Move-Item $mysqlSystem $corruptedDir -Force
}

# 3. Restore 'mysql' from Backup
if (Test-Path $mysqlBackup) {
    Write-Host "Restoring 'mysql' from XAMPP backup..."
    Copy-Item $mysqlBackup $mysqlData -Recurse -Force
}
else {
    Write-Error "CRITICAL: Backup folder $mysqlBackup not found!"
    exit 1
}

# 4. Clean Logs Again (Required for log sequence match)
Write-Host "Cleaning log files to force re-initialization..."
Remove-Item "$mysqlData\aria_log.*" -Force -ErrorAction SilentlyContinue
Remove-Item "$mysqlData\ib_logfile*" -Force -ErrorAction SilentlyContinue

# 5. Start MySQL
Write-Host "Attempting to start MySQL..."
$mysqlBin = "C:\xampp\mysql\bin\mysqld.exe"
Start-Process -FilePath $mysqlBin -WindowStyle Hidden

Start-Sleep -Seconds 10
if (Get-Process mysqld -ErrorAction SilentlyContinue) {
    Write-Host "SUCCESS: MySQL is running!" -ForegroundColor Green
    
    # 6. Apply Permissions (Pattern 25)
    Write-Host "Applying permissions..."
    $mysqlCmd = "C:\xampp\mysql\bin\mysql.exe"
    & $mysqlCmd -u root -e "GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION; GRANT ALL PRIVILEGES ON *.* TO 'pma'@'localhost'; FLUSH PRIVILEGES;"
    Write-Host "Permissions restored."
}
else {
    Write-Error "FAILURE: MySQL process started but died immediately."
}
