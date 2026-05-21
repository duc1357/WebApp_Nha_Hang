$ErrorActionPreference = "Stop"
. "$PSScriptRoot\_helpers.ps1"

$baseUrl = Get-RestaurantBaseUrl
Assert-RestaurantHostAvailable $baseUrl

node "$PSScriptRoot\browser_console_smoke.mjs" $baseUrl
if ($LASTEXITCODE -ne 0) {
    throw "browser_console_smoke.mjs exited with code $LASTEXITCODE"
}
