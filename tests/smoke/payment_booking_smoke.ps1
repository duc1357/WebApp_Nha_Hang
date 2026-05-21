$ErrorActionPreference = "Stop"
. "$PSScriptRoot\_helpers.ps1"

$baseUrl = Get-RestaurantBaseUrl
Assert-RestaurantHostAvailable $baseUrl

function New-CustomerSession {
    $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $csrf = (Invoke-RestMethod -Uri "$baseUrl/api/auth/get_csrf.php" -WebSession $session -TimeoutSec 10).csrf_token
    Invoke-RestMethod `
        -Uri "$baseUrl/api/auth/login.php" `
        -Method Post `
        -Body (@{ identifier = "customer.demo@example.test"; password = "ChangeMeDemo123!" } | ConvertTo-Json) `
        -ContentType "application/json; charset=utf-8" `
        -Headers @{ "X-CSRF-Token" = $csrf } `
        -WebSession $session `
        -TimeoutSec 10 | Out-Null

    $csrf = (Invoke-RestMethod -Uri "$baseUrl/api/auth/get_csrf.php" -WebSession $session -TimeoutSec 10).csrf_token
    return @{
        Session = $session
        Csrf = $csrf
    }
}

$menu = Invoke-RestMethod -Uri "$baseUrl/api/menu/get_menu.php" -TimeoutSec 10
$firstItem = $menu.data | Select-Object -First 1
if ($null -eq $firstItem) {
    throw "No menu item available for smoke test"
}

$ctx = New-CustomerSession

Assert-Status "empty_cart_rejected" {
    Invoke-RestMethod `
        -Uri "$baseUrl/api/payment/create_payment.php" `
        -Method Post `
        -Body (@{ items = @(); payment_method = "cash" } | ConvertTo-Json -Depth 4) `
        -ContentType "application/json; charset=utf-8" `
        -Headers @{ "X-CSRF-Token" = $ctx.Csrf } `
        -WebSession $ctx.Session `
        -TimeoutSec 10
} @(400)

Assert-Status "invalid_table_rejected_before_order_insert" {
    Invoke-RestMethod `
        -Uri "$baseUrl/api/payment/create_payment.php" `
        -Method Post `
        -Body (@{ items = @(@{ id = [int]$firstItem.id; quantity = 1 }); payment_method = "cash"; table_id = "not-a-table" } | ConvertTo-Json -Depth 5) `
        -ContentType "application/json; charset=utf-8" `
        -Headers @{ "X-CSRF-Token" = $ctx.Csrf } `
        -WebSession $ctx.Session `
        -TimeoutSec 10
} @(400)

Assert-Status "past_booking_rejected" {
    Invoke-RestMethod `
        -Uri "$baseUrl/api/user/book_table.php" `
        -Method Post `
        -Body (@{ name = "Smoke Test"; phone = "0123456789"; date = "2020-01-01"; time = "18:00"; guests = 2; floor = "Smoke"; table_id = 1 } | ConvertTo-Json -Depth 4) `
        -ContentType "application/json; charset=utf-8" `
        -Headers @{ "X-CSRF-Token" = $ctx.Csrf } `
        -WebSession $ctx.Session `
        -TimeoutSec 10
} @(422)

