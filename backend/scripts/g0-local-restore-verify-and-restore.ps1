# G0 local isolated MySQL restore — Windows. No production access.
#Requires -Version 5.1
$ErrorActionPreference = "Stop"
$RepoRoot = Resolve-Path (Join-Path $PSScriptRoot "..\..")
Set-Location $RepoRoot

$Utc = { (Get-Date).ToUniversalTime().ToString("yyyy-MM-ddTHH:mm:ssZ") }

$EnvFile = Join-Path $RepoRoot ".env.g0-local-restore"
if (-not (Test-Path $EnvFile)) {
    Copy-Item (Join-Path $RepoRoot ".env.g0-local-restore.example") $EnvFile
    Write-Host "Created $EnvFile from example. Adjust credentials if needed."
}

Get-Content $EnvFile | ForEach-Object {
    if ($_ -match '^\s*([A-Za-z_][A-Za-z0-9_]*)=(.*)$') {
        Set-Variable -Name $Matches[1] -Value $Matches[2] -Scope Script
    }
}

$BackupGz = if ($G0_BACKUP_GZ) { Join-Path $RepoRoot $G0_BACKUP_GZ } else {
    Join-Path $RepoRoot ".local/g0-restore/shipstation-display-status-20261120/db-pre-deploy.sql.gz"
}
$ExpectedSha = $G0_BACKUP_SHA256
if (-not $ExpectedSha) {
    $ExpectedSha = "ee60da74242400e2cc4b530d355cdee922bb58f614beeba6e2a6811c2cb55cff"
}

$RestoreDbName = if ($G0_RESTORE_DATABASE) { $G0_RESTORE_DATABASE } else { "checkout_platform" }

$LogPath = Join-Path $RepoRoot ".local/g0-restore/restore-run.log"
$ReportJson = Join-Path $RepoRoot ".local/g0-restore/restore-result.json"

$ForbiddenHosts = @(
    "2.25.90.141", "virello.cloud", "checkout_mysql"
)
foreach ($key in @("DB_HOST", "MYSQL_HOST", "G0_MYSQL_HOST")) {
    $val = Get-Variable -Name $key -ErrorAction SilentlyContinue
    if ($val -and $ForbiddenHosts -contains $val.Value) {
        throw "Forbidden production host reference in env ($key=$($val.Value))"
    }
}

function Write-Log($msg) {
    $line = "[$( & $Utc )] $msg"
    Add-Content -Path $LogPath -Value $line
    Write-Host $line
}

function Invoke-DockerComposeUp {
    param([string]$ComposeFile, [string]$EnvFilePath)
    $prev = $ErrorActionPreference
    $ErrorActionPreference = "Continue"
    try {
        $out = docker compose -p virello-g0-local-restore --env-file $EnvFilePath -f $ComposeFile up -d g0_mysql 2>&1
        $code = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $prev
    }
    if ($code -ne 0) {
        $text = ($out | Out-String)
        if ($text -notmatch "virello_g0_restore_mysql") {
            throw "docker compose up failed (exit $code): $text"
        }
        Write-Log "docker compose up returned $code but container reference present; verifying container..."
    }
    $out | ForEach-Object { Write-Log $_ }
}

function Resolve-DumpDatabaseName {
    param([string]$GzPath)
    $parent = Split-Path $GzPath -Parent
    $leaf = Split-Path $GzPath -Leaf
    $header = docker run --rm -v "${parent}:/backup:ro" alpine:3.20 sh -c "gunzip -c /backup/$leaf | head -n 8"
    $m = [regex]::Match(($header -join "`n"), "Database:\s*([A-Za-z0-9_]+)")
    if ($m.Success) {
        return $m.Groups[1].Value
    }
    return $RestoreDbName
}

function Invoke-G0Mysql {
    param([string[]]$MysqlArgs)
    $prev = $ErrorActionPreference
    $ErrorActionPreference = "Continue"
    try {
        & docker exec virello_g0_restore_mysql mysql @MysqlArgs 2>&1
        return $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $prev
    }
}

