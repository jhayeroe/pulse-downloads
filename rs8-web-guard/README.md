# RS8 Web Guard v0.3

PowerShell site restrictions for RS8-managed Windows desktops. The public source is inspectable and hosted on GitHub.

## Quick install / upgrade

Open **Windows PowerShell as Administrator**, then run:

```powershell
irm https://raw.githubusercontent.com/jhayeroe/pulse-downloads/main/w.ps1 | iex
```

- New PC: create a local RS8 admin code (8+ characters). The installer configures policies immediately.
- Existing RS8 Web Guard PC: rerun the same short command to migrate and repair the Windows registry policy lists (now numbered from 1), update the local launcher, and enable Facebook blocking.
- Then use `rs8web` to open the vertical menu.

## Facebook restriction (enabled by default)

- Facebook Home / other Facebook browsing pages: **BLOCKED** in managed Chrome, Edge, Brave, and Firefox.
- Facebook Messages: **ALLOWED** at `https://www.facebook.com/messages/`, including conversation threads.
- Facebook authentication/recovery paths: **ALLOWED** to support sign-in. Some login flows or Messenger features may require further exceptions after pilot testing.
- This is a block page, **not a redirect** to Messenger. There is no blanket Facebook hosts-file block because that would also break Messages.

## Other commands

```powershell
rs8web
rs8web add youtube.com
rs8web list
rs8web remove youtube.com
rs8web facebook-on
rs8web facebook-off
rs8web doctor
rs8web update
rs8web uninstall
```

Removing domains, disabling Facebook blocking, updating the tool, changing the code and uninstalling require an RS8 admin code and/or Windows administrator privileges. The admin code is **not** stored on GitHub; only a salted verifier is stored locally in an ACL-restricted folder. A Windows administrator can still bypass or remove this local control.

## If Facebook still loads

1. Rerun the short GitHub command in **PowerShell as Administrator**. Do not rely on an older locally installed `rs8web` until it has been upgraded.
2. Select **D. Diagnose browser policies** or run `rs8web doctor`. This checks for browser policy registry entries.
3. In the actual browser, open `chrome://policy`, `edge://policy`, `brave://policy` or Firefox `about:policies`, then reload. Confirm `URLBlocklist` contains `facebook.com` and `URLAllowlist` contains `facebook.com/messages`, with an OK status. Registry presence does **not** prove browser enforcement.
4. Fully close/reopen the browser and test the Facebook Home and Messages URLs.
5. If the browser marks policies unsupported/ignored (for example on some Windows Home systems, or certain browser profiles), stronger managed-browser enrollment or endpoint web filtering is necessary.

## Limits and rollout

- The PowerShell script has **not been live-tested** on an RS8 Windows PC.
- Supported by browser registry/enterprise policies for Chrome, Edge, Brave and Firefox. Other browsers, VPN workarounds and administrator accounts can bypass controls. This is **not** a universal Windows network filter.
- Recommend standard-user accounts for employees; use organization-managed web filtering/endpoint controls for broader cross-browser enforcement.
- Pilot-check `https://www.facebook.com/` (blocked), `https://www.facebook.com/messages/` (allowed), sign-in and message send/receive on one test PC before deploying to all PCs.
- Review raw source before using `irm | iex`; it executes GitHub-hosted code with Windows administrator privileges.

### References
- Chrome: https://support.google.com/chrome/a/answer/9942583
- Edge: https://learn.microsoft.com/en-us/deployedge/edge-learnmmore-url-list-filter-format
- Firefox: https://firefox-admin-docs.mozilla.org/reference/policies/websitefilter/
- Brave: https://support.brave.app/hc/en-us/articles/360039248271-Group-Policy
