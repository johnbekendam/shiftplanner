[CmdletBinding()]
param(
    [Parameter(Mandatory)]
    [ValidatePattern('^[0-9A-Za-z][0-9A-Za-z._-]*$')]
    [string]$Version
)

$ErrorActionPreference = "Stop"

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw "Docker is not available in PATH."
}

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$artifactDirectory = Join-Path $repoRoot "dist"
$imageName = "shiftplanner:$Version"
$artifactPath = Join-Path $artifactDirectory "shiftplanner-$Version.tar"

New-Item -ItemType Directory -Force -Path $artifactDirectory | Out-Null

docker build --tag $imageName $repoRoot

if ($LASTEXITCODE -ne 0) {
    throw "Docker image build failed."
}

docker image save --output $artifactPath $imageName

if ($LASTEXITCODE -ne 0) {
    throw "Docker image export failed."
}

Write-Output "Created $artifactPath"