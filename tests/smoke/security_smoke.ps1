$ErrorActionPreference = "Stop"
. "$PSScriptRoot\_helpers.ps1"

$baseUrl = Get-RestaurantBaseUrl
Assert-RestaurantHostAvailable $baseUrl

Assert-Status "admin_stats_requires_login" {
    Invoke-RestMethod -Uri "$baseUrl/api/admin/get_stats.php" -TimeoutSec 10
} @(401)

$noCsrfSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
Assert-Status "post_without_csrf_rejected" {
    Invoke-RestMethod `
        -Uri "$baseUrl/api/auth/login.php" `
        -Method Post `
        -Body (@{ identifier = "customer.demo@example.test"; password = "ChangeMeDemo123!" } | ConvertTo-Json) `
        -ContentType "application/json; charset=utf-8" `
        -WebSession $noCsrfSession `
        -TimeoutSec 10
} @(403)

$wrongPasswordSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$csrf = (Invoke-RestMethod -Uri "$baseUrl/api/auth/get_csrf.php" -WebSession $wrongPasswordSession -TimeoutSec 10).csrf_token
Assert-Status "wrong_password_rejected" {
    Invoke-RestMethod `
        -Uri "$baseUrl/api/auth/login.php" `
        -Method Post `
        -Body (@{ identifier = "customer.demo@example.test"; password = "WrongPassword123!" } | ConvertTo-Json) `
        -ContentType "application/json; charset=utf-8" `
        -Headers @{ "X-CSRF-Token" = $csrf } `
        -WebSession $wrongPasswordSession `
        -TimeoutSec 10
} @(401)

Assert-Status "webhook_requires_authorization" {
    Invoke-RestMethod `
        -Uri "$baseUrl/api/payment/webhook.php" `
        -Method Post `
        -Body (@{ gateway = "MBBank"; transferAmount = 1000; transferType = "in"; content = "DH1" } | ConvertTo-Json) `
        -ContentType "application/json; charset=utf-8" `
        -TimeoutSec 10
} @(401, 503)
