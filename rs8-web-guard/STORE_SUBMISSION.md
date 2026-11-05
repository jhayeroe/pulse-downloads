# RS8 Messenger Navigation Guard — Store submission draft

## Extension
- Name: RS8 Messenger Navigation Guard
- Category: Productivity
- Language: English
- Proposed distribution: **Unlisted** (not Private; employees may not share one store account)
- Release build: 0.7.0 candidate, not yet live-tested on an RS8 workstation
- Publisher: RS8 Moto Corp — must be submitted from an account authorized by the company.

## Short description
Restricts Facebook Home and Feed navigation on managed devices while keeping Facebook Messages available.

## Full listing description
RS8 Messenger Navigation Guard supports workplace browser restrictions on company-managed computers. It monitors navigation inside Facebook and redirects unsupported destinations, including Facebook Home and News Feed, back to Facebook Messages. Unlike URL-only browser policies, it also watches in-page navigation that may not reload the tab.

It is designed to work alongside RS8's Windows-managed browser URL policies. It allows the Facebook Messages interface and selected messaging-related links. Supported attachment and external-link workflows require validation in the organization’s environment and may depend on Facebook's current implementation.

The extension is intended for business administration of authorized workplace computers. It does not read chat message text or send browsing logs to a publisher server. It uses local, temporary per-tab storage for photo-link permissions. It does not guarantee enforcement in browsers without the installed extension, against Windows administrators, or across every browser family.

## Single purpose (privacy tab)
Prevent ordinary Facebook browsing on authorized workplace browsers while preserving access to Facebook Messages.

## Permission justifications
- tabs: inspect and update Facebook tab URLs for redirects.
- webNavigation: detect normal and SPA route changes to blocked Facebook pages.
- storage: temporary session-only permissions for selected photo previews.
- *.facebook.com host permission: inspect navigation only on Facebook-controlled pages.

## Data handling summary
No chat content or credentials collected. No analytics or publisher-server uploads. Temporary photo-link URLs are held in browser extension session storage for up to 120 seconds and are removed when their tab closes. The URL may contain an attachment identifier. See PRlVACY.md for details.

## Reviewer notes
1. Install this extension using the signed store package.
2. Sign in to a test Facebook account with access to Messages.
3. From facebook.com/messages/, click the Facebook logo in the upper-left: it should stay in Messages or return to Messages.
4. Paste facebook.com into a new tab: with an RS8 URL policy installed, browser policy blocks the tab.
5. Check that the Messages interface remains usable and test actual message attachment types (image, PDF, external-link redirect).
6. The toolbar popup shows the extension version and local active status.
7. The extension sends no requests to an RS8 server.

## Prerequisites before pressing Publish
- Confirm publishing account's legal owner and complete verification.
- Chrome developer registration and one-time fee if not already complete.
- Microsoft Edge Partner Center registration if submitting Edge package.
- Review privacy policy and set actual company support email.
- Add accurate icons and **genuine screenshots of the running extension**, not mockups.
- Run real acceptance tests for Home click, reload, login, attachments and external links.
- Upload a ZIP whose root contains manifest.json and referenced files.
- Complete permissions / single-purpose / privacy disclosures.
- Submit for store review, record the returned extension IDs, and only then configure managed force-install.