$futureDate = (Get-Date).AddDays(30).ToString("yyyy-MM-dd")
Assert-Status "empty_preorder_rejected" {
    Invoke-RestMethod `
        -Uri "$baseUrl/api/user/book_table.php" `
        -Method Post `
        -Body (@{ name = "Smoke Test"; phone = "0123456789"; date = $futureDate; time = "18:00"; guests = 2; floor = "Smoke"; table_id = 1; has_preorder = $true; items = @() } | ConvertTo-Json -Depth 5) `
        -ContentType "application/json; charset=utf-8" `
        -Headers @{ "X-CSRF-Token" = $ctx.Csrf } `
        -WebSession $ctx.Session `
        -TimeoutSec 10
} @(422)

$php = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe"
$fixture = & $php .\tests\smoke\payment_webhook_fixture.php setup | ConvertFrom-Json

try {
    $webhookConfig = & $php .\tests\smoke\payment_webhook_fixture.php webhook-config | ConvertFrom-Json
    $token = $env:SEPAY_WEBHOOK_TOKEN
    if ([string]::IsNullOrWhiteSpace($token)) {
        $token = $webhookConfig.token
    }
    $gateway = $webhookConfig.bank
    $account = $webhookConfig.account

    $orderWebhookBody = @{
        gateway = $gateway
        accountNumber = $account
        transferType = "in"
        transferAmount = [int]$fixture.order_amount
        content = "Thanh toan DH$($fixture.order_id)"
    } | ConvertTo-Json

    Assert-Status "insufficient_order_payment_rejected" {
        Invoke-RestMethod `
            -Uri "$baseUrl/api/payment/webhook.php" `
            -Method Post `
            -Body (@{
                gateway = $gateway
                accountNumber = $account
                transferType = "in"
                transferAmount = 1
                content = "Thanh toan DH$($fixture.order_id)"
            } | ConvertTo-Json) `
            -ContentType "application/json; charset=utf-8" `
            -Headers @{ Authorization = "Apikey $token" } `
            -TimeoutSec 10
    } @(400)

    $firstOrder = Invoke-RestMethod `
        -Uri "$baseUrl/api/payment/webhook.php" `
        -Method Post `
        -Body $orderWebhookBody `
        -ContentType "application/json; charset=utf-8" `
        -Headers @{ Authorization = "Apikey $token" } `
        -TimeoutSec 10

    $secondOrder = Invoke-RestMethod `
        -Uri "$baseUrl/api/payment/webhook.php" `
        -Method Post `
        -Body $orderWebhookBody `
        -ContentType "application/json; charset=utf-8" `
        -Headers @{ Authorization = "Apikey $token" } `
        -TimeoutSec 10

    $bookingWebhookBody = @{
        gateway = $gateway
        accountNumber = $account
        transferType = "in"
        transferAmount = [int]$fixture.booking_amount
        content = "Thanh toan BKG$($fixture.booking_id)"
    } | ConvertTo-Json

    Assert-Status "insufficient_booking_deposit_rejected" {
        Invoke-RestMethod `
            -Uri "$baseUrl/api/payment/webhook.php" `
            -Method Post `
            -Body (@{
                gateway = $gateway
                accountNumber = $account
                transferType = "in"
                transferAmount = 1
                content = "Thanh toan BKG$($fixture.booking_id)"
            } | ConvertTo-Json) `
            -ContentType "application/json; charset=utf-8" `
            -Headers @{ Authorization = "Apikey $token" } `
            -TimeoutSec 10
    } @(400)

    $firstBooking = Invoke-RestMethod `
        -Uri "$baseUrl/api/payment/webhook.php" `
        -Method Post `
        -Body $bookingWebhookBody `
        -ContentType "application/json; charset=utf-8" `
        -Headers @{ Authorization = "Apikey $token" } `
        -TimeoutSec 10

    $secondBooking = Invoke-RestMethod `
        -Uri "$baseUrl/api/payment/webhook.php" `
        -Method Post `
        -Body $bookingWebhookBody `
        -ContentType "application/json; charset=utf-8" `
        -Headers @{ Authorization = "Apikey $token" } `
        -TimeoutSec 10

    $status = & $php .\tests\smoke\payment_webhook_fixture.php status $fixture.order_id $fixture.booking_id $fixture.voucher_code | ConvertFrom-Json

    if ($firstOrder.success -ne $true -or $secondOrder.message -ne "Order already paid") {
        throw "duplicate order webhook did not return idempotent success"
    }
    if ($status.order_status -ne "paid" -or [int]$status.voucher_used_count -ne 1) {
        throw "duplicate order webhook changed final state incorrectly"
    }
    Write-Output "PASS duplicate_order_webhook_is_idempotent"

    if ($firstBooking.success -ne $true -or $secondBooking.message -ne "Booking deposit already paid") {
        throw "duplicate booking webhook did not return idempotent success"
    }
    if ($status.booking_status -ne "confirmed" -or $status.booking_payment_status -ne "partial") {
        throw "duplicate booking webhook changed final state incorrectly"
    }
    Write-Output "PASS duplicate_booking_webhook_is_idempotent"
} finally {
    if ($fixture) {
        & $php .\tests\smoke\payment_webhook_fixture.php cleanup $fixture.order_id $fixture.booking_id $fixture.voucher_code | Out-Null
    }
}
