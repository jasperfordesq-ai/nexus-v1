$ErrorActionPreference = "Stop"

$vhostsPath = "C:\xampp\apache\conf\extra\httpd-vhosts.conf"
$hostsPath = "C:\Windows\System32\drivers\etc\hosts"
$backupPath = "$vhostsPath.bak"

Write-Host "Starting restoration..."

# 1. Backup httpd-vhosts.conf
if (Test-Path $vhostsPath) {
    Copy-Item $vhostsPath $backupPath -Force
    Write-Host "Backed up httpd-vhosts.conf to $backupPath"
} else {
    Write-Error "httpd-vhosts.conf not found at $vhostsPath"
    exit 1
}

# 2. Overwrite httpd-vhosts.conf with correct config
$vhostsContent = @"
# Local Staging Restoration - Auto-generated
<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs"
    ServerName localhost
</VirtualHost>

<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs/staging/httpdocs"
    ServerName staging.timebank.local
    <Directory "C:/xampp/htdocs/staging/httpdocs">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
"@

Set-Content -Path $vhostsPath -Value $vhostsContent -Encoding Ascii
Write-Host "Updated httpd-vhosts.conf"

# 3. Update hosts file if needed
try {
    $hostsContent = Get-Content $hostsPath -ErrorAction Stop
    $needsUpdate = $true
    foreach ($line in $hostsContent) {
        if ($line -match "staging.timebank.local") {
            $needsUpdate = $false
            break
        }
    }

    if ($needsUpdate) {
        Add-Content -Path $hostsPath -Value "`r`n127.0.0.1       staging.timebank.local"
        Write-Host "Added entry to hosts file."
    } else {
        Write-Host "Hosts file already configured."
    }
} catch {
    Write-Warning "Could not update hosts file. You may need to run as Administrator or edit manually."
    Write-Warning $_.Exception.Message
}

Write-Host "Done. Please RESTART APACHE in XAMPP Control Panel."
