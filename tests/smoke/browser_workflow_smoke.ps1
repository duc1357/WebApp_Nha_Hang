$ErrorActionPreference = "Stop"
. "$PSScriptRoot\_helpers.ps1"

$baseUrl = Get-RestaurantBaseUrl
Assert-RestaurantHostAvailable $baseUrl

node "$PSScriptRoot\browser_workflow_smoke.mjs" $baseUrl
if ($LASTEXITCODE -ne 0) {
    throw "browser_workflow_smoke.mjs exited with code $LASTEXITCODE"
}
