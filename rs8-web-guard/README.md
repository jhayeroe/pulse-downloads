# RS8 Web Guard v0.2

PowerShell site restrictions for RS8-managed Windows desktops. The public source is inspectable and hosted on GitHub.

## Quick install / upgrade

Open **Windows PowerShell as Administrator**, then run:

```powershell
irm https://raw.githubusercontent.com/jhayeroe/pulse-downloads/main/w.ps1 | iex
```

- New PC: create a local RS8 admin code (8+ characters). The installer configures policies immediately.
- Existing v0.1 PC: the same command migrates existing settings, automatically updates the local launcher, and enables Facebook restriction.
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
rs8web update
rs8web uninstall
```

Removing domains, disabling Facebook blocking, updating the tool, changing the code and uninstalling require an RS8 admin code and/or Windows administrator privileges. The admin code is **not** stored on GitHub; only a salted verifier is stored locally in an ACL-restricted folder. A Windows administrator can still bypass or remove this local control.

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
