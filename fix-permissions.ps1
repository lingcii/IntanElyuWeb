# ============================================================
#  Fix Storage Directory Permissions
#  Run this if you get "Unable to write" errors when uploading images.
#  OneDrive can reset ACLs — just re-run this script to fix it.
# ============================================================

$storageDir = "$PSScriptRoot\backend\storage\app\public\tourist_spots"

Write-Host ""
Write-Host " Fixing write permissions on tourist_spots directory..." -ForegroundColor Cyan

# Remove inherited DENY rules from the entire storage tree
icacls "$PSScriptRoot\backend\storage" /remove:d "Everyone" /T /C | Out-Null

# Break inheritance and set explicit full-access on tourist_spots
icacls $storageDir /inheritance:r /grant:r `
    "Everyone:(OI)(CI)(F)" `
    "BUILTIN\Administrators:(OI)(CI)(F)" `
    "NT AUTHORITY\SYSTEM:(OI)(CI)(F)"

Write-Host " Done! Permissions restored on: $storageDir" -ForegroundColor Green
Write-Host ""
