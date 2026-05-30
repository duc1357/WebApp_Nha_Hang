$ErrorActionPreference = "Stop"

function Assert-Contains {
    param(
        [string] $Name,
        [string] $Path,
        [string] $Pattern
    )

    $content = Get-Content $Path -Raw
    if ($content -notmatch $Pattern) {
        throw "$Name failed: $Path does not match $Pattern"
    }
    Write-Output "PASS $Name"
}

function Assert-NotContains {
    param(
        [string] $Name,
        [string] $Path,
        [string] $Pattern
    )

    $content = Get-Content $Path -Raw
    if ($content -match $Pattern) {
        throw "$Name failed: $Path still matches $Pattern"
    }
    Write-Output "PASS $Name"
}

Assert-Contains "login_csrf_ready" "js\login.js" "csrfReady\s*=\s*initializeCsrfToken\(\)"
Assert-Contains "login_fetch_awaits_csrf" "js\login.js" "await\s+csrfReady"
Assert-Contains "admin_login_csrf_ready" "js\admin-login.js" "csrfReady\s*=\s*initializeCsrfToken\(\)"
Assert-Contains "admin_login_fetch_awaits_csrf" "js\admin-login.js" "await\s+csrfReady"
Assert-Contains "forgot_password_csrf_ready" "js\forgot-password.js" "csrfReady\s*=\s*initializeCsrfToken\(\)"
Assert-Contains "forgot_password_fetch_awaits_csrf" "js\forgot-password.js" "await\s+csrfReady"

Assert-NotContains "register_email_not_optional" "login.html" "Email \(tu.{0,4}y ch.{0,4}n\)"
Assert-Contains "register_email_required" "login.html" "<input[^>]+id=""reg-email""[^>]+required"

Assert-Contains "forgot_password_min_length" "js\forgot-password.js" "newPassword\.length\s*<\s*8"
Assert-Contains "forgot_password_uppercase" "js\forgot-password.js" "\[A-Z\]"
Assert-Contains "forgot_password_digit" "js\forgot-password.js" "\\d"
