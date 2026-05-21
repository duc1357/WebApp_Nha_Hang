$ErrorActionPreference = "Stop"
. "$PSScriptRoot\_helpers.ps1"

$baseUrl = Get-RestaurantBaseUrl
Assert-RestaurantHostAvailable $baseUrl

function Assert-SuccessJson {
    param(
        [string] $Name,
        [string] $Url
    )

    $response = Invoke-RestMethod -Uri $Url -TimeoutSec 10
    if ($null -eq $response) {
        throw "$Name returned empty response"
    }

    Write-Output "PASS $Name"
}

Assert-SuccessJson "csrf" "$baseUrl/api/auth/get_csrf.php"
Assert-SuccessJson "menu" "$baseUrl/api/menu/get_menu.php"
Assert-SuccessJson "tables" "$baseUrl/api/tables/read.php"
Assert-SuccessJson "featured_reviews" "$baseUrl/api/public/get_featured_reviews.php"
Assert-SuccessJson "booked_tables" "$baseUrl/api/public/get_booked_tables.php?date=2026-05-20&time=18:00"

$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$csrf = (Invoke-RestMethod -Uri "$baseUrl/api/auth/get_csrf.php" -WebSession $session -TimeoutSec 10).csrf_token

if ([string]::IsNullOrWhiteSpace($csrf)) {
    throw "CSRF token was empty"
}

$body = @{
    email = "admin.demo@example.test"
    password = "ChangeMeDemo123!"
} | ConvertTo-Json

$login = Invoke-RestMethod `
    -Uri "$baseUrl/api/admin/login.php" `
    -Method Post `
    -Body $body `
    -ContentType "application/json; charset=utf-8" `
    -Headers @{ "X-CSRF-Token" = $csrf } `
    -WebSession $session `
    -TimeoutSec 10

if ($login.success -ne $true) {
    throw "admin login failed"
}

$stats = Invoke-RestMethod -Uri "$baseUrl/api/admin/get_stats.php" -WebSession $session -TimeoutSec 10
if ($stats.success -ne $true) {
    throw "admin stats failed"
}

Write-Output "PASS admin_login_and_stats"
