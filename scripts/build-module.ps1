[CmdletBinding()]
param(
    [string]$OutputDirectory = (Join-Path $PSScriptRoot '..\release')
)

$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$moduleSource = Join-Path $projectRoot 'bitrix-module\safeai.b2brequest'
$outputRoot = [System.IO.Path]::GetFullPath($OutputDirectory)
$releaseRoot = Join-Path $outputRoot 'safeai.b2brequest'
$archivePath = Join-Path $outputRoot 'safeai.b2brequest-0.2.0.zip'

if (-not (Test-Path -LiteralPath $moduleSource -PathType Container)) {
    throw "Module source was not found: $moduleSource"
}

New-Item -ItemType Directory -Force -Path $outputRoot | Out-Null
if (Test-Path -LiteralPath $releaseRoot) {
    Remove-Item -LiteralPath $releaseRoot -Recurse -Force
}
if (Test-Path -LiteralPath $archivePath) {
    Remove-Item -LiteralPath $archivePath -Force
}

Copy-Item -LiteralPath $moduleSource -Destination $releaseRoot -Recurse
Get-ChildItem -LiteralPath $releaseRoot -Recurse -Directory -Force |
    Where-Object { $_.Name -in @('vendor', '.phpunit.cache') } |
    Sort-Object FullName -Descending |
    Remove-Item -Recurse -Force

Compress-Archive -LiteralPath $releaseRoot -DestinationPath $archivePath -CompressionLevel Optimal
$hash = Get-FileHash -LiteralPath $archivePath -Algorithm SHA256

Write-Host "Created: $archivePath"
Write-Host "SHA256: $($hash.Hash)"

