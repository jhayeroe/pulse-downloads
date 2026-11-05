# RS8 Web Guard — managed deployment (pre-release)

Goal: one Windows IT deployment; **no Load unpacked** for staff; browsers receive
approved extensions automatically. Policies are kept in HKLM and ordinarily survive
browser uninstall/reinstall while device management remains.

## Current status: not yet deployable

The RS8 browser extension is currently only an unpacked development extension.
Chrome's enterprise force-install policy requires a stable published Chrome Web
Store ID, or signed CRX plus supported AD-managed enterprise hosting. Edge can
use Chrome Web Store IDs or a signed Edge Add-ons package. Firefox requires a
separately signed Mozilla XPI and policy. No IDs have been published for RS8
yet. Accordingly, `extension-ids.json` intentionally has empty IDs and
`Set-RS8ManagedExtensions.ps1` must **not** claim to install an extension.

### Required release process

1. Review extension code and security/privacy permissions (tabs/webNavigation/storage).
2. Publish the Chromium build to Chrome Web Store (company-managed listing if appropriate).
3. Put the approved 32-letter extension ID into `extension-ids.json`.
4. Publish a separate Edge Add-ons build and add its ID. On Windows devices
   not joined to a domain, the Edge force-install policy is limited to packages
   listed in Microsoft Edge Add-ons; do not silently fall back to Chrome Store.
5. Run `Set-RS8ManagedExtensions.ps1` elevated (via the RS8 installer, GPO,
   Intune, or company software deployment). Staff do not use Load unpacked.
6. Test on employee device editions. The status of registry policies alone
   is **not** proof of successful extension installation.
7. Port, sign, and deploy Firefox add-on separately. Validate Brave policy
   support on an RS8 test computer before setting `brave_chrome_store_enabled`.
8. For the literal 'all browsers' requirement, manage an approved-browser list
   and use Windows App Control/MDM to prevent unapproved browsers. Review carefully
   in audit mode before enforcement so other apps are not locked out.

**Never** automate untrusted extension installation or bypass browser warnings,
and do not assume a Windows local administrator cannot remove local policies.

Official references:
- https://support.google.com/chrome/a/answer/7532015
- https://learn.microsoft.com/en-us/deployedge/microsoft-edge-manage-extensions-ref-guide
- https://firefox-admin-docs.mozilla.org/reference/policies/extensionsettings/
- https://learn.microsoft.com/en-us/windows/security/application-security/application-control/app-control-for-business/appcontrol-and-applocker-overview
