/* RS8 Web Guard v0.7 - content-side fallback for SPA navigation and Messenger files. */
(() => {
  "use strict";
  const VERSION = "0.7.0";
  let lastChecked = "";
  function showHint() {
    const id = "rs8-web-guard-notice";
    if (document.getElementById(id)) return;
    const notice = document.createElement("div");
    notice.id = id;
    notice.textContent = "RS8 Web Guard: Facebook Home is restricted; Messenger remains available.";
    Object.assign(notice.style, {
      position: "fixed", bottom: "18px", right: "18px", zIndex: "2147483647",
      padding: "10px 14px", background: "#182637", color: "#fff",
      borderRadius: "8px", font: "13px system-ui, sans-serif", boxShadow: "0 3px 14px #0005"
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
  function isDisallowedControl(event) {
    for (const node of event.composedPath()) {
      if (!(node instanceof Element)) continue;
      if (node.matches("a[href]")) {
        const href = node.href || node.getAttribute("href");
        if (RS8Rules.isFacebookUrl(href) && !RS8Rules.isAlwaysAllowed(href)) {
          return { blocked: true, photo: RS8Rules.isPhotoUrl(href), href };
        }
      }
      if (node.matches("button,[role='button'],[role='link']")) {
        const label = (node.getAttribute("aria-label") || node.getAttribute("title") || "").trim();
        const inNavbar = !!node.closest("header,nav,[role='banner'],[role='navigation']");
        if (/^facebook(?: home)?$/i.test(label) && inNavbar) {
          return { blocked: true, photo: false };
        }
      }
    }
    return { blocked: false, photo: false };
  }
  function intercept(event) {
    if (!RS8Rules.isMessengerUrl(location.href) || !event.target || !event.isTrusted) return;
    const control = isDisallowedControl(event);
    if (!control.blocked) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    if (control.photo && event.type === "click") {
      chrome.runtime.sendMessage({ type: "OPEN_PHOTO", url: control.href }, (response) => {
        if (chrome.runtime.lastError || !response?.ok) showHint();
      });
    } else if (event.type === "click") showHint();
  }
  for (const type of ["pointerdown", "mousedown", "click", "auxclick"]) {
    document.addEventListener(type, intercept, true);
  }
  window.addEventListener("popstate", inspect);
  window.addEventListener("hashchange", inspect);
  window.addEventListener("pageshow", inspect);
  setInterval(inspect, 250);
  if (document.documentElement) document.documentElement.setAttribute("data-rs8-guard-version", VERSION);
  inspect();
})();
