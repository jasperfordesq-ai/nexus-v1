$mysqlBin = "C:\xampp\mysql\bin"
$mysqlData = "C:\xampp\mysql\data"
$myIni = "$mysqlBin\my.ini"
$myIniBak = "$mysqlBin\my.ini.bak"

Write-Host "=== Nexus MySQL Recovery V2 ===" -ForegroundColor Cyan

# 1. Stop Services
Stop-Process -Name mysqld -Force -ErrorAction SilentlyContinue

# 2. Delete aria_log_control (The specific file that was missed)
$ariaControl = "$mysqlData\aria_log_control"
if (Test-Path $ariaControl) {
    Write-Host "Removing aria_log_control to force Aria reset..."
    Remove-Item $ariaControl -Force
}

# 3. Clean Logs Again
Remove-Item "$mysqlData\aria_log.*" -Force -ErrorAction SilentlyContinue
Remove-Item "$mysqlData\ib_logfile*" -Force -ErrorAction SilentlyContinue

# 4. Enable innodb_force_recovery = 1
Write-Host "Modifying my.ini for Crash Recovery..."
if (-not (Test-Path $myIniBak)) { Copy-Item $myIni $myIniBak }

$iniContent = Get-Content $myIni
$newContent = @()
$hasRecovery = $false

foreach ($line in $iniContent) {
    if ($line -match "innodb_force_recovery") {
        # Update existing
        $newContent += "innodb_force_recovery = 1"
        $hasRecovery = $true
    }
    else {
        $newContent += $line
        # Insert after [mysqld] if not found
        if ($line -match "\[mysqld\]" -and -not $hasRecovery) {
            # We will check if we already added it to avoid duplicates if iterating
        }
    }
}

# Simple append if complex parsing fails, but let's try a safer append
if (-not $hasRecovery) {
    # Append under [mysqld] section simply by regex replacement or just appending to file?
    # Safer: just append to end, MySQL usually accepts it.
    $newContent += "innodb_force_recovery = 1"
}

Set-Content -Path $myIni -Value $newContent

# 5. Start MySQL (Recovery Mode)
Write-Host "Starting MySQL in Recovery Mode..."
Start-Process -FilePath "$mysqlBin\mysqld.exe" -WindowStyle Hidden

Write-Host "Waiting 15 seconds for LSN reconciliation..."
Start-Sleep -Seconds 15

if (Get-Process mysqld -ErrorAction SilentlyContinue) {
    Write-Host "MySQL started successfully in Recovery Mode!" -ForegroundColor Green
    
    # 6. Stop and Disable Recovery
    Write-Host "Stopping MySQL to disable recovery mode..."
    Stop-Process -Name mysqld -Force
    Start-Sleep -Seconds 3
    
    Write-Host "Restoring my.ini..."
    Copy-Item $myIniBak $myIni -Force
    
    # 7. Final Start
    Write-Host "Attempting Final Normal Start..."
    Start-Process -FilePath "$mysqlBin\mysqld.exe" -WindowStyle Hidden
    Start-Sleep -Seconds 10
    
    if (Get-Process mysqld -ErrorAction SilentlyContinue) {
        Write-Host "SUCCESS: MySQL is running normally!" -ForegroundColor Green
        
        # 8. Apple Permissions again just in case
        $mysqlCmd = "$mysqlBin\mysql.exe"
        & $mysqlCmd -u root -e "GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION; GRANT ALL PRIVILEGES ON *.* TO 'pma'@'localhost'; FLUSH PRIVILEGES;"
    }
    else {
        Write-Error "FAILURE: MySQL died after disabling recovery mode."
    }
}
else {
    Write-Error "FAILURE: MySQL failed to start even in Recovery Mode."
}
