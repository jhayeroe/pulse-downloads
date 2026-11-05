# RS8 Messenger Restricted Navigation — Windows pilot (PowerShell only)

**Status:** Code-complete pilot; not tested on an actual RS8 Windows PC. Existing RS8 Web Guard remains unchanged.

## Goal

One PowerShell command installs a separate **RS8 Messenger (Pilot)** desktop window using Microsoft's WebView2 Runtime. It does not require a Chrome/Edge extension or store listing. Browser navigation is checked in three layers:

1. Native WebView2 `NavigationStarting` cancels unauthorized full-page loads.
2. Native WebView2 `SourceChanged` plus a 250-ms UI-timer check detect client-side URL changes.
3. A document-start JS guard intercepts the Facebook logo click and blocks History API navigations before changing routes.

The existing Chrome/Edge/Brave/Firefox URL policies are **not modified** by this pilot. Full all-browser enforcement is a separate and unimplemented phase.

## Single-command installation (Windows PowerShell 5.1)

Run as the **employee's normal Windows user** (not Run as different administrator; it installs per-user):

```powershell
irm https://raw.githubusercontent.com/jhayeroe/pulse-downloads/main/rs8-web-guard/messenger-shell/Install-RS8MessengerPilot.ps1 | iex
```

The script installs `%LOCALAPPDATA%\RS8Messenger\`, downloads the pinned Microsoft.Web.WebView2 NuGet SDK 1.0.4258.31 if needed, verifies/install the official WebView2 Runtime if missing, creates a Start Menu shortcut, and launches the pilot window.

No Chrome extension setup or Web Store account is required. There is a **separate WebView2 browser profile**, so an employee may have to log in to Facebook again.

Read/approve the script before organization-wide use; the one-liner downloads and executes code.

## Navigation rules in this pilot

| Destination / action | Expected result |
| --- | --- |
| `facebook.com/messages/` and conversations | Allowed |
| Facebook Home icon, Home, Feed, Reels, groups, profiles | Blocked |
| Normal Facebook login/account verification paths | Allowed |
| Messenger attachment/download endpoints | Allowed |
| Image `photo.php?fbid=...` | Allowed provisionally; verify actual message image handling |
| External URL shared in Messages | Opens the system default browser |
| `l.facebook.com/l.php?u=https... ` redirect | Opens validated non-Facebook destination in the default browser |
| Unsupported schemes / non-user-initiated popups | Blocked |

External destinations are not vetted for malicious content; users should exercise normal security precautions.

## Required manual acceptance tests before rollout

1. Launch from Start Menu; sign in and confirm conversations load.
2. Click the Facebook icon at upper-left while inside Messages — the Home feed must **never render**.
3. Try address navigation to `/reels/`, `/profile`, `/watch`, `/groups`; should be rejected.
4. Test Back and Forward behavior.
5. Test message attachments: PDF, Word, Excel, images, videos, downloads.
6. Test multiple external links including `l.facebook.com/l.php?u=...`.
7. Test Meta login challenges and 2FA.
8. Verify that the normal Chrome/Edge browser still obeys existing policy and that installing this pilot did not change any restriction.

If a required attachment or login flow fails, record the resulting URL **without tokens or personal data**, browser event, and expected behavior for a targeted fix. Do not broadly allow `facebook.com` just to make a file work.

## Uninstall pilot only

Close RS8 Messenger, then run in PowerShell as that same Windows user:

```powershell
Remove-Item "$env:APPDATA\Microsoft\Windows\Start Menu\Programs\RS8 Messenger (Pilot).lnk" -Force -ErrorAction SilentlyContinue
Remove-Item "$env:LOCALAPPDATA\RS8Messenger" -Recurse -Force
```

That will delete the pilot's separate browser profile, saved sign-in cookies, and cached data. **It will not uninstall RS8 Web Guard or modify its existing policies.**

## Requirements / limitations

- Windows 10/11, Windows PowerShell 5.1, WebView2 Evergreen Runtime.
- This is not a signed, compiled production application. It does not assert that its controls defeat an employee with local administrator rights.
- The page-level JS guard is defense-in-depth, not a replacement for host navigation events.
- Meta may change Messenger URLs and attachment workflows; revalidate after changes.
- A true “no Facebook in any browser” requirement also needs enforceable browser/application management. Windows hosts/IP blocking is not a solution because the Facebook and Messenger services share infrastructure.
- DO NOT delete existing `URLAllowlist` rules globally or block the Facebook domain in hosts before testing the Messenger shell on the user's real PC.
