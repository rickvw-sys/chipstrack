// Chipstrack service worker: offline-schil, pushmeldingen en openen van een check-in bij een tik op een melding.
var CACHE = "chipstrack-v19";
var SHELL = ["./", "index.html", "manifest.webmanifest", "icon-192.png", "icon-512.png", "privacy.html", "voorwaarden.html"];

self.addEventListener("install", function (e) {
  e.waitUntil(
    caches.open(CACHE).then(function (c) {
      return Promise.all(SHELL.map(function (u) { return c.add(u).catch(function () {}); }));
    }).then(function () { return self.skipWaiting(); })
  );
});

self.addEventListener("activate", function (e) {
  e.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(keys.filter(function (k) { return k !== CACHE; }).map(function (k) { return caches.delete(k); }));
    }).then(function () { return self.clients.claim(); })
  );
});

// Alleen bestanden van de app zelf; Supabase, lettertypen en andere diensten gaan altijd rechtstreeks via het netwerk.
self.addEventListener("fetch", function (e) {
  var req = e.request;
  if (req.method !== "GET") return;
  var url = new URL(req.url);
  if (url.origin !== self.location.origin) return;
  if (url.pathname.slice(-6) === "/sw.js") return;
  if (req.mode === "navigate") {
    // Eerst het netwerk (altijd de nieuwste app), anders de opgeslagen versie.
    e.respondWith(
      fetch(req).then(function (res) {
        if (res && res.ok) {
          var copy = res.clone();
          caches.open(CACHE).then(function (c) { c.put(new Request(url.origin + url.pathname), copy); });
        }
        return res;
      }).catch(function () {
        return caches.match(req, { ignoreSearch: true }).then(function (m) {
          return m || caches.match("index.html") || caches.match("./");
        });
      })
    );
    return;
  }
  // Afbeeldingen, manifest en dergelijke: uit de opslag, ondertussen vernieuwen.
  e.respondWith(
    caches.match(req).then(function (hit) {
      var net = fetch(req).then(function (res) {
        if (res && res.ok) { var copy = res.clone(); caches.open(CACHE).then(function (c) { c.put(req, copy); }); }
        return res;
      }).catch(function () { return hit; });
      return hit || net;
    })
  );
});

// Pushmelding ontvangen: { title, body, tag, url }
self.addEventListener("push", function (e) {
  var d = {};
  try { d = e.data ? e.data.json() : {}; } catch (err) { d = { body: e.data ? e.data.text() : "" }; }
  e.waitUntil(
    self.registration.showNotification(d.title || "Chipstrack", {
      body: d.body || "",
      tag: d.tag || undefined,
      icon: "icon-192.png",
      badge: "icon-192.png",
      data: { url: d.url || "./" }
    })
  );
});

// Tik op een melding: open direct de bijbehorende check-in.
self.addEventListener("notificationclick", function (e) {
  e.notification.close();
  var target = (e.notification.data && e.notification.data.url) || "./";
  var full = new URL(target, self.registration.scope).href;
  e.waitUntil(
    self.clients.matchAll({ type: "window", includeUncontrolled: true }).then(function (list) {
      for (var i = 0; i < list.length; i++) {
        var c = list[i];
        if (c.url.indexOf(self.registration.scope) === 0 && "focus" in c) {
          c.postMessage({ type: "open", url: full });
          return c.focus();
        }
      }
      return self.clients.openWindow ? self.clients.openWindow(full) : null;
    })
  );
});
