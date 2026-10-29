# RS8 Web Guard (prototype)

PowerShell domain blocker for RS8 Windows desktops. Launch in elevated Windows PowerShell:

```powershell
irm https://raw.githubusercontent.com/jhayeroe/pulse-downloads/main/rs8-web-guard/RS8-WebGuard.ps1 | iex
```

First run creates an RS8 admin code locally, downloads a local copy and installs the `rs8web` command. Commands: `rs8web`, `rs8web add youtube.com`, `rs8web list`, `rs8web remove youtube.com`, `rs8web change-pin`, `rs8web update`, `rs8web uninstall`.

Removing domains, code changes, updates and uninstall require an RS8 code and Windows administrator rights. Code is not uploaded; only a salted password verifier is saved in an ACL-protected ProgramData folder.

**Important limitations:** Whole domains only. Facebook intentionally not blocked because this would break `facebook.com/messages`. Home-only Facebook redirect is not supported. Hosts entries and managed-browser policies improve coverage, but are not universal controls: VPN, encrypted DNS, alternative un-managed browsers and Windows administrator users can evade these restrictions. Standard Windows user accounts and a centrally managed web-filter or gateway are required for stronger enforcement. Pilot on one PC first; this script has not been tested on an RS8 Windows machine.

Inspect source before using `irm | iex` because the downloaded code executes with your Windows privileges.
