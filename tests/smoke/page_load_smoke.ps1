$ErrorActionPreference = "Stop"
. "$PSScriptRoot\_helpers.ps1"

$baseUrl = Get-RestaurantBaseUrl
Assert-RestaurantHostAvailable $baseUrl

function Assert-PageOk {
    param(
        [string] $Name,
        [string] $Path
    )

    $response = Invoke-WebRequest -Uri "$baseUrl$Path" -TimeoutSec 10 -UseBasicParsing
    if ($response.StatusCode -ne 200) {
        throw "$Name returned $($response.StatusCode); expected 200"
    }
    Write-Output "PASS $Name"
}

Assert-PageOk "home_page" "/"
Assert-PageOk "booking_page" "/booking.html"
Assert-PageOk "profile_page" "/profile.html"
Assert-PageOk "login_page" "/login.html"
Assert-PageOk "forgot_password_page" "/forgot_password.html"
Assert-PageOk "admin_login_page" "/admin/"
Assert-PageOk "admin_bookings_page" "/admin/bookings.php"
Assert-PageOk "admin_logs_page" "/admin/logs.php"

Assert-Status "config_access_blocked" {
    Invoke-RestMethod -Uri "$baseUrl/config/constants.php" -TimeoutSec 10
} @(403, 404)

Assert-Status "database_access_blocked" {
    Invoke-RestMethod -Uri "$baseUrl/Database/duong_bau_restaurant.sql" -TimeoutSec 10
} @(403, 404)

Assert-Status "env_access_blocked" {
    Invoke-RestMethod -Uri "$baseUrl/.env" -TimeoutSec 10
} @(403, 404)
