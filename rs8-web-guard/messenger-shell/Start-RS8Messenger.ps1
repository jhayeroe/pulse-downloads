# RS8 Messenger Shell pilot (PowerShell 5.1 / Windows with WebView2)
# Isolated from existing RS8 Web Guard. Does not alter Windows/browser policies.
[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'

if ([Threading.Thread]::CurrentThread.GetApartmentState() -ne 'STA') {
  throw 'This window requires STA. Start with powershell.exe -STA -File Start-RS8Messenger.ps1'
}
$homeUrl = 'https://www.facebook.com/messages/'
$script:HomeUrl = $homeUrl
$script:Core = $null
$script:InjectionTask = $null
$script:InjectionStarted = $false
$script:WebReady = $false
$script:Redirecting = $false
$script:LastRejected = $null

$base = Split-Path -Parent $MyInvocation.MyCommand.Path
$coreDll = Join-Path $base 'lib\Microsoft.Web.WebView2.Core.dll'
$formsDll = Join-Path $base 'lib\Microsoft.Web.WebView2.WinForms.dll'
$guardPath = Join-Path $base 'navigation-guard.js'
foreach($required in @($coreDll,$formsDll,$guardPath)){
  if(-not (Test-Path -LiteralPath $required)){throw "Missing RS8 Messenger component: $required"}
}
$env:PATH = (Join-Path $base 'lib') + [IO.Path]::PathSeparator + $env:PATH
$env:WEBVIEW2_USER_DATA_FOLDER = Join-Path $env:LOCALAPPDATA 'RS8Messenger\Profile'
New-Item -ItemType Directory -Path $env:WEBVIEW2_USER_DATA_FOLDER -Force | Out-Null
Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing
Add-Type -AssemblyName System.Web
Add-Type -Path $coreDll
Add-Type -Path $formsDll

function Get-SafeUri([string]$candidate) {
  $uri = $null
  if([Uri]::TryCreate($candidate,[UriKind]::Absolute,[ref]$uri)){return $uri}
  return $null
}
function Test-FacebookHost([Uri]$uri) {
  if($null -eq $uri){return $false}
  $hostName = $uri.DnsSafeHost.ToLowerInvariant()
  return ($hostName -eq 'facebook.com' -or $hostName.EndsWith('.facebook.com'))
}
function Test-RS8Allowed([string]$value) {
  $uri = Get-SafeUri $value
  if($null -eq $uri){return $false}
  if($uri.Scheme -eq 'about' -and $uri.OriginalString -eq 'about:blank'){return $true}
  if($uri.Scheme -eq 'blob'){
    return $uri.OriginalString.StartsWith('blob:https://www.facebook.com/',[StringComparison]::OrdinalIgnoreCase)
  }
  if($uri.Scheme -notin @('https','http')){return $false}
  if(-not (Test-FacebookHost $uri)){return $false}
  $path = $uri.AbsolutePath
  if($path -eq '/photo.php'){
    return $uri.Query -match '(^|[?&])fbid='
  }
  foreach($pattern in @(
    '^/messages(?:/|$)',
    '^/login(?:\.php)?(?:/|$)',
    '^/checkpoint(?:/|$)',
    '^/two_factor(?:/|$)',
    '^/recover(?:/|$)',
    '^/confirmemail(?:/|$)',
    '^/connect/login_success\.html$',
    '^/ajax/login(?:/|$)',
    '^/api/graphql(?:/|$)',
    '^/messaging/attachment(?:/|$)',
    '^/ajax/messaging/attachment\.php$',
    '^/attachment(?:/|$)',
    '^/download(?:/|$)'
  )){
    if($path -match $pattern){return $true}
  }
  return $false
}
function Open-Outside([string]$value) {
  $uri = Get-SafeUri $value
  if($null -eq $uri){return $false}
  # Facebook's outbound link wrapper can carry the real external URL in ?u=...
  if((Test-FacebookHost $uri) -and $uri.AbsolutePath -eq '/l.php'){
    $target = [Web.HttpUtility]::ParseQueryString($uri.Query).Get('u')
    $uri = Get-SafeUri ([string]$target)
  }
  if($null -eq $uri){return $false}
  if($uri.Scheme -notin @('https','http')){return $false}
  if(Test-FacebookHost $uri){return $false}
  try {
    Start-Process -FilePath $uri.AbsoluteUri
    return $true
  } catch {
    return $false
  }
}

$form = New-Object Windows.Forms.Form
$form.Text = 'RS8 Messenger - Restricted Navigation (Pilot)'
$form.Width = 1180
$form.Height = 820
$form.MinimumSize = New-Object Drawing.Size(640,480)
$form.StartPosition = 'CenterScreen'

$bar = New-Object Windows.Forms.Panel
$bar.Dock = 'Top'
$bar.Height = 43
$bar.BackColor = [Drawing.Color]::FromArgb(20,36,57)
$form.Controls.Add($bar)

$btn = New-Object Windows.Forms.Button
$btn.Text = 'Messages Home'
$btn.Width = 130
$btn.Height = 30
$btn.Left = 8
$btn.Top = 6
$bar.Controls.Add($btn)

$status = New-Object Windows.Forms.Label
$status.ForeColor = [Drawing.Color]::White
$status.Text = 'RS8: Messenger allowed | Facebook Home blocked'
$status.AutoSize = $true
$status.Left = 151
$status.Top = 14
$bar.Controls.Add($status)

$script:Browser = New-Object Microsoft.Web.WebView2.WinForms.WebView2
$script:Browser.Dock = 'Fill'
$form.Controls.Add($script:Browser)
$script:Browser.BringToFront()
$bar.BringToFront()

$btn.Add_Click({
  if($script:Core -ne $null){$script:Core.Navigate($script:HomeUrl)}
})

$script:Browser.add_CoreWebView2InitializationCompleted({
  param($sender,$eventArgs)
  if(-not $eventArgs.IsSuccess){
    [Windows.Forms.MessageBox]::Show(
      ('Could not start Microsoft WebView2: ' + [string]$eventArgs.InitializationException),
      'RS8 Messenger Pilot') | Out-Null
    return
  }
  $script:Core = $sender.CoreWebView2
  $script:Core.Settings.AreDevToolsEnabled = $false
  $script:Core.Settings.AreHostObjectsAllowed = $false

  $script:Core.add_NavigationStarting({
    param($core,$args)
    try {
      if(Test-RS8Allowed $args.Uri){return}
      $args.Cancel = $true
      $script:LastRejected = $args.Uri
      if($args.IsUserInitiated -and (Open-Outside $args.Uri)){
        $status.Text = 'External link opened in default browser'
      }else{
        $status.Text = 'Facebook browsing blocked; Messages allowed'
      }
    } catch {
      $args.Cancel = $true
      $status.Text = 'Blocked unsafe or unsupported navigation'
    }
  })

  $script:Core.add_SourceChanged({
    param($core,$args)
    try {
      $current = [string]$core.Source
      if(-not (Test-RS8Allowed $current)){
        if(-not $script:Redirecting){
          $script:Redirecting = $true
          $core.Navigate($script:HomeUrl)
        }
      }else{
        $script:Redirecting = $false
      }
    } catch {
      $status.Text = 'Navigation validation error'
    }
  })

  $script:Core.add_NewWindowRequested({
    param($core,$args)
    $args.Handled = $true
    if(-not $args.IsUserInitiated){return}
    try {
      if(Test-RS8Allowed $args.Uri){
        $core.Navigate($args.Uri)
      }elseif(Open-Outside $args.Uri){
        $status.Text = 'External link opened'
      }else{
        $status.Text = 'Facebook browsing blocked'
      }
    } catch {
      $status.Text = 'Blocked unsupported popup'
    }
  })

  $script:Core.add_NavigationCompleted({
    param($core,$args)
    if(-not $args.IsSuccess){
      $status.Text = 'Messenger navigation needs attention (check login or connectivity)'
    }elseif(Test-RS8Allowed ([string]$core.Source)){
      $status.Text = 'Messenger ready | Facebook Home restricted'
    }
  })

  try {
    $guardJs = [IO.File]::ReadAllText($guardPath,[Text.Encoding]::UTF8)
    $script:InjectionTask = $script:Core.AddScriptToExecuteOnDocumentCreatedAsync($guardJs)
    $script:InjectionStarted = $true
  } catch {
    $status.Text = 'Guard setup error; Messenger was not opened'
    [Windows.Forms.MessageBox]::Show($_.Exception.Message,'RS8 Messenger Pilot') | Out-Null
  }
})

$timer = New-Object Windows.Forms.Timer
$timer.Interval = 250
$timer.Add_Tick({
  if($script:Core -eq $null){return}
  if($script:InjectionStarted -and -not $script:WebReady){
    if(-not $script:InjectionTask.IsCompleted){return}
    if($script:InjectionTask.IsFaulted){
      $script:InjectionStarted = $false
      $status.Text = 'Navigation guard failed; app stopped'
      [Windows.Forms.MessageBox]::Show([string]$script:InjectionTask.Exception,'RS8 Messenger Pilot') | Out-Null
      return
    }
    $script:WebReady = $true
    $script:Core.Navigate($script:HomeUrl)
    return
  }
  if(-not $script:WebReady){return}
  try {
    $current = [string]$script:Core.Source
    if($current -and -not (Test-RS8Allowed $current)){
      if(-not $script:Redirecting){
        $script:Redirecting = $true
        $script:Core.Navigate($script:HomeUrl)
      }
    }else{
      $script:Redirecting = $false
    }
  } catch {
    $status.Text = 'Navigation check error'
  }
})
$form.add_Shown({
  try {
    $null = $script:Browser.EnsureCoreWebView2Async()
    $timer.Start()
  } catch {
    [Windows.Forms.MessageBox]::Show($_.Exception.Message,'RS8 Messenger Pilot') | Out-Null
  }
})
$form.add_FormClosed({$timer.Stop();$timer.Dispose()})
[Windows.Forms.Application]::EnableVisualStyles()
[Windows.Forms.Application]::Run($form)
