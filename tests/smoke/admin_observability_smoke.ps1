$ErrorActionPreference = "Stop"
. "$PSScriptRoot\_helpers.ps1"

$baseUrl = Get-RestaurantBaseUrl
Assert-RestaurantHostAvailable $baseUrl

$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$csrf = (Invoke-RestMethod -Uri "$baseUrl/api/auth/get_csrf.php" -WebSession $session -TimeoutSec 10).csrf_token
Invoke-RestMethod `
    -Uri "$baseUrl/api/admin/login.php" `
    -Method Post `
    -Body (@{ email = "admin.demo@example.test"; password = "ChangeMeDemo123!" } | ConvertTo-Json) `
    -ContentType "application/json; charset=utf-8" `
    -Headers @{ "X-CSRF-Token" = $csrf } `
    -WebSession $session `
    -TimeoutSec 10 | Out-Null

$health = Invoke-RestMethod -Uri "$baseUrl/api/admin/get_system_health.php" -WebSession $session -TimeoutSec 10
if ($health.success -ne $true -or $null -eq $health.checks) {
    throw "admin health check failed"
}
Write-Output "PASS admin_health"

$logs = Invoke-RestMethod -Uri "$baseUrl/api/admin/get_logs.php?channel=auth&limit=5" -WebSession $session -TimeoutSec 10
if ($logs.success -ne $true -or $null -eq $logs.logs) {
    throw "admin logs failed"
}
Write-Output "PASS admin_logs"

Assert-Status "admin_logs_requires_login" {
    Invoke-RestMethod -Uri "$baseUrl/api/admin/get_logs.php?channel=auth&limit=5" -TimeoutSec 10
} @(401)

Assert-Status "direct_log_access_blocked" {
    Invoke-RestMethod -Uri "$baseUrl/logs/auth.log" -TimeoutSec 10
} @(403, 404)
