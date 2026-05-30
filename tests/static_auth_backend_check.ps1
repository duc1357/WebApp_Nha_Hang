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

Assert-Contains "session_missing_token_version_rejected" "api\services\auth_state_service.php" '!\s*isset\(\$_SESSION\[''token_version''\]\)'
Assert-Contains "session_token_version_mismatch_rejected" "api\services\auth_state_service.php" '\(int\)\$_SESSION\[''token_version''\]\s*!==\s*\$dbTokenVersion'
Assert-Contains "session_sync_helper_exists" "api\services\auth_state_service.php" 'function\s+syncCurrentSessionVersion'
Assert-Contains "admin_self_update_syncs_session" "api\admin\update_user.php" 'AuthStateService::syncCurrentSessionVersion'
