# RS8 Web Guard - managed Chromium extension deployment.
# Only approved signed extension store packages may be auto-installed.
# Chrome/Edge Windows enterprise policy survives ordinary browser reinstall
# provided OS management policies remain intact.
[CmdletBinding()]
param(
  [string]$ConfigUrl='https://raw.githubusercontent.com/jhayeroe/pulse-downloads/main/rs8-web-guard/enterprise/extension-ids.json',
  [switch]$StatusOnly
)
$ErrorActionPreference='Stop'
function TestAdmin {
  $principal=[Security.Principal.WindowsPrincipal]::new([Security.Principal.WindowsIdentity]::GetCurrent())
  return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}
function PolicyItems([string]$path) {
  if(-not (Test-Path -LiteralPath $path)){return @()}
  return @((Get-ItemProperty -LiteralPath $path).PSObject.Properties |
      Where-Object {$_.Name -match '^\d+$'} |
      ForEach-Object { [pscustomobject]@{Index=[int]$_.Name;Value=[string]$_.Value} })
}
function AddApprovedPolicy([string]$name,[string]$path,[string]$id,[string]$url) {
  if(-not $id){Write-Host "$name : extension not yet published; automatic installation NOT available." -ForegroundColor Yellow;return}
  if($id -cnotmatch '^[a-p]{32}$'){throw "$name : invalid Chrome extension store ID."}
  $value="$id;$url"
  $items=@(PolicyItems $path)
  if($items | Where-Object {$_.Value -ceq $value}){
    Write-Host "$name : force-install policy already configured." -ForegroundColor Green
    return
  }
  if($StatusOnly){
    Write-Host "$name : extension ID configured; force-install policy not yet found." -ForegroundColor Yellow
    return
  }
  if(-not (TestAdmin)){throw 'Run as Administrator to set machine-wide extension policies.'}
  if(-not (Test-Path -LiteralPath $path)){New-Item -Path $path -Force | Out-Null}
  $used=@((PolicyItems $path) | ForEach-Object {$_.Index})
  $slot=1
  while($slot -in $used -and $slot -le 1000){$slot++}
  if($slot -gt 1000){throw "$name : all enterprise extension policy slots occupied."}
  New-ItemProperty -LiteralPath $path -Name ([string]$slot) -Value $value -PropertyType String -Force | Out-Null
  Write-Host "$name : RS8 force-install policy applied. Restart the browser to verify." -ForegroundColor Green
}
try {
  $config=Invoke-RestMethod -Uri $ConfigUrl -TimeoutSec 20
  if([int]$config.schema -ne 1){throw 'Unexpected enterprise extension configuration schema.'}
  $chrome=[string]$config.chrome_web_store_id
  $edge=[string]$config.edge_addons_id
  $chromeURL='https://clients2.google.com/service/update2/crx'
  $edgeURL='https://edge.microsoft.com/extensionwebstorebase/v1/crx'
  AddApprovedPolicy 'Chrome' 'HKLM:\SOFTWARE\Policies\Google\Chrome\ExtensionInstallForcelist' $chrome $chromeURL
  if($edge){
    AddApprovedPolicy 'Edge' 'HKLM:\SOFTWARE\Policies\Microsoft\Edge\ExtensionInstallForcelist' $edge $edgeURL
  }else{
    AddApprovedPolicy 'Edge (Chrome Web Store)' 'HKLM:\SOFTWARE\Policies\Microsoft\Edge\ExtensionInstallForcelist' $chrome $chromeURL
  }
  if([bool]$config.brave_chrome_store_enabled){
    AddApprovedPolicy 'Brave (validate policy support before rollout)' 'HKLM:\SOFTWARE\Policies\BraveSoftware\Brave\ExtensionInstallForcelist' $chrome $chromeURL
  }
  Write-Host 'Note: Firefox requires a separately signed XPI and Mozilla enterprise deployment.' -ForegroundColor Yellow
  Write-Host 'Note: New/unmanaged browsers are not protected unless added to management or blocked by device application control.' -ForegroundColor Yellow
  Write-Host 'Check chrome://policy or edge://policy for ExtensionInstallForcelist status OK.' -ForegroundColor Cyan
}catch{
  Write-Host ('RS8 managed extension deployment error: '+$_.Exception.Message) -ForegroundColor Red
  exit 1
}
