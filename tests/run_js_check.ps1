$ErrorActionPreference = "Stop"

$files = @(rg --files -g "*.js")
$failed = 0

foreach ($file in $files) {
    $output = node --check $file 2>&1
    if ($LASTEXITCODE -ne 0) {
        $failed++
        Write-Output "FAIL $file"
        Write-Output $output
    }
}

if ($failed -gt 0) {
    Write-Error "JS syntax check failed for $failed file(s)"
}

Write-Output "JS syntax check passed for $($files.Count) file(s)"
