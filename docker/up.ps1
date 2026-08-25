#Requires -Version 5.1
<#
.SYNOPSIS
  Start the CheckoutPlatform Docker stack (Nginx, PHP-FPM, MySQL, Redis, Supervisor workers + scheduler).

.USAGE
  .\docker\up.ps1
  .\docker\up.ps1 -Build
  .\docker\up.ps1 -Seed
#>
param(
    [switch]$Build,
    [switch]$Seed
)

$ErrorActionPreference = "Stop"

$env:Path = [System.Environment]::GetEnvironmentVariable("Path", "Machine") + ";" +
            [System.Environment]::GetEnvironmentVariable("Path", "User")

$Root = Split-Path -Parent $PSScriptRoot
Set-Location $Root

if (-not (Test-Path ".env.docker")) {
    Copy-Item ".env.docker.example" ".env.docker"
    Write-Host "Created .env.docker from example."
    Write-Host "Generate APP_KEY: docker compose exec app php artisan key:generate --show"
}

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw "Docker CLI not found. Install Docker Desktop and restart your terminal."
}

function Wait-DockerDaemon {
    param([int]$TimeoutSeconds = 180)
    $elapsed = 0
    while ($elapsed -lt $TimeoutSeconds) {
        docker info 2>$null | Out-Null
        if ($LASTEXITCODE -eq 0) { return $true }
        if ($elapsed -eq 0) {
            $desktop = Join-Path $env:LOCALAPPDATA "Programs\DockerDesktop\Docker Desktop.exe"
            if (Test-Path $desktop) {
                Write-Host "Starting Docker Desktop..."
                Start-Process $desktop | Out-Null
            }
        }
        Start-Sleep -Seconds 5
        $elapsed += 5
        Write-Host "Waiting for Docker daemon... (${elapsed}s)"
    }
    return $false
}

if (-not (Wait-DockerDaemon)) {
    throw "Docker daemon is not running. Open Docker Desktop manually, then retry."
}

$composeArgs = @("compose", "--env-file", ".env.docker", "up", "-d")
if ($Build) { $composeArgs += "--build" }

Write-Host "Starting containers..."
& docker @composeArgs
if ($LASTEXITCODE -ne 0) { throw "docker compose up failed." }

if ($Seed) {
    Write-Host "Running database seed..."
    & docker compose exec app php artisan db:seed --force
}

Write-Host ""
Write-Host "Stack is up."
Write-Host "  App:    http://localhost:8080"
Write-Host "  Admin:  http://localhost:8080/admin"
Write-Host "  API:    http://localhost:8080/api/v1"
Write-Host ""
Write-Host "Useful commands:"
Write-Host "  docker compose ps"
Write-Host "  docker compose logs -f worker scheduler"
Write-Host "  docker compose exec worker supervisorctl status"
Write-Host "  docker compose exec app php artisan test"
