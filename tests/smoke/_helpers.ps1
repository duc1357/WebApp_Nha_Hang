$ErrorActionPreference = "Stop"

function Get-RestaurantBaseUrl {
    $baseUrl = $env:RESTAURANT_BASE_URL
    if ([string]::IsNullOrWhiteSpace($baseUrl)) {
        $baseUrl = "http://restaurant.test"
    }
    return $baseUrl.TrimEnd("/")
}

function Assert-RestaurantHostAvailable {
    param([string] $BaseUrl)

    try {
        $healthResponse = Invoke-WebRequest -Uri $BaseUrl -TimeoutSec 5 -UseBasicParsing
        if ($healthResponse.StatusCode -lt 200 -or $healthResponse.StatusCode -ge 500) {
            throw "Unexpected status code $($healthResponse.StatusCode)"
        }
    } catch {
        Write-Error "Cannot reach $BaseUrl. Start Laragon/Apache and confirm the virtual host before running smoke tests."
    }
}

function Assert-Status {
    param(
        [string] $Name,
        [scriptblock] $Request,
        [int[]] $ExpectedStatus
    )

    try {
        & $Request | Out-Null
        throw "$Name unexpectedly succeeded"
    } catch {
        $response = $_.Exception.Response
        if ($null -eq $response) {
            throw
        }

        $status = [int]$response.StatusCode
        if ($ExpectedStatus -notcontains $status) {
            throw "$Name returned $status; expected $($ExpectedStatus -join ', ')"
        }

        Write-Output "PASS $Name"
    }
}
