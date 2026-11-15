// Chipstrack: deelpagina voor een check-in (/c/<id>).
// Geeft voor WhatsApp, Instagram, X en andere diensten een voorbeeldkaart (titel, tekst, afbeelding)
// en stuurt mensen daarna door naar de app. Leest alleen wat ook zonder account zichtbaar is.
var SB = "https://cjnixgeeqsezrepwkwfm.supabase.co";
var KEY = "sb_publishable_7_ECMcbL-K_Ma-EvzBVeqw_EfH9EgKC";
var SITE = "https://app.chipstrack.nl";
var UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

function esc(s) {
  return String(s == null ? "" : s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
}
async function rest(path) {
  var r = await fetch(SB + "/rest/v1/" + path, { headers: { apikey: KEY, Authorization: "Bearer " + KEY } });
  if (!r.ok) return null;
  return r.json();
}
async function exists(url) {
  try { var r = await fetch(url, { method: "HEAD" }); return r.ok; } catch (e) { return false; }
}
function okImg(u) {
  return typeof u === "string" && /^https:\/\/[a-z0-9-]+\.supabase\.co\/storage\/v1\/object\/public\/photos\//.test(u) ? u : "";
}
var TXT = {
  nl: {
    title: function (who, name, score) { return who + " gaf " + name + " " + score + " ★ op Chipstrack"; },
    desc: function (taste, tex) { return "Smaak " + taste + " · Textuur " + tex + ". Bekijk de check-in op Chipstrack."; },
    plain: "Bekijk de check-in op Chipstrack, de app om chips te beoordelen.",
    open: "Open in Chipstrack",
    missing: "Chipstrack – Taste · Rate · Discover"
  },
  en: {
    title: function (who, name, score) { return who + " gave " + name + " " + score + " ★ on Chipstrack"; },
    desc: function (taste, tex) { return "Taste " + taste + " · Texture " + tex + ". See the check-in on Chipstrack."; },
    plain: "See the check-in on Chipstrack, the app for rating chips.",
    open: "Open in Chipstrack",
    missing: "Chipstrack – Taste · Rate · Discover"
  }
};

module.exports = async function (req, res) {
  var q = req.query || {};
  var id = String(q.id || "").trim();
  var lang = q.lang === "en" ? "en" : "nl";
  var T = TXT[lang];
  var langQ = lang === "en" ? "&lang=en" : "";
  var page = SITE + "/c/" + encodeURIComponent(id) + (lang === "en" ? "?lang=en" : "");
  var target = "/?c=" + encodeURIComponent(id) + langQ;
  var title = T.missing, desc = T.plain, image = SITE + "/icon-512.png", found = false;

  if (UUID.test(id)) {
    try {
      var c = await rest("checkins?id=eq." + id + "&select=id,user_id,product_id,taste,texture,rating,review,photo&limit=1");
      c = c && c[0];
      if (c) {
        var pr = await rest("products?id=eq." + c.product_id + "&select=brand,variant,flavor,photo&limit=1");
        var pf = await rest("profiles?id=eq." + c.user_id + "&select=username&limit=1");
        pr = (pr && pr[0]) || {};
        pf = (pf && pf[0]) || {};
        var name = ((pr.brand || "") + " " + (pr.flavor || "")).trim() || "chips";
        var score = (+c.rating).toFixed(1);
        title = T.title(pf.username || "Chipstrack", name, score);
        var review = String(c.review || "").replace(/\s+/g, " ").trim();
        desc = review ? "“" + review.slice(0, 180) + "”" : (c.taste != null && c.texture != null ? T.desc((+c.taste).toFixed(1), (+c.texture).toFixed(1)) : T.plain);
        var og = SB + "/storage/v1/object/public/photos/" + c.user_id + "/og_" + id + ".jpg";
        if (await exists(og)) image = og;
        else image = okImg(c.photo) || okImg(pr.photo) || image;
        found = true;
      }
    } catch (e) { /* val terug op de standaardkaart */ }
  }

  res.setHeader("Content-Type", "text/html; charset=utf-8");
  res.setHeader("Cache-Control", found ? "public, s-maxage=300, stale-while-revalidate=3600" : "public, s-maxage=60");
  res.statusCode = 200;
  res.end(
    '<!doctype html><html lang="' + lang + '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' +
    "<title>" + esc(title) + "</title>" +
    '<meta name="description" content="' + esc(desc) + '">' +
    '<meta property="og:type" content="website"><meta property="og:site_name" content="Chipstrack">' +
    '<meta property="og:title" content="' + esc(title) + '"><meta property="og:description" content="' + esc(desc) + '">' +
    '<meta property="og:url" content="' + esc(page) + '"><meta property="og:image" content="' + esc(image) + '">' +
    '<meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="' + esc(title) + '">' +
    '<meta name="twitter:description" content="' + esc(desc) + '"><meta name="twitter:image" content="' + esc(image) + '">' +
    '<meta http-equiv="refresh" content="0;url=' + esc(target) + '"><meta name="robots" content="noindex">' +
    '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#ffc20e;font-family:system-ui,sans-serif;color:#141414}a{background:#141414;color:#ffc20e;padding:14px 22px;border-radius:99px;text-decoration:none;font-weight:700}</style>' +
    '</head><body><a href="' + esc(target) + '">' + esc(T.open) + "</a></body></html>"
  );
};
