<#
.SYNOPSIS
    Zips a folder using the native 'tar' command, which is generally faster and more reliable than Windows default zip.
.DESCRIPTION
    Creates a zip archive of the specified folder. 
    Can optionally exclude specific folder names (like node_modules, .git) which often cause performance issues on Windows.
.EXAMPLE
    .\scripts\zip-folder.ps1 -SourcePath ".\my-project"
.EXAMPLE
    .\scripts\zip-folder.ps1 -SourcePath ".\my-project" -Excludes "node_modules", ".git"
#>
param(
    [Parameter(Mandatory=$true)]
    [string]$SourcePath,
    
    [Parameter(Mandatory=$false)]
    [string]$DestinationPath,

    [Parameter(Mandatory=$false)]
    [string[]]$Excludes = @(".git", "node_modules", "vendor")
)

$ErrorActionPreference = "Stop"

# Resolve paths
if (-not (Test-Path $SourcePath)) {
    Write-Error "Source path '$SourcePath' does not exist."
    exit 1
}

$SourceItem = Get-Item $SourcePath
$SourceAbsPath = $SourceItem.FullName

if (-not $DestinationPath) {
    $DestinationPath = "$($SourceItem.FullName).zip"
}

$DestAbsPath = $DestinationPath
# If destination is relative, resolve it relative to current location, but Ensure parent exists
if (-not (Split-Path $DestAbsPath -IsAbsolute)) {
    $DestAbsPath = Join-Path (Get-Location) $DestinationPath
}

$ParentDir = Split-Path $SourceAbsPath -Parent
$DirName = Split-Path $SourceAbsPath -Leaf

Write-Host "Zipping '$DirName'..."
Write-Host "  Source: $SourceAbsPath"
Write-Host "  Dest:   $DestAbsPath"
if ($Excludes) {
    Write-Host "  Excluding: $($Excludes -join ', ')"
}

# Build exclusions args
$ExcludeArgs = @()
foreach ($ex in $Excludes) {
    $ExcludeArgs += "--exclude"
    $ExcludeArgs += "$ex"
}

# Construct command args
# We change directory (-C) to the parent of the source, so the zip contains just the folder name at root
$TarArgs = @("-a", "-c", "-f", "$DestAbsPath") + $ExcludeArgs + @("-C", "$ParentDir", "$DirName")

Write-Host "Running tar..."
try {
    # Run tar
    # Note: We use Start-Process to properly wait and handle arguments
    $p = Start-Process -FilePath "tar" -ArgumentList $TarArgs -Wait -NoNewWindow -PassThru
    
    if ($p.ExitCode -eq 0) {
        Write-Host "✅ Successfully created zip archive." -ForegroundColor Green
    } else {
        Write-Error "❌ tar failed with exit code $($p.ExitCode)"
    }
} catch {
    Write-Error "An error occurred while running tar: $_"
}
