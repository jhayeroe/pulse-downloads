/* RS8 Web Guard v0.6 - intercept navigation from Messenger. */
(() => {
  "use strict";
  let lastChecked = "";
  function showHint() {
    const id = "rs8-web-guard-notice";
    if (document.getElementById(id)) return;
    const notice = document.createElement("div");
    notice.id = id;
    notice.textContent = "RS8 Web Guard: Facebook browsing is restricted. Messenger remains available.";
    Object.assign(notice.style, {
      position: "fixed", bottom: "18px", right: "18px", zIndex: "2147483647",
      padding: "10px 14px", background: "#182637", color: "#fff",
      borderRadius: "8px", font: "13px system-ui, sans-serif",
      boxShadow: "0 3px 14px #0005"
    });
    const append = () => {
      if (!document.getElementById(id)) document.documentElement.appendChild(notice);
      setTimeout(() => { if (notice.isConnected) notice.remove(); }, 3200);
    };
    if (document.documentElement) append();
    else document.addEventListener("DOMContentLoaded", append, { once: true });
  }
  function returnToMessages() {
    if (location.href !== RS8Rules.MESSAGES) location.replace(RS8Rules.MESSAGES);
  }
  function inspect() {
    const current = location.href;
    if (current === lastChecked) return;
    lastChecked = current;
    if (RS8Rules.isAlwaysAllowed(current)) return;
    if (RS8Rules.isPhotoUrl(current)) {
      chrome.runtime.sendMessage({ type: "VERIFY_PHOTO", url: current }, (response) => {
        if (chrome.runtime.lastError || !response?.allowed) returnToMessages();
      });
      return;
    }
    returnToMessages();
  }
  window.addEventListener("click", (event) => {
    if (!RS8Rules.isMessengerUrl(location.href) || !event.target) return;
    const el = event.target instanceof Element ? event.target : event.target.parentElement;
    const anchor = el?.closest?.("a[href]");
    if (!anchor) return;
    const href = anchor.href;
    if (!RS8Rules.isFacebookUrl(href)) return; // External websites and CDN files are unaffected.
    if (RS8Rules.isPhotoUrl(href)) {
      event.preventDefault();
      event.stopImmediatePropagation();
      chrome.runtime.sendMessage({ type: "OPEN_PHOTO", url: href }, (response) => {
        if (chrome.runtime.lastError || !response?.ok) showHint();
      });
      return;
    }
    if (!RS8Rules.isAlwaysAllowed(href)) {
      event.preventDefault();
      event.stopImmediatePropagation();
      showHint();
    }
  }, true);
  window.addEventListener("popstate", inspect);
  window.addEventListener("hashchange", inspect);
  window.addEventListener("pageshow", inspect);
  setInterval(inspect, 350); // Backup for SPA navigations that have no standard event.
  inspect();
})();
