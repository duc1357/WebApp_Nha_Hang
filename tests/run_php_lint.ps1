$ErrorActionPreference = "Stop"
$php = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe"

if (-not (Test-Path $php)) {
    Write-Error "PHP binary not found at $php"
}

$files = @(rg --files -g "*.php")
$failed = 0

foreach ($file in $files) {
    $output = & $php -l $file 2>&1
    if ($LASTEXITCODE -ne 0) {
        $failed++
        Write-Output "FAIL $file"
        Write-Output $output
    }
}

if ($failed -gt 0) {
    Write-Error "PHP lint failed for $failed file(s)"
}

Write-Output "PHP lint passed for $($files.Count) file(s)"
