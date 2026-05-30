$ErrorActionPreference = "Stop"
. "$PSScriptRoot\_helpers.ps1"

$baseUrl = Get-RestaurantBaseUrl
Assert-RestaurantHostAvailable $baseUrl

function Get-CsrfToken {
    param([Microsoft.PowerShell.Commands.WebRequestSession] $Session)

    $response = Invoke-RestMethod -Uri "$baseUrl/api/auth/get_csrf.php" -WebSession $Session -TimeoutSec 10
    if ([string]::IsNullOrWhiteSpace($response.csrf_token)) {
        throw "CSRF token was empty"
    }
    return $response.csrf_token
}

function Invoke-JsonPost {
    param(
        [string] $Uri,
        [hashtable] $Body,
        [Microsoft.PowerShell.Commands.WebRequestSession] $Session,
        [string] $Csrf
    )

    Invoke-RestMethod `
        -Uri $Uri `
        -Method Post `
        -Body ($Body | ConvertTo-Json) `
        -ContentType "application/json; charset=utf-8" `
        -Headers @{ "X-CSRF-Token" = $Csrf } `
        -WebSession $Session `
        -TimeoutSec 10
}

function Assert-JsonStatus {
    param(
        [string] $Name,
        [scriptblock] $Request,
        [int[]] $ExpectedStatus
    )

    Assert-Status $Name $Request $ExpectedStatus
}

$adminSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$adminCsrf = Get-CsrfToken $adminSession

$adminLogin = Invoke-JsonPost `
    -Uri "$baseUrl/api/admin/login.php" `
    -Body @{ email = "admin.demo@example.test"; password = "ChangeMeDemo123!" } `
    -Session $adminSession `
    -Csrf $adminCsrf

if ($adminLogin.success -ne $true) {
    throw "admin login failed"
}

$selfUsers = Invoke-RestMethod -Uri "$baseUrl/api/admin/get_users_list.php?search=admin.demo@example.test&limit=1" -WebSession $adminSession -TimeoutSec 10
$selfAdmin = @($selfUsers.users | Where-Object { $_.email -eq "admin.demo@example.test" }) | Select-Object -First 1
if ($null -eq $selfAdmin) {
    throw "admin self user was not found"
}

$adminCsrf = Get-CsrfToken $adminSession
$selfUpdate = Invoke-JsonPost `
    -Uri "$baseUrl/api/admin/update_user.php" `
    -Body @{
        id = [int]$selfAdmin.id
        name = $selfAdmin.name
        phone = $selfAdmin.phone
        email = $selfAdmin.email
        password = ""
        role = "admin"
    } `
    -Session $adminSession `
    -Csrf $adminCsrf

if ($selfUpdate.success -ne $true) {
    throw "admin self update failed"
}

$postSelfUpdateStats = Invoke-RestMethod -Uri "$baseUrl/api/admin/get_stats.php" -WebSession $adminSession -TimeoutSec 10
if ($postSelfUpdateStats.success -ne $true) {
    throw "admin session was not synced after self update"
}

Write-Output "PASS admin_self_update_session_synced"

$adminCsrf = Get-CsrfToken $adminSession
$suffix = [DateTimeOffset]::UtcNow.ToUnixTimeMilliseconds()
$email = "auth-smoke-$suffix@example.test"
$phoneSeed = ([string]$suffix)
$phone = "0" + $phoneSeed.Substring([Math]::Max(0, $phoneSeed.Length - 9))
$updatedPhone = "1" + $phone.Substring(1)
$updatedPhone = "0" + $updatedPhone.Substring(1)
$password = "ChangeMe123!"

$created = Invoke-JsonPost `
    -Uri "$baseUrl/api/admin/create_user.php" `
    -Body @{
        name = "Auth Smoke $suffix"
        phone = $phone
        email = $email
        password = $password
        role = "customer"
    } `
    -Session $adminSession `
    -Csrf $adminCsrf

if ($created.success -ne $true) {
    throw "test user creation failed"
}

$users = Invoke-RestMethod -Uri "$baseUrl/api/admin/get_users_list.php?search=$([uri]::EscapeDataString($email))&limit=1" -WebSession $adminSession -TimeoutSec 10
$testUser = @($users.users | Where-Object { $_.email -eq $email }) | Select-Object -First 1
if ($null -eq $testUser) {
    throw "created test user was not found"
}

$userId = [int]$testUser.id
$testUserDeleted = $false

try {
    $userSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $userCsrf = Get-CsrfToken $userSession
    $userLogin = Invoke-JsonPost `
        -Uri "$baseUrl/api/auth/login.php" `
        -Body @{ identifier = $email; password = $password } `
        -Session $userSession `
        -Csrf $userCsrf

    if ($userLogin.success -ne $true -or [string]::IsNullOrWhiteSpace($userLogin.token)) {
        throw "test user login failed"
    }

    $adminCsrf = Get-CsrfToken $adminSession
    $updated = Invoke-JsonPost `
        -Uri "$baseUrl/api/admin/update_user.php" `
        -Body @{
            id = $userId
            name = "Auth Smoke Updated $suffix"
            phone = $updatedPhone
            email = $email
            password = ""
            role = "customer"
        } `
        -Session $adminSession `
        -Csrf $adminCsrf

    if ($updated.success -ne $true) {
        throw "test user update failed"
    }

    Assert-JsonStatus "updated_user_old_session_revoked" {
        Invoke-RestMethod -Uri "$baseUrl/api/user/get_user_history.php" -WebSession $userSession -TimeoutSec 10
    } @(401, 403)

    Assert-JsonStatus "updated_user_old_jwt_refresh_revoked" {
        Invoke-RestMethod `
            -Uri "$baseUrl/api/auth/refresh_token.php" `
            -Method Post `
            -Body (@{ token = $userLogin.token } | ConvertTo-Json) `
            -ContentType "application/json; charset=utf-8" `
            -TimeoutSec 10
    } @(401)

    $adminCsrf = Get-CsrfToken $adminSession
    $deleted = Invoke-JsonPost `
        -Uri "$baseUrl/api/admin/delete_user.php" `
        -Body @{ id = $userId } `
        -Session $adminSession `
        -Csrf $adminCsrf

    if ($deleted.success -ne $true) {
        throw "test user delete failed"
    }
    $testUserDeleted = $true

    Assert-JsonStatus "deleted_user_login_rejected" {
        $deletedSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
        $deletedCsrf = Get-CsrfToken $deletedSession
        Invoke-JsonPost `
            -Uri "$baseUrl/api/auth/login.php" `
            -Body @{ identifier = $email; password = $password } `
            -Session $deletedSession `
            -Csrf $deletedCsrf
    } @(401)
} finally {
    if (-not $testUserDeleted -and $userId -gt 0) {
        try {
            $cleanupCsrf = Get-CsrfToken $adminSession
            Invoke-JsonPost `
                -Uri "$baseUrl/api/admin/delete_user.php" `
                -Body @{ id = $userId } `
                -Session $adminSession `
                -Csrf $cleanupCsrf | Out-Null
            Write-Output "PASS auth_lifecycle_cleanup"
        } catch {
            Write-Warning "auth lifecycle cleanup failed for user id $userId`: $($_.Exception.Message)"
        }
    }
}

Write-Output "PASS auth_lifecycle_smoke"
