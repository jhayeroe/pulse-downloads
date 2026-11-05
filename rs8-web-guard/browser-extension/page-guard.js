/*
 * RS8 Web Guard v0.7 - page-side SPA protection.
 * Runs in Chromium MAIN world before Facebook scripts.
 * A second isolated content script and a background listener provide fallbacks.
 */
(() => {
  "use strict";
  const MESSAGE_URL = "https://www.facebook.com/messages/";
  const ALLOWED = [
    /^\/messages(?:\/|$)/,
    /^\/login(?:\.php)?(?:\/|$)/,
    /^\/checkpoint(?:\/|$)/,
    /^\/two_factor(?:\/|$)/,
    /^\/recover(?:\/|$)/,
    /^\/confirmemail(?:\/|$)/,
    /^\/connect\/login_success\.html$/,
    /^\/ajax\/login(?:\/|$)/,
    /^\/api\/graphql(?:\/|$)/,
    /^\/l\.php$/,
    /^\/ajax\/messaging\/attachment\.php$/,
    /^\/messaging\/attachment(?:\/|$)/,
    /^\/attachment(?:\/|$)/,
    /^\/download(?:\/|$)/,
    /^\/photo\.php$/,
    /^\/photo\/$/
  ];
  function parse(value) {
    try { return new URL(value, location.href); } catch (_) { return null; }
  }
  function isFacebook(url) {
    return url && /^https?:$/.test(url.protocol) &&
      (url.hostname === "facebook.com" || url.hostname.endsWith(".facebook.com"));
  }
  function permitted(value) {
    const url = parse(value);
    if (!url || !isFacebook(url)) return true;
    return ALLOWED.some((pattern) => pattern.test(url.pathname));
  }
  function onMessages() { return /^\/messages(?:\/|$)/.test(location.pathname); }
  function goToMessages() {
    if (location.href !== MESSAGE_URL) location.replace(MESSAGE_URL);
  }
  function blockedTarget(event) {
    for (const node of event.composedPath()) {
      if (!(node instanceof Element)) continue;
      if (node.matches("a[href]") && !permitted(node.href || node.getAttribute("href"))) return true;
      if (node.matches("button,[role='button'],[role='link']")) {
        const label = (node.getAttribute("aria-label") || node.getAttribute("title") || "").trim();
        if (/^facebook(?: home)?$/i.test(label) &&
          !!node.closest("header,nav,[role='banner'],[role='navigation']")) return true;
      }
    }
    return false;
  }
  for (const eventType of ["pointerdown", "mousedown", "click", "auxclick"]) {
    document.addEventListener(eventType, (event) => {
      if (!onMessages() || !event.isTrusted || !blockedTarget(event)) return;
      event.preventDefault();
      event.stopImmediatePropagation();
    }, true);
  }
  for (const method of ["pushState", "replaceState"]) {
    const original = History.prototype[method];
    if (typeof original !== "function") continue;
    try {
      Object.defineProperty(History.prototype, method, {
        configurable: true,
        writable: true,
        value: function (...args) {
          const url = args.length > 2 ? args[2] : null;
          if (url != null && !permitted(url)) {
            goToMessages();
            return;
          }
          return Reflect.apply(original, this, args);
        }
      });
    } catch (_) { /* Other safeguards still run if browser disallows patching. */ }
  }
})();
