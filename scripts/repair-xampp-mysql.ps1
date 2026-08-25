# XAMPP MariaDB recovery helper (run in PowerShell AS ADMIN if needed)
# Stop MySQL in XAMPP Control Panel before running destructive steps.

param(
    [ValidateSet('Diagnose', 'ResetInnoDBLogs', 'QuarantineCheckoutPlatform', 'RecreateDatabase')]
    [string]$Action = 'Diagnose'
)

$DataDir = 'C:\xampp\mysql\data'
$MyIni   = 'C:\xampp\mysql\bin\my.ini'
$ErrorLog = Join-Path $DataDir 'mysql_error.log'

function Write-Step($msg) { Write-Host "`n==> $msg" -ForegroundColor Cyan }

Write-Step 'Diagnose'
if (Test-Path $ErrorLog) {
    Get-Content $ErrorLog -Tail 25
} else {
    Write-Warning "Log not found: $ErrorLog"
}

Write-Host "`nPort listeners on 3306 / 3307:"
3306, 3307 | ForEach-Object {
    $p = $_
    Get-NetTCPConnection -LocalPort $p -State Listen -ErrorAction SilentlyContinue |
        ForEach-Object { Get-Process -Id $_.OwningProcess -ErrorAction SilentlyContinue | Select-Object @{N='Port';E={$p}}, Id, ProcessName }
}

Write-Host "`nXAMPP my.ini port (should match backend .env DB_PORT):"
Select-String -Path $MyIni -Pattern '^\s*port\s*=' -ErrorAction SilentlyContinue

switch ($Action) {
    'Diagnose' {
        Write-Host @"

Next steps (choose one path):

A) Use Docker MySQL for this project (recommended, avoids XAMPP corruption):
   cd C:\CheckoutPlatform
   docker compose up -d mysql
   Set backend\.env: DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=checkout_platform DB_USERNAME=checkout DB_PASSWORD=secret
   cd backend && php artisan migrate

B) Fix XAMPP InnoDB redo logs only (keeps data, fixes many 'log sequence in the future' cases):
   1. Stop MySQL in XAMPP
   2. Run: .\scripts\repair-xampp-mysql.ps1 -Action ResetInnoDBLogs
   3. Start MySQL in XAMPP

C) checkout_platform tablespace is corrupt (orders.ibd). Quarantine DB folder and recreate:
   1. Stop MySQL in XAMPP
   2. Run: .\scripts\repair-xampp-mysql.ps1 -Action QuarantineCheckoutPlatform
   3. Start MySQL, then: -Action RecreateDatabase
   4. cd backend && php artisan migrate

"@ -ForegroundColor Yellow
    }

    'ResetInnoDBLogs' {
        Write-Step 'Backup and remove ib_logfile0/1 (MariaDB recreates on start)'
        $backup = "C:\xampp\mysql\backup_innodb_$(Get-Date -Format 'yyyyMMdd-HHmmss')"
        New-Item -ItemType Directory -Path $backup -Force | Out-Null
        foreach ($f in @('ib_logfile0', 'ib_logfile1', 'ibtmp1')) {
            $src = Join-Path $DataDir $f
            if (Test-Path $src) { Copy-Item $src $backup -Force; Remove-Item $src -Force }
        }
        Write-Host "Backup: $backup. Now start MySQL from XAMPP Control Panel."
    }

    'QuarantineCheckoutPlatform' {
        Write-Step 'Move checkout_platform data directory aside'
        $src = Join-Path $DataDir 'checkout_platform'
        if (-not (Test-Path $src)) { Write-Warning 'No checkout_platform folder.'; break }
        $dest = Join-Path $DataDir ("checkout_platform.quarantine." + (Get-Date -Format 'yyyyMMdd-HHmmss'))
        Move-Item $src $dest
        Remove-Item (Join-Path $DataDir 'ib_logfile0'), (Join-Path $DataDir 'ib_logfile1') -Force -ErrorAction SilentlyContinue
        Write-Host "Quarantined to: $dest. Start MySQL, then run -Action RecreateDatabase"
    }

    'RecreateDatabase' {
        Write-Step 'Create empty checkout_platform (root, no password — default XAMPP)'
        $mysql = 'C:\xampp\mysql\bin\mysql.exe'
        $port = (Select-String -Path $MyIni -Pattern '^\s*port\s*=\s*(\d+)' | Select-Object -First 1).Matches.Groups[1].Value
        if (-not $port) { $port = '3306' }
        & $mysql -uroot -P $port -e @"
CREATE DATABASE IF NOT EXISTS checkout_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'checkout'@'localhost' IDENTIFIED BY 'secret';
GRANT ALL PRIVILEGES ON checkout_platform.* TO 'checkout'@'localhost';
FLUSH PRIVILEGES;
"@
        Write-Host 'Done. Run: cd C:\CheckoutPlatform\backend && php artisan migrate'
    }
}
