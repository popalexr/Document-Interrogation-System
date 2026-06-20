[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string] $ProjectRoot,

    [string] $PhpPath = "php.exe",

    [string] $NssmPath = "nssm.exe"
)

$ErrorActionPreference = "Stop"
$ProjectRoot = (Resolve-Path -LiteralPath $ProjectRoot).Path
$McpDirectory = Join-Path $ProjectRoot "Application\MCP_Server"
$WebDirectory = Join-Path $ProjectRoot "Application\Web"
$PythonPath = Join-Path $McpDirectory ".venv\Scripts\python.exe"

if (-not (Test-Path -LiteralPath $PythonPath -PathType Leaf)) {
    throw "Python executable not found: $PythonPath"
}

$NssmCommand = Get-Command $NssmPath -ErrorAction Stop
$PhpCommand = Get-Command $PhpPath -ErrorAction Stop
$Nssm = $NssmCommand.Source
$Php = $PhpCommand.Source

function Set-NssmValue {
    param(
        [string] $Service,
        [string] $Name,
        [Parameter(ValueFromRemainingArguments = $true)]
        [string[]] $Value
    )

    & $Nssm set $Service $Name @Value
    if ($LASTEXITCODE -ne 0) {
        throw "nssm failed while setting $Name for $Service"
    }
}

function Install-NssmService {
    param(
        [string] $Name,
        [string] $Application,
        [string] $Arguments,
        [string] $WorkingDirectory,
        [string] $Description
    )

    $existing = Get-Service -Name $Name -ErrorAction SilentlyContinue
    if (-not $existing) {
        & $Nssm install $Name $Application
        if ($LASTEXITCODE -ne 0) {
            throw "Could not install service $Name"
        }
    } else {
        Stop-Service -Name $Name -Force -ErrorAction SilentlyContinue
    }

    Set-NssmValue $Name Application $Application
    Set-NssmValue $Name AppParameters $Arguments
    Set-NssmValue $Name AppDirectory $WorkingDirectory
    Set-NssmValue $Name Description $Description
    Set-NssmValue $Name Start SERVICE_AUTO_START
    Set-NssmValue $Name AppExit Default Restart
    Set-NssmValue $Name AppRestartDelay 5000

    Start-Service -Name $Name
}

Install-NssmService `
    -Name "DocumentInterrogationMcp" `
    -Application $PythonPath `
    -Arguments "mcp_client.py" `
    -WorkingDirectory $McpDirectory `
    -Description "Document Interrogation MCP server"

Install-NssmService `
    -Name "DocumentInterrogationQueue" `
    -Application $Php `
    -Arguments "artisan queue:work --sleep=3 --tries=3 --timeout=90" `
    -WorkingDirectory $WebDirectory `
    -Description "Document Interrogation Laravel queue worker"

Get-Service -Name "DocumentInterrogationMcp", "DocumentInterrogationQueue"
