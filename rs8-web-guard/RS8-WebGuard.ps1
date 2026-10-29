# RS8 Web Guard v0.3 - fix URL policy numbering
# Public installer; admin code is created locally and never stored in GitHub.
# Domain blocking only. Facebook is deliberately excluded to preserve Messenger.
[CmdletBinding()]
param(
  [Parameter(Position=0)]
  [ValidateSet('menu','add','remove','list','reapply','change-pin','update','uninstall','facebook-on','facebook-off','help')]
  [string]$Action = 'menu',
  [Parameter(Position=1)][string]$Domain = ''
)
$ErrorActionPreference='Stop'
$root=Join-Path $env:ProgramData 'RS8WebGuard'
$store=Join-Path $root 'settings.json'
$local=Join-Path $root 'RS8-WebGuard.ps1'
$launcher=Join-Path $env:WINDIR 'System32\rs8web.cmd'
$hosts=Join-Path $env:WINDIR 'System32\drivers\etc\hosts'
$source='https://raw.githubusercontent.com/jhayeroe/pulse-downloads/main/rs8-web-guard/RS8-WebGuard.ps1'
$begin='# BEGIN RS8 WEB GUARD MANAGED'
$end='# END RS8 WEB GUARD MANAGED'
$state=$null

function Admin {
  $p=New-Object Security.Principal.WindowsPrincipal([Security.Principal.WindowsIdentity]::GetCurrent())
  return $p.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}
