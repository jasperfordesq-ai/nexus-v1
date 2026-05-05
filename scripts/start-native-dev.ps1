# Copyright (c) 2024-2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
# Author: Jasper Ford
# See NOTICE file for attribution and acknowledgements.

param(
    [switch]$NoVite
)

$ErrorActionPreference = 'Stop'

$RepoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$ApacheBase = 'C:\laragon\bin\apache'
$ApacheDir = Get-ChildItem -Path $ApacheBase -Directory |
    Sort-Object -Property Name -Descending |
    Select-Object -First 1

if (-not $ApacheDir) {
    throw "Laragon Apache was not found under $ApacheBase"
}

$Httpd = Join-Path $ApacheDir.FullName 'bin\httpd.exe'
$HttpdConf = Join-Path $ApacheDir.FullName 'conf\httpd.conf'
$ViteLog = Join-Path $RepoRoot 'storage\logs\vite-native.log'

function Wait-DockerEngine {
    $dockerDesktop = 'C:\Program Files\Docker\Docker\Docker Desktop.exe'

    for ($attempt = 1; $attempt -le 60; $attempt++) {
        docker info *> $null
        if ($LASTEXITCODE -eq 0) {
            return
        }

        if ($attempt -eq 1 -and (Test-Path $dockerDesktop)) {
            Write-Host 'Starting Docker Desktop'
            Start-Process -FilePath $dockerDesktop -WindowStyle Hidden
        }

        Start-Sleep -Seconds 2
    }

    throw 'Docker Desktop did not become ready within 120 seconds.'
}

function Test-ListeningPort {
    param(
        [Parameter(Mandatory = $true)]
        [int]$Port
    )

    $connection = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue |
        Select-Object -First 1

    return $null -ne $connection
}

Set-Location $RepoRoot

Wait-DockerEngine

Write-Host 'Starting Docker data services: db, redis, meilisearch'
docker compose up -d db redis meilisearch

Write-Host 'Checking Apache configuration'
& $Httpd -t -f $HttpdConf

if (Test-ListeningPort -Port 8088) {
    Write-Host 'Apache is already listening on 8088'
} else {
    Write-Host 'Starting Laragon Apache'
    Start-Process -FilePath $Httpd -ArgumentList '-f', $HttpdConf -WindowStyle Hidden
    Start-Sleep -Seconds 2
}

if (-not $NoVite) {
    if (Test-ListeningPort -Port 5173) {
        Write-Host 'Vite is already listening on 5173'
    } else {
        New-Item -ItemType Directory -Path (Split-Path $ViteLog -Parent) -Force | Out-Null
        $viteCommand = "Set-Location '$RepoRoot'; npm run dev:frontend *>> '$ViteLog'"
        Write-Host 'Starting native Windows Vite'
        Start-Process -FilePath 'powershell.exe' `
            -ArgumentList '-NoProfile', '-ExecutionPolicy', 'Bypass', '-Command', $viteCommand `
            -WindowStyle Hidden
        Start-Sleep -Seconds 4
    }
}

Write-Host ''
Write-Host 'Native dev stack is ready:'
Write-Host '  Frontend: http://127.0.0.1:5173/hour-timebank'
Write-Host '  NEXUS API: http://127.0.0.1:8088'
Write-Host '  Shared web root: http://127.0.0.1/'
