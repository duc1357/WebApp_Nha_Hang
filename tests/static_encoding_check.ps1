$ErrorActionPreference = "Stop"

$utf8Strict = New-Object System.Text.UTF8Encoding($false, $true)
$root = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$extensions = @(".php", ".js", ".html", ".css", ".md", ".ps1", ".sql", ".example")
$excludedPathParts = @(
    [IO.Path]::DirectorySeparatorChar + ".git" + [IO.Path]::DirectorySeparatorChar,
    [IO.Path]::DirectorySeparatorChar + ".worktrees" + [IO.Path]::DirectorySeparatorChar,
    [IO.Path]::DirectorySeparatorChar + "logs" + [IO.Path]::DirectorySeparatorChar
)

$files = Get-ChildItem -Path $root -Recurse -File |
    Where-Object {
        $path = $_.FullName
        $isExcluded = $false
        foreach ($part in $excludedPathParts) {
            if ($path.Contains($part)) {
                $isExcluded = $true
                break
            }
        }

        -not $isExcluded -and $extensions.Contains($_.Extension)
    }

$checked = 0
foreach ($file in $files) {
    try {
        $bytes = [IO.File]::ReadAllBytes($file.FullName)
        [void]$utf8Strict.GetString($bytes)
        $checked++
    } catch {
        $relative = $file.FullName.Substring($root.Length + 1)
        throw "FAIL utf8_valid $relative"
    }
}

Write-Output "PASS utf8_valid_source_files ($checked file(s))"

