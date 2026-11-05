/* RS8 Web Guard v0.6 - shared URL checks. No remote calls or page data collection. */
var RS8Rules = (() => {
  "use strict";
  const MESSAGES = "https://www.facebook.com/messages/";
  const ALWAYS_ALLOWED = [
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
    /^\/download(?:\/|$)/
  ];
  function parse(value) { try { return new URL(value); } catch (_) { return null; } }
  function isFacebookUrl(value) {
    const u = parse(value);
    return !!u && (u.protocol === "http:" || u.protocol === "https:") &&
      (u.hostname === "facebook.com" || u.hostname.endsWith(".facebook.com"));
  }
  function isMessengerUrl(value) {
    const u = parse(value);
    return isFacebookUrl(value) && /^\/messages(?:\/|$)/.test(u.pathname);
  }
  function isPhotoUrl(value) {
    const u = parse(value);
    return isFacebookUrl(value) &&
      (/^\/photo\.php$/.test(u.pathname) || /^\/photo\/$/.test(u.pathname)) &&
      u.searchParams.has("fbid");
  }
  function isAlwaysAllowed(value) {
    const u = parse(value);
    if (!isFacebookUrl(value)) return true;
    return ALWAYS_ALLOWED.some((pattern) => pattern.test(u.pathname));
  }
  return { MESSAGES, parse, isFacebookUrl, isMessengerUrl, isPhotoUrl, isAlwaysAllowed };
})();
