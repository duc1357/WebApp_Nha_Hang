$ErrorActionPreference = "Stop"
$php = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe"

if (-not (Test-Path $php)) {
    Write-Error "PHP binary not found at $php"
}

& $php .\tests\unit_helper_test.php
if ($LASTEXITCODE -ne 0) {
    Write-Error "PHP unit helper tests failed"
}
