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

    Write-Output "PASS $Name"
}

Assert-Contains "logout_card_dark_mode" "style.css" 'html\[data-theme="dark"\]\s+#logout-modal\s+\.logout-modal-card'
Assert-Contains "logout_title_dark_mode" "style.css" 'html\[data-theme="dark"\]\s+#logout-modal\s+h3'
Assert-Contains "logout_cancel_dark_mode" "style.css" 'html\[data-theme="dark"\]\s+#logout-modal\s+\.btn-cancel-logout'
Assert-Contains "logout_icon_dark_mode" "style.css" 'html\[data-theme="dark"\]\s+#logout-modal\s+\.logout-icon-wrap'
