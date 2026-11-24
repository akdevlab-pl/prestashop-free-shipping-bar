# Tworzy ZIP gotowy do wgrania w PrestaShop: paczki\akdl_freeship.zip
# W ZIP-ie musi być folder o nazwie modułu, a w nim pliki.
$ErrorActionPreference = 'Stop'
$modul = 'akdl_freeship'
$zrodlo = $PSScriptRoot
$paczki = Join-Path (Split-Path (Split-Path $zrodlo)) 'paczki'
$tmp = Join-Path ([IO.Path]::GetTempPath()) ("akdl_" + [guid]::NewGuid())
$cel = Join-Path $tmp $modul

$pomijaj = @('.git', '.gitignore', 'docs', 'build.ps1', 'README.md', 'CHANGELOG.md')
New-Item -ItemType Directory -Force $cel | Out-Null
Get-ChildItem $zrodlo -Force | Where-Object { $pomijaj -notcontains $_.Name } |
    Copy-Item -Destination $cel -Recurse -Force

# Każdy podfolder potrzebuje index.php (wymóg bezpieczeństwa PrestaShop).
$wzor = Join-Path $zrodlo 'index.php'
Get-ChildItem $cel -Recurse -Directory | ForEach-Object {
    $i = Join-Path $_.FullName 'index.php'
    if (-not (Test-Path $i)) { Copy-Item $wzor $i }
}

New-Item -ItemType Directory -Force $paczki | Out-Null
$zip = Join-Path $paczki "$modul.zip"
if (Test-Path $zip) { Remove-Item $zip -Force }
# Własne pakowanie zamiast Compress-Archive: ścieżki w ZIP muszą mieć „/”,
# inaczej PrestaShop na serwerze Linux źle rozpakuje moduł.
Add-Type -AssemblyName System.IO.Compression, System.IO.Compression.FileSystem
$archiwum = [IO.Compression.ZipFile]::Open($zip, 'Create')
try {
    Get-ChildItem $cel -Recurse -File | ForEach-Object {
        $wzgl = $modul + '/' + $_.FullName.Substring($cel.Length + 1).Replace('\', '/')
        [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archiwum, $_.FullName, $wzgl) | Out-Null
    }
} finally { $archiwum.Dispose() }
Remove-Item $tmp -Recurse -Force
Write-Host "Gotowe: $zip"
