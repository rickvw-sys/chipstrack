/* Chipstrack service worker: pushmeldingen + offline vangnet.
   Verhoog CACHE bij elke nieuwe versie van de app-bestanden. */
var CACHE="chipstrack-v5";
var SHELL=["./","index.html","manifest.webmanifest","icon-192.png","icon-512.png","icon-maskable-512.png"];

self.addEventListener("install",function(e){
  e.waitUntil(caches.open(CACHE).then(function(c){
    return Promise.all(SHELL.map(function(u){return c.add(u).catch(function(){})}));
  }).then(function(){return self.skipWaiting()}));
});
self.addEventListener("activate",function(e){
  e.waitUntil(caches.keys().then(function(k){
    return Promise.all(k.filter(function(n){return n!==CACHE}).map(function(n){return caches.delete(n)}));
  }).then(function(){return self.clients.claim()}));
});

/* Alleen eigen bestanden (GET). Supabase, lettertypen en kaarten gaan altijd rechtstreeks naar het netwerk. */
self.addEventListener("fetch",function(e){
  var r=e.request;
  if(r.method!=="GET")return;
  var u=new URL(r.url);
  if(u.origin!==location.origin)return;
  if(r.mode==="navigate"){
    // pagina: eerst netwerk (altijd de nieuwste app), anders de opgeslagen versie
    e.respondWith(fetch(r).then(function(res){
      var cp=res.clone();caches.open(CACHE).then(function(c){c.put("index.html",cp)});return res;
    }).catch(function(){return caches.match("index.html").then(function(m){return m||caches.match("./")})}));
    return;
  }
  // overige eigen bestanden (iconen, manifest): uit cache, ondertussen verversen
  e.respondWith(caches.match(r).then(function(m){
    var net=fetch(r).then(function(res){
      if(res&&res.ok){var cp=res.clone();caches.open(CACHE).then(function(c){c.put(r,cp)})}return res;
    }).catch(function(){return m});
    return m||net;
  }));
});

self.addEventListener("push",function(e){
  var d={};try{d=e.data.json()}catch(x){}
  e.waitUntil(self.registration.showNotification(d.title||"Chipstrack",{body:d.body||"",icon:"icon-192.png",badge:"icon-192.png",tag:d.tag||undefined,data:{url:d.url||"./"}}));
});
self.addEventListener("notificationclick",function(e){
  e.notification.close();
  e.waitUntil(self.clients.matchAll({type:"window",includeUncontrolled:true}).then(function(l){
    for(var i=0;i<l.length;i++){if("focus" in l[i])return l[i].focus()}
    return self.clients.openWindow(e.notification.data.url||"./");
  }));
});
