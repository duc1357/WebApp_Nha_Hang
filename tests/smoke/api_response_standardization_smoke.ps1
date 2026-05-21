$ErrorActionPreference = "Stop"

$excludedFiles = @(
    "api/services/response_service.php",
    "api/admin/export_revenue.php"
)

$files = Get-ChildItem -Path "api" -Recurse -Filter "*.php" |
    ForEach-Object { $_.FullName.Substring((Get-Location).Path.Length + 1).Replace("\", "/") } |
    Where-Object { $excludedFiles -notcontains $_ }

$manualPatterns = @(
    "echo\s+json_encode",
    "http_response_code\s*\(",
    "header\s*\(\s*['""]Content-Type:\s*application/json"
)

$failed = 0

foreach ($file in $files) {
    if (-not (Test-Path $file)) {
        Write-Output "FAIL missing_file $file"
        $failed++
        continue
    }

    $content = Get-Content -Raw -Encoding UTF8 -Path $file
    foreach ($pattern in $manualPatterns) {
        if ($content -match $pattern) {
            Write-Output "FAIL manual_response $file pattern=$pattern"
            $failed++
            break
        }
    }
}

if ($failed -gt 0) {
    Write-Error "API response standardization smoke failed for $failed file(s)"
}

Write-Output "PASS api_response_standardization"
