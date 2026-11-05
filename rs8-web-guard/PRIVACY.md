# RS8 Messenger Navigation Guard — Privacy Policy

**Version:** Draft for browser-store submission (November 5, 2026)
**Product:** RS8 Messenger Navigation Guard
**Intended publisher:** RS8 Moto Corp (publisher account ownership must be confirmed before store submission)

## Purpose
This browser extension helps administrators of company-owned computers restrict general Facebook browsing while permitting Facebook Messages and selected messaging-related navigation and attachments. It is intended for authorized, managed workplace devices and must be disclosed to affected users under the organization's policies.

## Information accessed
The extension reads the URL and navigation events of Facebook pages so that it can decide whether to keep the page open or return the tab to Facebook Messages. Its scripts may inspect the destination URLs and selected attributes of links clicked within Facebook Messages. The extension does **not** read or transmit the text of chat messages, does not access passwords, and does not record browsing history to an external server.

To support selected photo links opened from Messages, it temporarily stores the clicked Facebook photo URL and expiry time in the browser's **extension session storage**, associated with the opened tab. A URL may contain parameters such as an attachment identifier. These values remain inside the browser and are removed when the tab closes (or when the browser session ends). A photo permission expires after at most **120 seconds**.

## Collection and disclosure
The current published source contains no analytics, telemetry, advertising SDK, remote code execution, or network request to an RS8 or third-party collection endpoint. This extension does not sell or share navigation records, message content, or attachment URLs with the publisher or advertisers. Normal browsing requests to Facebook/Messenger are performed by the browser under those services' own policies.

## Permissions
- **tabs:** Access and update tab URLs so unauthorized Facebook pages can be redirected to Facebook Messages.
- **webNavigation:** Detect changes to Facebook routes, including in-app navigation without reload.
- **storage:** Hold temporary per-tab photo-view permissions in session memory.
- **Host permission `*.facebook.com`:** Limit checks to Facebook pages; the extension does not request access to all websites.

## User and administrator controls
On managed devices, an administrator may deploy the extension using browser enterprise policy. Users who have questions or wish to challenge or request changes to workplace browsing restrictions should contact their organization's IT/HR administrators. On unmanaged browsers, ordinary browser extension controls may allow users to disable it.

## Security, retention and updates
The extension is packaged locally and does not download executable code. Temporary photo permissions expire after 120 seconds and are removed when their tab closes. Changes to behavior and privacy practices require an updated extension and, where appropriate, updated disclosure.

## Contact and governing rights
The organization operating the managed devices is responsible for the workplace notice and applicable employee-privacy obligations. Store publisher contact details and a verified support email must be added and confirmed by the account owner during submission. Until then, treat this as a **draft policy**, not a representation that a store publisher identity has been verified.
