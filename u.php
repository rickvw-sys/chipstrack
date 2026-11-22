<?php
// Chipstrack: uitnodigingspagina (/u/<gebruikersnaam>), versie voor Plesk/PHP.
// Geeft in WhatsApp, Instagram, X enz. een voorbeeldkaart met naam, aantal check-ins en favoriete chips,
// en stuurt mensen daarna door naar de app. Leest alleen wat ook zonder account zichtbaar is.
$SB   = "https://cjnixgeeqsezrepwkwfm.supabase.co";
$KEY  = "sb_publishable_7_ECMcbL-K_Ma-EvzBVeqw_EfH9EgKC";
$HOST = preg_replace('/[^a-z0-9.\-]/i', '', $_SERVER['HTTP_HOST'] ?? 'app.chipstrack.nl');
$SITE = "https://" . $HOST;

function esc($s) { return htmlspecialchars((string)($s ?? ""), ENT_QUOTES, 'UTF-8'); }
function http($url, $head = false, $key = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 4, CURLOPT_FOLLOWLOCATION => false, CURLOPT_NOBODY => $head,
        CURLOPT_HTTPHEADER => $key ? ["apikey: $key", "Authorization: Bearer $key"] : []]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $body];
}
function rest($path) {
    global $SB, $KEY;
    [$code, $body] = http("$SB/rest/v1/$path", false, $KEY);
    return $code === 200 ? json_decode($body, true) : null;
}

$TXT = [
  "nl" => ["title" => "%s nodigt je uit voor Chipstrack", "fav" => "favoriet", "avg" => "gemiddeld", "ci1" => "check-in", "ciN" => "check-ins",
           "follow" => "Volg %s en proef mee.", "plain" => "Chipstrack, de app om chips te beoordelen.",
           "open" => "Open in Chipstrack", "missing" => "Chipstrack – Taste · Rate · Discover"],
  "en" => ["title" => "%s invites you to Chipstrack", "fav" => "favourite", "avg" => "average", "ci1" => "check-in", "ciN" => "check-ins",
           "follow" => "Follow %s and taste along.", "plain" => "Chipstrack, the app for rating chips.",
           "open" => "Open in Chipstrack", "missing" => "Chipstrack – Taste · Rate · Discover"],
];

$un   = trim($_GET['u'] ?? "");
$lang = (($_GET['lang'] ?? "") === "en") ? "en" : "nl";
$T    = $TXT[$lang];
$valid = preg_match('/^[A-Za-z0-9_.-]{3,24}$/', $un) === 1;
$page   = "$SITE/u/" . rawurlencode($un) . ($lang === "en" ? "?lang=en" : "");
$target = "/?u=" . rawurlencode($un) . ($lang === "en" ? "&lang=en" : "");
$title = $T["missing"]; $desc = $T["plain"]; $image = "$SITE/icon-512.png"; $found = false;

if ($valid) {
    // ilike gebruikt _ als jokerteken; daarom daarna exact (hoofdletterloos) vergelijken
    $rows = rest("profiles?username=ilike." . rawurlencode($un) . "&select=id,username&limit=5");
    $pf = null;
    foreach (($rows ?: []) as $r) { if (strtolower($r["username"]) === strtolower($un)) { $pf = $r; break; } }
    if ($pf) {
        $name = $pf["username"];
        $cs = rest("checkins?user_id=eq." . $pf["id"] . "&select=rating,product_id&order=rating.desc&limit=1000") ?: [];
        $n = count($cs);
        $title = sprintf($T["title"], $name);
        $desc = sprintf($T["follow"], $name);
        if ($n > 0) {
            $avg = array_sum(array_map(fn($c) => (float)$c["rating"], $cs)) / $n;
            $fav = "";
            $pr = rest("products?id=eq." . $cs[0]["product_id"] . "&select=brand,flavor&limit=1");
            if ($pr && $pr[0]) $fav = trim(($pr[0]["brand"] ?? "") . " " . ($pr[0]["flavor"] ?? ""));
            $dec = $lang === "en" ? "." : ",";
            $desc = "$n " . ($n === 1 ? $T["ci1"] : $T["ciN"]) . " · " . $T["avg"] . " " . number_format($avg, 1, $dec, "") . " ★"
                  . ($fav !== "" ? " · " . $T["fav"] . ": $fav" : "") . ". " . sprintf($T["follow"], $name);
        }
        $og = "$SB/storage/v1/object/public/photos/" . $pf["id"] . "/og_invite.jpg";
        [$oc] = http($og, true);
        if ($oc === 200) $image = $og . "?v=" . $n;
        $found = true;
    }
}
header("Content-Type: text/html; charset=utf-8");
header("Cache-Control: public, max-age=" . ($found ? 300 : 60));
?>
<!doctype html><html lang="<?= $lang ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= esc($title) ?></title>
<meta name="description" content="<?= esc($desc) ?>">
<meta property="og:type" content="website"><meta property="og:site_name" content="Chipstrack">
<meta property="og:title" content="<?= esc($title) ?>"><meta property="og:description" content="<?= esc($desc) ?>">
<meta property="og:url" content="<?= esc($page) ?>"><meta property="og:image" content="<?= esc($image) ?>">
<meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="<?= esc($title) ?>">
<meta name="twitter:description" content="<?= esc($desc) ?>"><meta name="twitter:image" content="<?= esc($image) ?>">
<meta http-equiv="refresh" content="0;url=<?= esc($target) ?>"><meta name="robots" content="noindex">
<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#ffc20e;font-family:system-ui,sans-serif;color:#141414}a{background:#141414;color:#ffc20e;padding:14px 22px;border-radius:99px;text-decoration:none;font-weight:700}</style>
</head><body><a href="<?= esc($target) ?>"><?= esc($T["open"]) ?></a></body></html>