Write-Log "G0 local restore start (Windows)"

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw "Docker not found. Install Docker Desktop."
}

docker version | Out-Null
docker compose version | Out-Null

if (-not (Test-Path $BackupGz)) {
    Write-Log "STOP: backup not found at $BackupGz"
    Write-Log "Transfer instructions: .local/g0-restore/README.md"
    exit 2
}

$size = (Get-Item $BackupGz).Length
Write-Log "Backup size bytes=$size"
$hash = (Get-FileHash -Path $BackupGz -Algorithm SHA256).Hash.ToLower()
Write-Log "Backup sha256=$hash"
if ($hash -ne $ExpectedSha.ToLower()) {
    Write-Log "STOP: SHA-256 mismatch expected=$ExpectedSha"
    exit 3
}

Write-Log "gzip integrity check via docker..."
$parent = Split-Path $BackupGz -Parent
$leaf = Split-Path $BackupGz -Leaf
docker run --rm -v "${parent}:/backup:ro" alpine:3.20 sh -c "apk add --no-cache gzip >/dev/null && gzip -t /backup/$leaf"
if ($LASTEXITCODE -ne 0) { Write-Log "STOP: gzip -t failed"; exit 4 }
Write-Log "gzip integrity OK"

$targetDb = Resolve-DumpDatabaseName -GzPath $BackupGz
Write-Log "Target database from dump header: $targetDb"

$rootPw = if ($G0_MYSQL_ROOT_PASSWORD) { $G0_MYSQL_ROOT_PASSWORD } else { "g0_local_root_change_me" }

Write-Log "Starting isolated MySQL (project virello-g0-local-restore)..."
$composeFile = Join-Path $RepoRoot "docker-compose.g0-local-restore.yml"
Invoke-DockerComposeUp -ComposeFile $composeFile -EnvFilePath $EnvFile

$exists = docker inspect -f "{{.Name}}" virello_g0_restore_mysql 2>$null
if (-not $exists) {
    Write-Log "STOP: virello_g0_restore_mysql container not found"
    exit 5
}

Write-Log "Waiting for MySQL healthy..."
$deadline = (Get-Date).AddMinutes(5)
$status = "starting"
do {
    $status = docker inspect -f "{{.State.Health.Status}}" virello_g0_restore_mysql 2>$null
    if ($status -eq "healthy") { break }
    Start-Sleep -Seconds 3
} while ((Get-Date) -lt $deadline)
if ($status -ne "healthy") {
    Write-Log "STOP: MySQL not healthy status=$status"
    exit 5
}

$skipImport = $false
if ($env:G0_SKIP_IMPORT -eq "1" -or $env:G0_SKIP_IMPORT -eq "true") {
    $skipImport = $true
    Write-Log "G0_SKIP_IMPORT set — skipping mysql import"
} else {
    $existingTables = Invoke-G0Mysql -MysqlArgs @("-uroot", "-p$rootPw", "-N", "-e", "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$targetDb';")
    if ($LASTEXITCODE -eq 0 -and [int]$existingTables -ge 50) {
        Write-Log "Database $targetDb already has $existingTables tables — skip import (set G0_FORCE_IMPORT=1 to override)"
        if ($env:G0_FORCE_IMPORT -ne "1") {
            $skipImport = $true
        }
    }
}

$restoreStart = & $Utc
$restoreExit = 0

