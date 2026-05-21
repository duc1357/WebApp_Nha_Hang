$ErrorActionPreference = "Stop"

$files = @(
    "admin/index.php",
    "admin/dashboard.php",
    "admin/users.php",
    "admin/vouchers.php",
    "admin/orders.php",
    "admin/bookings.php",
    "admin/menu.php",
    "admin/logs.php",
    "admin/sidebar.php",
    "profile.html",
    "js/menu.js",
    "js/profile.js",
    "js/utils.js",
    "js/admin-login.js",
    "js/admin-common.js",
    "js/admin-logs.js",
    "js/admin-menu.js",
    "js/admin-users.js",
    "js/admin-vouchers.js",
    "js/admin-orders.js",
    "js/admin-bookings.js",
    "js/admin-dashboard.js",
    "js/profile-inline.js"
)

$failed = 0

foreach ($file in $files) {
    $content = Get-Content -Raw -Encoding UTF8 -Path $file

    if ($content -match "<script(?![^>]*\bsrc=)[^>]*>") {
        Write-Output "FAIL inline_script $file"
        $failed++
    }

    if ($content -match "\son[a-z]+\s*=") {
        Write-Output "FAIL inline_handler $file"
        $failed++
    }
}

if ($failed -gt 0) {
    Write-Error "CSP inline smoke failed for $failed issue(s)"
}

Write-Output "PASS csp_inline_smoke"
