/* RS8 Messenger Shell pilot - defense-in-depth SPA navigation guard.
   The host WebView2 NavigationStarting and SourceChanged handlers are authoritative.
   Do not read messages, passwords, or chat content. */
(() => {
  "use strict";
  if (window !== window.top || window.__rs8MessengerShellGuard) return;
  window.__rs8MessengerShellGuard = true;
  const HOME = "https://www.facebook.com/messages/";
  const allow = [
    /^\/messages(?:\/|$)/i, /^\/login(?:\.php)?(?:\/|$)/i,
    /^\/checkpoint(?:\/|$)/i, /^\/two_factor(?:\/|$)/i,
    /^\/recover(?:\/|$)/i, /^\/confirmemail(?:\/|$)/i,
    /^\/ajax\/login(?:\/|$)/i, /^\/api\/graphql(?:\/|$)/i,
    /^\/connect\/login_success\.html$/i,
    /^\/messaging\/attachment(?:\/|$)/i,
    /^\/ajax\/messaging\/attachment\.php$/i,
    /^\/attachment(?:\/|$)/i, /^\/download(?:\/|$)/i,
    /^\/photo\.php$/i
  ];
  function parse(x) {
    try { return new URL(x, window.location.href); }
    catch (_) { return null; }
  }
  function isFacebook(u) {
    return u && (u.hostname === "facebook.com" || u.hostname.endsWith(".facebook.com")) &&
      (u.protocol === "https:" || u.protocol === "http:");
  }
  function allowed(url) {
    const u = parse(url);
    if (!u) return false;
    if (!isFacebook(u)) return true;  // Host app handles external links.
    if (u.pathname === "/photo.php" && !u.searchParams.has("fbid")) return false;
    return allow.some((re) => re.test(u.pathname));
  }
  function isHomeControl(event) {
    for (const node of event.composedPath()) {
      if (!(node instanceof Element)) continue;
      if (node.matches("a[href]")) {
        const url = parse(node.href);
        if (isFacebook(url) && !allowed(url.href)) return true;
      }
      if (node.matches("button,[role=button],[role=link]")) {
        const label = (node.getAttribute("aria-label") || node.getAttribute("title") || "").trim();
        const header = node.closest("header,nav,[role=banner],[role=navigation]");
        if (header && /^facebook(?: home)?$/i.test(label)) return true;
      }
    }
    return false;
  }
  for (const name of ["pointerdown", "mousedown", "click", "auxclick"]) {
    document.addEventListener(name, (event) => {
      if (!event.isTrusted || !/^\/messages(?:\/|$)/i.test(location.pathname)) return;
      if (isHomeControl(event)) {
        event.preventDefault();
        event.stopImmediatePropagation();
      }
    }, true);
  }
  for (const method of ["pushState", "replaceState"]) {
    const original = History.prototype[method];
    if (!original) continue;
    try {
      Object.defineProperty(History.prototype, method, {
        configurable: true,
        writable: true,
        value: function (...args) {
          if (args.length >= 3 && args[2] != null && !allowed(args[2])) return;
          return Reflect.apply(original, this, args);
        }
      });
    } catch (_) {}
  }
  function enforce() {
    if (!allowed(location.href) && location.href !== HOME) location.replace(HOME);
  }
  window.addEventListener("popstate", enforce);
  window.addEventListener("hashchange", enforce);
  setInterval(enforce, 250);
  enforce();
})();
