$ErrorActionPreference = "Stop"

function Assert-Contains {
    param(
        [string]$Name,
        [string]$Path,
        [string]$Pattern
    )
    $content = Get-Content $Path -Raw
    if ($content -notmatch $Pattern) {
        Write-Error "FAIL $Name"
    }
    Write-Host "PASS $Name"
}

Assert-Contains "trusted_proxy_flag" "config\constants.php" "TRUST_PROXY_HEADERS"
Assert-Contains "trusted_proxy_ips" "config\constants.php" "TRUSTED_PROXY_IPS"
Assert-Contains "rate_limit_trusts_proxy_conditionally" "api\services\rate_limit_service.php" "isTrustedProxy"
Assert-Contains "rate_limit_uses_remote_addr_first" "api\services\rate_limit_service.php" "REMOTE_ADDR"
Assert-Contains "json_unescaped_unicode" "api\services\response_service.php" "JSON_UNESCAPED_UNICODE"
Assert-Contains "json_throw_on_error" "api\services\response_service.php" "JSON_THROW_ON_ERROR"
Assert-Contains "export_revenue_date_range" "api\admin\export_revenue.php" "DateTimeImmutable"
Assert-Contains "export_revenue_max_days" "api\admin\export_revenue.php" "maxExportDays"
Assert-Contains "export_revenue_logger" "api\admin\export_revenue.php" "Logger::"
Assert-Contains "export_revenue_prepared_statement" "api\admin\export_revenue.php" "prepare\("
Assert-Contains "admin_audio_lazy_init" "js\admin-common.js" "function getAudioContext"
Assert-Contains "admin_audio_guarded" "js\admin-common.js" "try"
Assert-Contains "public_menu_filters_active_items_server_side" "api\menu\get_menu.php" "is_active\s*=\s*1"
Assert-Contains "profile_update_checks_duplicate_phone" "api\user\update_profile.php" "SELECT\s+id\s+FROM\s+users\s+WHERE\s+\(email\s*=\s*\?\s+OR\s+phone\s*=\s*\?\)"
Assert-Contains "change_password_syncs_current_session" "api\auth\change_password.php" "AuthStateService::syncCurrentSessionVersion"
Assert-Contains "admin_menu_list_requires_admin" "api\menu\get_menu_list.php" "auth_check_api\.php"
Assert-Contains "admin_dashboard_uses_admin_menu_list" "js\admin-dashboard.js" "get_menu_list\.php\?page=1&limit=100"

# --- API BOUNDARY & SECURITY ENFORCEMENT CHECKS ---

# 1. CSRF Checks for mutating user/payment APIs
Assert-Contains "book_table_requires_csrf" "api\user\book_table.php" "CsrfService::validateRequest"
Assert-Contains "submit_review_requires_csrf" "api\user\submit_review.php" "CsrfService::validateRequest"
Assert-Contains "update_profile_requires_csrf" "api\user\update_profile.php" "CsrfService::validateRequest"
Assert-Contains "upload_avatar_requires_csrf" "api\user\upload_avatar.php" "CsrfService::validateRequest"
Assert-Contains "create_payment_requires_csrf" "api\payment\create_payment.php" "CsrfService::validateRequest"

# 2. Session Auth Checks for user/payment APIs (Guarding against unauthorized actions)
Assert-Contains "book_table_requires_session" "api\user\book_table.php" "AuthStateService::requireSession|requireAuth"
Assert-Contains "submit_review_requires_session" "api\user\submit_review.php" "AuthStateService::requireSession|requireAuth"
Assert-Contains "update_profile_requires_session" "api\user\update_profile.php" "AuthStateService::requireSession|requireAuth"
Assert-Contains "upload_avatar_requires_session" "api\user\upload_avatar.php" "AuthStateService::requireSession|requireAuth"
Assert-Contains "create_payment_requires_session" "api\payment\create_payment.php" "AuthStateService::requireSession|requireAuth"
Assert-Contains "get_order_details_requires_session" "api\user\get_order_details.php" "AuthStateService::requireSession|requireAuth"
Assert-Contains "get_user_history_requires_session" "api\user\get_user_history.php" "AuthStateService::requireSession|requireAuth"

# 3. IDOR Ownership Checks (Ensure users can only access their own data)
Assert-Contains 'get_order_details_ownership_check' 'api\user\get_order_details.php' 'user_id\s*=\s*\?'
Assert-Contains 'get_user_history_orders_ownership_check' 'api\user\get_user_history.php' 'user_id\s*=\s*\?'
Assert-Contains 'submit_review_ownership_check' 'api\user\submit_review.php' 'user_id\s*=\s*\?'



# 4. Dynamic Scanning of api/user/ and api/payment/ files (Except webhook.php)
$protectedFolders = @("api\user", "api\payment")
foreach ($folder in $protectedFolders) {
    if (Test-Path $folder) {
        $files = Get-ChildItem -Path $folder -Filter "*.php" -File
        foreach ($file in $files) {
            # Skip files that don't need user session authentication (e.g. webhook.php or logs)
            if ($file.Name -eq "webhook.php" -or $file.Name -like "*error*") {
                continue
            }
            $content = Get-Content $file.FullName -Raw
            if ($content -notmatch "AuthStateService::requireSession|requireAuth") {
                Write-Error "FAIL security_auth_check_missing in $($file.FullName)"
            }
            Write-Host "PASS security_auth_check_verified in $($file.Name)"
        }
    }
}

