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

Assert-Contains "qr_header_panel_rule" "style.css" "\.customer-payment-panel\s+\.modal-header"
Assert-Contains "qr_header_rounded" "style.css" "\.customer-payment-panel\s+\.modal-header[\s\S]*border-radius"