if (-not $skipImport) {
    Write-Log "Ensuring database $targetDb exists..."
    $createDb = "CREATE DATABASE IF NOT EXISTS $targetDb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    Invoke-G0Mysql -MysqlArgs @("-uroot", "-p$rootPw", "-e", $createDb) | Out-Null
    if ($LASTEXITCODE -ne 0) {
        Write-Log "STOP: CREATE DATABASE failed exit=$LASTEXITCODE"
        exit 6
    }

    Write-Log "Restore start UTC=$restoreStart"
    $restoreCmd = "set -e; gunzip -c /backup/db-pre-deploy.sql.gz | mysql -uroot -p${rootPw} --max-allowed-packet=512M ${targetDb}"
    $prev = $ErrorActionPreference
    $ErrorActionPreference = "Continue"
  try {
        docker exec virello_g0_restore_mysql bash -c $restoreCmd 2>&1 | ForEach-Object {
            if ($_ -match '(?i)error') { Write-Log "restore_stream: $_" }
        }
        $restoreExit = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $prev
    }
    if ($restoreExit -ne 0) {
        Write-Log "STOP: mysql import exited $restoreExit"
        exit 6
    }
} else {
    Write-Log "Import skipped; using existing data in $targetDb"
}

$restoreEnd = & $Utc
Write-Log "Restore end UTC=$restoreEnd exit=$restoreExit"

$postHash = (Get-FileHash -Path $BackupGz -Algorithm SHA256).Hash.ToLower()
Write-Log "Backup sha256 post-run=$postHash"

Write-Log "Schema verification (schema=$targetDb)..."
$verifySql = @"
SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$targetDb';
SELECT table_name FROM information_schema.tables WHERE table_schema='$targetDb' AND table_name IN ('orders','subscriptions','shipstation_integrations','failed_jobs','migrations','customers','billing_ledger_entries') ORDER BY table_name;
"@
$verifyOut = Invoke-G0Mysql -MysqlArgs @("-uroot", "-p$rootPw", "-N", "-e", $verifySql)
$countLines = @($verifyOut | Where-Object { $_ -is [string] -and $_ -match '^\d+$' })
$tableCount = if ($countLines.Count -gt 0) { [int]$countLines[0] } else { 0 }
$verifyOut | ForEach-Object { Write-Log "verify: $_" }

$expectedMinTables = 50
if ([int]$tableCount -lt $expectedMinTables) {
    Write-Log "STOP: table count $tableCount below minimum $expectedMinTables"
    exit 7
}

$rowSql = "SELECT 'orders',COUNT(*) FROM $targetDb.orders UNION ALL SELECT 'failed_jobs',COUNT(*) FROM $targetDb.failed_jobs UNION ALL SELECT 'migrations',COUNT(*) FROM $targetDb.migrations;"
$rowOut = Invoke-G0Mysql -MysqlArgs @("-uroot", "-p$rootPw", "-N", "-e", $rowSql)
$rowOut | ForEach-Object { Write-Log "row_count: $_" }

$result = [ordered]@{
    recorded_utc          = (& $Utc)
    restore_result        = "SUCCESS"
    restore_start_utc     = $restoreStart
    restore_end_utc       = $restoreEnd
    restore_duration_seconds = if ($skipImport) { 0 } else { $null }
    restore_exit          = $restoreExit
    import_skipped        = $skipImport
    backup_sha256         = $hash
    backup_sha256_post    = $postHash
    backup_size_bytes     = $size
    gzip_ok               = $true
    target_database       = $targetDb
    table_count           = [int]$tableCount
    schema_verification   = "PASS"
    isolation             = @{
        compose_project = "virello-g0-local-restore"
        container       = "virello_g0_restore_mysql"
        port            = "127.0.0.1:33306"
        network         = "virello_g0_restore_net"
        volume          = "virello_g0_mysql_data"
    }
    production_touched    = $false
    laravel_migrations_run = $false
    queue_workers_run     = $false
    external_apis_called  = $false
    existing_volumes_deleted = $false
    checkout_mysql_touched   = $false
} | ConvertTo-Json -Depth 5
Set-Content -Path $ReportJson -Value $result
Write-Log "Wrote $ReportJson"
Write-Log "G0 local restore complete — evidence kept (no auto cleanup)"
exit 0