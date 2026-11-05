/* RS8 Web Guard v0.7 - event-level SPA navigation guard and photo grants. */
importScripts("rules.js");
const GRANT_PREFIX = "rs8-photo:";
const GRANT_TTL_MS = 120000;
function grantKey(tabId) { return GRANT_PREFIX + tabId; }
function redirect(tabId) {
  chrome.tabs.update(tabId, { url: RS8Rules.MESSAGES }, () => { void chrome.runtime.lastError; });
}
async function granted(tabId, url) {
  const key = grantKey(tabId);
  const obj = await chrome.storage.session.get(key);
  const grant = obj[key];
  return !!grant && grant.url === url && grant.expires > Date.now();
}
function checkNavigation(details) {
  if (details.frameId !== 0 || details.tabId < 0 || !RS8Rules.isFacebookUrl(details.url)) return;
  if (RS8Rules.isAlwaysAllowed(details.url)) return;
  if (RS8Rules.isPhotoUrl(details.url)) {
    granted(details.tabId, details.url).then((yes) => {
      if (!yes) redirect(details.tabId);
    }).catch(() => redirect(details.tabId));
    return;
  }
  redirect(details.tabId);
}
chrome.webNavigation.onHistoryStateUpdated.addListener(checkNavigation);
chrome.webNavigation.onCommitted.addListener(checkNavigation);
chrome.webNavigation.onReferenceFragmentUpdated.addListener(checkNavigation);
chrome.tabs.onUpdated.addListener((tabId, changes) => {
  if (changes.url) checkNavigation({ tabId, frameId: 0, url: changes.url });
});
chrome.tabs.onRemoved.addListener((tabId) => {
  chrome.storage.session.remove(grantKey(tabId)).catch(() => {});
});
chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
  if (request?.type === "VERIFY_PHOTO") {
    if (!sender.tab || !RS8Rules.isPhotoUrl(request.url)) {
      sendResponse({ allowed: false });
    } else {
      granted(sender.tab.id, request.url).then(
        (yes) => sendResponse({ allowed: yes }),
        () => sendResponse({ allowed: false })
      );
      return true;
    }
    return false;
  }
  if (request?.type !== "OPEN_PHOTO") return false;
  if (!sender.tab || !RS8Rules.isMessengerUrl(sender.tab.url) || !RS8Rules.isPhotoUrl(request.url)) {
    sendResponse({ ok: false, reason: "Not an approved Messenger photo request" });
    return false;
  }
  (async () => {
    const tab = await chrome.tabs.create({ url: "about:blank", active: true, openerTabId: sender.tab.id });
    await chrome.storage.session.set({
      [grantKey(tab.id)]: { url: request.url, expires: Date.now() + GRANT_TTL_MS }
    });
    await chrome.tabs.update(tab.id, { url: request.url });
    sendResponse({ ok: true });
  })().catch((error) => sendResponse({ ok: false, reason: String(error) }));
  return true;
});
