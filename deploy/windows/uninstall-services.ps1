[CmdletBinding()]
param(
    [string] $NssmPath = "nssm.exe"
)

$ErrorActionPreference = "Stop"
$Nssm = (Get-Command $NssmPath -ErrorAction Stop).Source

foreach ($service in "DocumentInterrogationMcp", "DocumentInterrogationQueue") {
    if (Get-Service -Name $service -ErrorAction SilentlyContinue) {
        Stop-Service -Name $service -Force -ErrorAction SilentlyContinue
        & $Nssm remove $service confirm
        if ($LASTEXITCODE -ne 0) {
            throw "Could not remove service $service"
        }
    }
}
