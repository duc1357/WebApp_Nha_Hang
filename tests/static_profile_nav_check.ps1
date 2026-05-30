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

Assert-Contains "profile_nav_disables_user_dropdown" "profile.html" 'data-static-user-header="true"'
Assert-Contains "main_js_checks_static_user_header" "js\main.js" 'staticUserHeader'
Assert-Contains "main_js_skips_dropdown_on_static_header" "js\main.js" 'wrapper\.append\(trigger\)'
