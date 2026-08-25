#Requires -Version 5.1
$ErrorActionPreference = "Stop"
$env:Path = [System.Environment]::GetEnvironmentVariable("Path", "Machine") + ";" +
            [System.Environment]::GetEnvironmentVariable("Path", "User")
Set-Location (Split-Path -Parent $PSScriptRoot)
docker compose --env-file .env.docker down @args
