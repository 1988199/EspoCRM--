param([string]$OutputPath)
$ErrorActionPreference = 'Stop'
$quoteRoot = Split-Path $PSScriptRoot -Parent
if (!$OutputPath) {
    $quoteVersion = ([IO.File]::ReadAllText("$quoteRoot/manifest.json") | ConvertFrom-Json).version
    $OutputPath = Join-Path $quoteRoot "dist/quote-management-$quoteVersion.zip"
}
$OutputPath = [IO.Path]::GetFullPath($OutputPath)
if (Test-Path -LiteralPath $OutputPath) { throw '安装包已存在，请指定新路径，避免覆盖。' }
New-Item -ItemType Directory -Force -Path (Split-Path $OutputPath -Parent) | Out-Null
$quoteZip = [IO.Compression.ZipFile]::Open($OutputPath, [IO.Compression.ZipArchiveMode]::Create)
try {
    $quoteFiles = @((Get-Item "$quoteRoot/manifest.json"), (Get-Item "$quoteRoot/LICENSE"))
    $quoteFiles += @(Get-ChildItem "$quoteRoot/files" -File -Recurse)
    $quoteFiles += @(Get-ChildItem "$quoteRoot/scripts" -File -Filter '*.php')
    foreach ($file in $quoteFiles) {
        $relative = $file.FullName.Substring($quoteRoot.Length + 1).Replace('\', '/')
        [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($quoteZip, $file.FullName, $relative) | Out-Null
    }
} finally { $quoteZip.Dispose() }
Get-Item -LiteralPath $OutputPath | Select-Object FullName, Length