function NeedAdmin {
  if(-not (Admin)){throw 'Run PowerShell as administrator. Windows permission is separate from the RS8 code.'}
}
function Secret([string]$label) {
  $s=Read-Host $label -AsSecureString
  $p=[Runtime.InteropServices.Marshal]::SecureStringToBSTR($s)
  try {return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($p)}
  finally {[Runtime.InteropServices.Marshal]::ZeroFreeBSTR($p);$s.Dispose()}
}
function Digest([string]$pin,[string]$salt) {
  $bytes=[Text.Encoding]::UTF8.GetBytes($pin)
  $k=[Security.Cryptography.Rfc2898DeriveBytes]::new($bytes,[Convert]::FromBase64String($salt),150000,[Security.Cryptography.HashAlgorithmName]::SHA256)
  try {return [Convert]::ToBase64String($k.GetBytes(32))}
  finally {$k.Dispose();[Array]::Clear($bytes,0,$bytes.Length)}
}
function SetPin {
  for($i=0;$i -lt 3;$i++){
    $a=Secret 'Create RS8 admin code (8+ characters)'
    $b=Secret 'Confirm code'
    if($a.Length -ge 8 -and $a -ceq $b){
      $salt=New-Object byte[] 16
      $rng=[Security.Cryptography.RandomNumberGenerator]::Create()
      try {$rng.GetBytes($salt)} finally {$rng.Dispose()}
      $script:state.Salt=[Convert]::ToBase64String($salt)
      $script:state.Hash=Digest $a $script:state.Salt
      return
    }
    Write-Host 'Code too short or did not match.' -ForegroundColor Yellow
  }
  throw 'Could not create admin code.'
}
function VerifyPin {
  for($i=0;$i -lt 3;$i++){
    $a=Secret 'RS8 admin code'
    $actual=[Convert]::FromBase64String((Digest $a $script:state.Salt))
    $expected=[Convert]::FromBase64String($script:state.Hash)
    $diff=0
    if($actual.Length -ne $expected.Length){$diff=1}
    else{for($j=0;$j -lt $actual.Length;$j++){$diff=$diff -bor ($actual[$j] -bxor $expected[$j])}}
    if($diff -eq 0){return}
    Write-Host 'Wrong code.' -ForegroundColor Yellow
  }
  throw 'Admin code check failed.'
}
function SaveState {
  $data=$script:state | ConvertTo-Json -Depth 10
  [IO.File]::WriteAllText($store,$data,[Text.UTF8Encoding]::new($false))
}
function DownloadLocal {
  NeedAdmin
  $tmp=Join-Path $root ([Guid]::NewGuid().ToString('N')+'.ps1')
  try {
    Invoke-WebRequest -UseBasicParsing -Uri $source -OutFile $tmp
    if((Get-Item $tmp).Length -lt 500){throw 'Download was unexpectedly short.'}
    Move-Item -LiteralPath $tmp -Destination $local -Force
  } finally {if(Test-Path $tmp){Remove-Item -LiteralPath $tmp -Force}}
  if(Test-Path $launcher){
    $previous=Get-Content $launcher -Raw
    if($previous -notmatch 'RS8 WEB GUARD LAUNCHER'){throw 'Existing rs8web.cmd is not owned by this tool.'}
  }
  $nl=[Environment]::NewLine
  $wrapper='@echo off'+$nl+'REM RS8 WEB GUARD LAUNCHER'+$nl+'powershell.exe -NoProfile -ExecutionPolicy Bypass -File "'+$local+'" %*'+$nl
  [IO.File]::WriteAllText($launcher,$wrapper,[Text.Encoding]::ASCII)
}
function Init {
  if(Test-Path $store){
    $script:state=Get-Content $store -Raw -Encoding UTF8 | ConvertFrom-Json
    $changed=$false
    if(-not $script:state.PSObject.Properties['SchemaVersion']){
      $script:state | Add-Member -NotePropertyName SchemaVersion -NotePropertyValue 3
      $changed=$true
    }elseif([int]$script:state.SchemaVersion -lt 3){
      $script:state.SchemaVersion=3
      $changed=$true
    }
    if(-not $script:state.PSObject.Properties['FacebookEnabled']){
      $script:state | Add-Member -NotePropertyName FacebookEnabled -NotePropertyValue $true
      $changed=$true
    }
    foreach($field in @('EdgeAllow','ChromeAllow','BraveAllow','FirefoxAllow')){
      if(-not $script:state.PSObject.Properties[$field]){
        $script:state | Add-Member -NotePropertyName $field -NotePropertyValue @()
        $changed=$true
      }
    }
    if($changed){
      NeedAdmin
      SaveState
      DownloadLocal
      ApplyAll
      Write-Host 'Upgraded existing setup: Facebook browsing is now blocked, with Messenger and sign-in exceptions.' -ForegroundColor Green
    }
    return
  }
  NeedAdmin
  New-Item -ItemType Directory -Path $root -Force | Out-Null
  & icacls.exe $root '/inheritance:r' '/grant:r' '*S-1-5-18:(OI)(CI)F' '*S-1-5-32-544:(OI)(CI)F' | Out-Null
  if($LASTEXITCODE -ne 0){throw 'Could not secure settings folder.'}
  $script:state=[pscustomobject]@{
    SchemaVersion=3;Salt='';Hash='';Domains=@();FacebookEnabled=$true
    Edge=@();Chrome=@();Brave=@();Firefox=@()
    EdgeAllow=@();ChromeAllow=@();BraveAllow=@();FirefoxAllow=@()
  }
  Write-Host 'RS8 Web Guard - first-time setup' -ForegroundColor Cyan
  SetPin
  SaveState
  DownloadLocal
  ApplyAll
  Write-Host 'Installed. Facebook Home BLOCKED; Facebook Messages ALLOWED.' -ForegroundColor Green
  Write-Host 'Run rs8web to open the menu.' -ForegroundColor Green
}
function CleanDomain([string]$text) {
  $v=$text.Trim().ToLowerInvariant()
  if($v -match '^https?://'){
    $u=[Uri]$v
    if($u.AbsolutePath -ne '/' -or $u.Query -or $u.Fragment){throw 'Only whole domains are supported, not a URL path.'}
    $v=$u.DnsSafeHost.ToLowerInvariant()
  }
  if($v.StartsWith('www.')){$v=$v.Substring(4)}
  if($v -notmatch '^([a-z0-9-]+\.)+[a-z]{2,63}$'){throw 'Enter a domain such as youtube.com'}
  if($v -eq 'facebook.com' -or $v -like '*.facebook.com' -or $v -eq 'messenger.com'){
    throw 'Facebook is managed separately (blocked by default, Messages allowed). Use Facebook on/off menu commands.'
  }
  return $v
}
function Effective {
  $x=@($script:state.Domains)
  if('youtube.com' -in $x){$x+=@('youtu.be','youtube-nocookie.com')}
  return @($x | Sort-Object -Unique)
}
function ApplyHosts {
  NeedAdmin
  $sr=[IO.StreamReader]::new($hosts,$true)
  try{$old=$sr.ReadToEnd();$enc=$sr.CurrentEncoding}finally{$sr.Dispose()}
  $old=[regex]::Replace($old,'(?ms)^# BEGIN RS8 WEB GUARD MANAGED\r?\n.*?^# END RS8 WEB GUARD MANAGED\r?\n?','')
  $old=[regex]::Replace($old,'(\r?\n)+$','')
  $lines=New-Object 'System.Collections.Generic.List[string]'
  foreach($d in @(Effective)){
    foreach($h in @($d,"www.$d","m.$d","music.$d")){
      if($h -notin $lines){
        $lines.Add("0.0.0.0 $h")
        $lines.Add("::1 $h")
      }
    }
  }
  if($lines.Count -gt 0){
    $all=@($begin)+@($lines.ToArray())+@($end)
    $old+=[Environment]::NewLine+[Environment]::NewLine+($all -join [Environment]::NewLine)+[Environment]::NewLine
  }else{$old+=[Environment]::NewLine}
  [IO.File]::WriteAllText($hosts,$old,$enc)
  & ipconfig.exe /flushdns | Out-Null
}
function ApplyPolicy([string]$path,[string]$field,[string[]]$patterns) {
  NeedAdmin
  if(-not (Test-Path $path)){New-Item -Path $path -Force | Out-Null}
  $prop=$script:state.PSObject.Properties[$field]
  foreach($entry in @($prop.Value)){
    if($null -eq $entry){continue}
    $existing=Get-ItemPropertyValue -LiteralPath $path -Name ([string]$entry.Index) -ErrorAction SilentlyContinue
    if($existing -eq [string]$entry.Pattern){Remove-ItemProperty -LiteralPath $path -Name ([string]$entry.Index)}
  }
  $new=@()
  foreach($pattern in @($patterns)){
    $used=@((Get-ItemProperty -LiteralPath $path).PSObject.Properties.Name | Where-Object {$_ -match '^\d+$'})
    $slot=$null
    for($i=1;$i -le 1000;$i++){if([string]$i -notin $used){$slot=[string]$i;break}}
    if($null -eq $slot){throw 'Registry policy list slots 1-1000 are full.'}
    New-ItemProperty -Path $path -Name $slot -Value $pattern -PropertyType String -Force | Out-Null
    $new+= [pscustomobject]@{Index=$slot;Pattern=$pattern}
  }
  $prop.Value=@($new)
  SaveState
}
function ApplyAll {
  NeedAdmin
  ApplyHosts
  $sites=@(Effective)
  $blocks=@($sites)
  $allows=@()
  if($script:state.FacebookEnabled){
    # Chromium enterprise URL policy: block Facebook as a site, allow Messenger & authentication paths.
    # These policies are URL-based; a plain hosts-file Facebook block would also block Messages.
    $blocks+=@('facebook.com')
    $allows+=@(
      'facebook.com/messages',
      'facebook.com/login',
      'facebook.com/checkpoint',
      'facebook.com/two_factor',
      'facebook.com/recover',
      'facebook.com/confirmemail'
    )
  }
  ApplyPolicy 'HKLM:\SOFTWARE\Policies\Microsoft\Edge\URLBlocklist' 'Edge' $blocks
  ApplyPolicy 'HKLM:\SOFTWARE\Policies\Microsoft\Edge\URLAllowlist' 'EdgeAllow' $allows
  ApplyPolicy 'HKLM:\SOFTWARE\Policies\Google\Chrome\URLBlocklist' 'Chrome' $blocks
  ApplyPolicy 'HKLM:\SOFTWARE\Policies\Google\Chrome\URLAllowlist' 'ChromeAllow' $allows
  ApplyPolicy 'HKLM:\SOFTWARE\Policies\BraveSoftware\Brave\URLBlocklist' 'Brave' $blocks
  ApplyPolicy 'HKLM:\SOFTWARE\Policies\BraveSoftware\Brave\URLAllowlist' 'BraveAllow' $allows
  $ff=@($sites | ForEach-Object {"*://*.$_/*"})
  $ffAllow=@()
  if($script:state.FacebookEnabled){
    $ff+=@('*://*.facebook.com/*')
    $ffAllow+=@(
      '*://*.facebook.com/messages*',
      '*://*.facebook.com/login*',
      '*://*.facebook.com/checkpoint*',
      '*://*.facebook.com/two_factor*',
      '*://*.facebook.com/recover*',
      '*://*.facebook.com/confirmemail*'
    )
  }
  ApplyPolicy 'HKLM:\SOFTWARE\Policies\Mozilla\Firefox\WebsiteFilter\Block' 'Firefox' $ff
  ApplyPolicy 'HKLM:\SOFTWARE\Policies\Mozilla\Firefox\WebsiteFilter\Exceptions' 'FirefoxAllow' $ffAllow
  Write-Host 'Policies updated. Close and reopen browsers to verify.' -ForegroundColor Green
}
function FacebookSwitch([bool]$enable) {
  NeedAdmin
  if([bool]$script:state.FacebookEnabled -eq $enable){
    if($enable){Write-Host 'Facebook browsing is already blocked.'}
    else{Write-Host 'Facebook restriction is already disabled.'}
    return
  }
  if(-not $enable){VerifyPin}
  $script:state.FacebookEnabled=$enable
  SaveState
  ApplyAll
}
function ListSites {
  Write-Host ''
  if($script:state.FacebookEnabled){
    Write-Host 'Facebook Home / News Feed / other Facebook browsing: BLOCKED' -ForegroundColor Yellow
    Write-Host 'Messenger: ALLOWED at https://www.facebook.com/messages/' -ForegroundColor Green
    Write-Host 'Facebook login / account verification: ALLOWED (to support messaging)'
  }else{
    Write-Host 'Facebook restriction: OFF' -ForegroundColor Yellow
  }
  Write-Host ''
  Write-Host 'Other blocked domains:' -ForegroundColor Cyan
  if(@($script:state.Domains).Count -eq 0){Write-Host '  None'}
  foreach($d in @($script:state.Domains)){Write-Host "  $d"}
  Write-Host 'Supported browser policies: Edge, Chrome, Brave, Firefox'
  Write-Host 'Note: Other browsers / Windows administrators may bypass local policies.'
}
function AddSite([string]$name) {
  NeedAdmin
  if(-not $name){$name=Read-Host 'Domain to block'}
  $d=CleanDomain $name
  if($d -in @($script:state.Domains)){Write-Host 'Already blocked.';return}
  $script:state.Domains=@($script:state.Domains)+@($d)
  SaveState
  ApplyAll
}
function RemoveSite([string]$name) {
  NeedAdmin
  if(-not $name){$name=Read-Host 'Domain to unblock'}
  $d=CleanDomain $name
  if($d -notin @($script:state.Domains)){Write-Host 'Not in blocklist.';return}
  VerifyPin
  $script:state.Domains=@($script:state.Domains | Where-Object {$_ -ne $d})
  SaveState
  ApplyAll
}
function Uninstall {
  NeedAdmin
  VerifyPin
  if((Read-Host 'Type REMOVE to uninstall') -cne 'REMOVE'){return}
  $script:state.Domains=@()
  $script:state.FacebookEnabled=$false
  SaveState
  ApplyAll
  if(Test-Path $launcher){
    if((Get-Content $launcher -Raw) -match 'RS8 WEB GUARD LAUNCHER'){Remove-Item $launcher -Force}
  }
  Remove-Item $root -Recurse -Force
  Write-Host 'RS8 Web Guard removed.' -ForegroundColor Green
}
function Menu {
  while($true){
    Write-Host ''
    Write-Host '===================================' -ForegroundColor DarkCyan
    Write-Host '          RS8 WEB GUARD' -ForegroundColor Cyan
    Write-Host '===================================' -ForegroundColor DarkCyan
    if($script:state.FacebookEnabled){
      Write-Host 'Facebook browsing: BLOCKED' -ForegroundColor Yellow
      Write-Host 'Facebook Messages: ALLOWED' -ForegroundColor Green
    }else{
      Write-Host 'Facebook browsing restriction: OFF' -ForegroundColor Yellow
    }
    Write-Host ''
    Write-Host '  1. Block a new website'
    Write-Host '  2. Unblock website (admin code)'
    Write-Host '  3. View blocked websites'
    Write-Host '  4. Enable Facebook restriction'
    Write-Host '  5. Disable Facebook restriction (admin code)'
    Write-Host '  6. Reapply website policies'
    Write-Host '  7. Change admin code'
    Write-Host '  8. Update from GitHub'
    Write-Host '  9. Uninstall (admin code)'
    Write-Host '  0. Exit'
    Write-Host ''
    $answer=Read-Host 'Select option'
    try{
      switch($answer){
        '1'{AddSite ''}
        '2'{RemoveSite ''}
        '3'{ListSites}
        '4'{FacebookSwitch $true}
        '5'{FacebookSwitch $false}
        '6'{ApplyAll}
        '7'{NeedAdmin;VerifyPin;SetPin;SaveState}
        '8'{NeedAdmin;VerifyPin;DownloadLocal;ApplyAll;Write-Host 'Update downloaded. Reopen rs8web for the latest menu.'}
        '9'{Uninstall;return}
        '0'{return}
        default{Write-Host 'Invalid option.'}
      }
    }catch{Write-Host $_.Exception.Message -ForegroundColor Red}
  }
}
try{
  if($Action -eq 'help'){
    Write-Host 'rs8web | rs8web add youtube.com | rs8web list | rs8web remove youtube.com | rs8web uninstall'
    return
  }
  Init
  switch($Action){
    'menu'{Menu}
    'add'{AddSite $Domain}
    'remove'{RemoveSite $Domain}
    'list'{ListSites}
    'reapply'{ApplyAll}
    'change-pin'{NeedAdmin;VerifyPin;SetPin;SaveState}
    'update'{NeedAdmin;VerifyPin;DownloadLocal}
    'uninstall'{Uninstall}
    'facebook-on'{FacebookSwitch $true}
    'facebook-off'{FacebookSwitch $false}
  }
}catch{Write-Host ('ERROR: '+$_.Exception.Message) -ForegroundColor Red;exit 1}
