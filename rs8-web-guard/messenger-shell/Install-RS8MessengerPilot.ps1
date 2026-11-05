# RS8 Messenger Restricted Navigation - PILOT installer
# Run in a normal Windows PowerShell 5.1 session. Does not modify RS8 Web Guard policies.
[CmdletBinding()]
param([switch]$NoLaunch)
$ErrorActionPreference='Stop'
if($PSVersionTable.PSEdition -ne 'Desktop'){
  throw 'Use Windows PowerShell 5.1, not PowerShell 7. Launch powershell.exe.'
}
[Net.ServicePointManager]::SecurityProtocol=[Net.SecurityProtocolType]::Tls12
$root = Join-Path $env:LOCALAPPDATA 'RS8Messenger'
$lib = Join-Path $root 'lib'
$source = 'https://raw.githubusercontent.com/jhayeroe/pulse-downloads/main/rs8-web-guard/messenger-shell'
$psExe = Join-Path $env:WINDIR 'System32\WindowsPowerShell\v1.0\powershell.exe'
$temporary = Join-Path $env:TEMP ('rs8-messenger-'+[Guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $root,$lib,$temporary -Force | Out-Null
try {
  foreach($item in @('Start-RS8Messenger.ps1','navigation-guard.js')){
    $to = Join-Path $root $item
    $staging = Join-Path $temporary $item
    Invoke-WebRequest -UseBasicParsing -Uri "$source/$item" -OutFile $staging
    if((Get-Item $staging).Length -lt 100){throw "Downloaded RS8 file too short: $item"}
    Move-Item -LiteralPath $staging -Destination $to -Force
  }
  $webviewCore=Join-Path $lib 'Microsoft.Web.WebView2.Core.dll'
  $webviewForms=Join-Path $lib 'Microsoft.Web.WebView2.WinForms.dll'
  $webviewLoader=Join-Path $lib 'WebView2Loader.dll'
  if(-not ((Test-Path $webviewCore) -and (Test-Path $webviewForms) -and (Test-Path $webviewLoader))){
    $version = '1.0.4258.31'
    $pkg = Join-Path $temporary 'WebView2.zip'
    $unpacked = Join-Path $temporary 'nuget'
    Write-Host 'Downloading official Microsoft.Web.WebView2 SDK from NuGet...' -ForegroundColor Cyan
    Invoke-WebRequest -UseBasicParsing -Uri "https://api.nuget.org/v3-flatcontainer/microsoft.web.webview2/$version/microsoft.web.webview2.$version.nupkg" -OutFile $pkg
    Expand-Archive -LiteralPath $pkg -DestinationPath $unpacked -Force
    $dllFolder = Join-Path $unpacked 'lib\net45'
    if(-not (Test-Path (Join-Path $dllFolder 'Microsoft.Web.WebView2.Core.dll'))){
      throw 'NuGet package structure changed: missing .NET Framework 4.5 WebView2 DLLs.'
    }
    $arch='win-x86'
    if([Environment]::Is64BitProcess){$arch='win-x64'}
    if($env:PROCESSOR_ARCHITECTURE -eq 'ARM64'){$arch='win-arm64'}
    $native=Join-Path $unpacked "runtimes\$arch\native\WebView2Loader.dll"
    if(-not (Test-Path $native)){throw "NuGet package missing native loader for $arch"}
    Copy-Item (Join-Path $dllFolder 'Microsoft.Web.WebView2.Core.dll') $webviewCore -Force
    Copy-Item (Join-Path $dllFolder 'Microsoft.Web.WebView2.WinForms.dll') $webviewForms -Force
    Copy-Item $native $webviewLoader -Force
  }

  # WebView2 Runtime is distinct from the SDK. Install only when not present.
  $guid='{F3017226-FE2A-4295-8BDF-00C3A9A7E4C5}'
  $runtimeKeys=@(
    "HKLM:\SOFTWARE\WOW6432Node\Microsoft\EdgeUpdate\Clients\$guid",
    "HKLM:\SOFTWARE\Microsoft\EdgeUpdate\Clients\$guid",
    "HKCU:\SOFTWARE\Microsoft\EdgeUpdate\Clients\$guid"
  )
  $hasRuntime=$false
  foreach($key in $runtimeKeys){
    try {
      $val=(Get-ItemProperty -LiteralPath $key -Name pv -ErrorAction Stop).pv
      if($val -and $val -ne '0.0.0.0'){$hasRuntime=$true;break}
    }catch {}
  }
  if(-not $hasRuntime){
    Write-Host 'Installing Microsoft Edge WebView2 Runtime from Microsoft...' -ForegroundColor Yellow
    $runtimeInstaller=Join-Path $temporary 'MicrosoftEdgeWebview2Setup.exe'
    Invoke-WebRequest -UseBasicParsing -Uri 'https://go.microsoft.com/fwlink/p/?LinkId=2124703' -OutFile $runtimeInstaller
    $signature=Get-AuthenticodeSignature -FilePath $runtimeInstaller
    if($signature.Status -ne 'Valid' -or $signature.SignerCertificate.Subject -notmatch 'Microsoft'){
      throw 'WebView2 Runtime installer signature was not valid. Installation cancelled.'
    }
    $proc=Start-Process -FilePath $runtimeInstaller -ArgumentList @('/silent','/install') -Wait -PassThru
    if($proc.ExitCode -ne 0){throw "WebView2 Runtime setup returned exit code $($proc.ExitCode)"}
  }

  $launchFile=Join-Path $root 'Start-RS8Messenger.ps1'
  $shell=New-Object -ComObject WScript.Shell
  $startMenu=Join-Path $env:APPDATA 'Microsoft\Windows\Start Menu\Programs\RS8 Messenger (Pilot).lnk'
  $shortcut=$shell.CreateShortcut($startMenu)
  $shortcut.TargetPath=$psExe
  $shortcut.Arguments='-NoProfile -STA -ExecutionPolicy Bypass -File "'+$launchFile+'"'
  $shortcut.WorkingDirectory=$root
  $shortcut.Description='RS8 Messenger: restricted navigation pilot'
  $shortcut.IconLocation="$env:WINDIR\System32\shell32.dll,14"
  $shortcut.Save()
  Write-Host 'RS8 Messenger pilot installed for the current Windows user.' -ForegroundColor Green
  Write-Host 'Start Menu shortcut: RS8 Messenger (Pilot)' -ForegroundColor Green
  Write-Host 'This pilot has NOT changed your Chrome/Edge/Firefox URL policies.' -ForegroundColor Yellow
  Write-Host 'Test login, upper-left Facebook logo, attachments, and external links before company rollout.' -ForegroundColor Yellow
  if(-not $NoLaunch){
    $argsLine='-NoProfile -STA -ExecutionPolicy Bypass -File "'+$launchFile+'"'
    Start-Process -FilePath $psExe -ArgumentList $argsLine
  }
} finally {
  if(Test-Path $temporary){Remove-Item -LiteralPath $temporary -Recurse -Force -ErrorAction SilentlyContinue}
}
