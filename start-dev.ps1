# ============================================================
#  Intan-Elyu Tourism Management System — Dev Server Launcher
#  Run from the project root:  .\start-dev.ps1
#
#  Starts:
#    Backend  : http://127.0.0.1:8000  (Laravel)
#    Frontend : http://localhost:8080  (PHP built-in server)
# ============================================================

$root = Split-Path -Parent $MyInvocation.MyCommand.Definition

Write-Host ""
Write-Host " [1/2] Starting Laravel backend on http://127.0.0.1:8000 ..." -ForegroundColor Cyan
Start-Process powershell -ArgumentList "-NoExit", "-Command", "Set-Location '$root\backend'; php artisan serve --host=127.0.0.1 --port=8000" -WindowStyle Normal

Start-Sleep -Seconds 2

Write-Host " [2/2] Starting PHP Frontend on http://localhost:8080 ..." -ForegroundColor Cyan
Start-Process powershell -ArgumentList "-NoExit", "-Command", "Set-Location '$root\Frontend\Website\Frontend'; php -S localhost:8080" -WindowStyle Normal

Write-Host ""
Write-Host "  Both servers are starting in separate windows." -ForegroundColor Green
Write-Host ""
Write-Host "  Backend  →  http://127.0.0.1:8000"
Write-Host "  Frontend →  http://localhost:8080"
Write-Host "  Login    →  http://localhost:8080/login.php"
Write-Host ""
