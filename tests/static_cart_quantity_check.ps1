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

Assert-Contains "increase_function_exists" "js\cart.js" "increaseCartItem"
Assert-Contains "decrease_function_exists" "js\cart.js" "decreaseCartItem"
Assert-Contains "quantity_controls_rendered" "js\cart.js" "cart-qty-controls"
Assert-Contains "decrease_removes_at_zero" "js\cart.js" "item\.quantity\s*<=\s*1"
