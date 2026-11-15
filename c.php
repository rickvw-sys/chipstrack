<?php
// Chipstrack: deelpagina voor een check-in (/c/<id>), versie voor Plesk/PHP.
// Geeft voor WhatsApp, Instagram, X en andere diensten een voorbeeldkaart en stuurt mensen daarna door naar de app.
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
function ok_img($u) {
    return is_string($u) && preg_match('#^https://[a-z0-9-]+\.supabase\.co/storage/v1/object/public/photos/#', $u) ? $u : "";
}

$TXT = [
  "nl" => ["who" => "gaf", "on" => "op Chipstrack", "t" => "Smaak", "x" => "Textuur",
           "see" => "Bekijk de check-in op Chipstrack.", "plain" => "Bekijk de check-in op Chipstrack, de app om chips te beoordelen.",
           "open" => "Open in Chipstrack", "missing" => "Chipstrack – Taste · Rate · Discover"],
  "en" => ["who" => "gave", "on" => "on Chipstrack", "t" => "Taste", "x" => "Texture",
           "see" => "See the check-in on Chipstrack.", "plain" => "See the check-in on Chipstrack, the app for rating chips.",
           "open" => "Open in Chipstrack", "missing" => "Chipstrack – Taste · Rate · Discover"],
];

$id   = trim($_GET['id'] ?? "");
$lang = (($_GET['lang'] ?? "") === "en") ? "en" : "nl";
$T    = $TXT[$lang];
$page   = "$SITE/c/" . rawurlencode($id) . ($lang === "en" ? "?lang=en" : "");
$target = "/?c=" . rawurlencode($id) . ($lang === "en" ? "&lang=en" : "");
$title = $T["missing"]; $desc = $T["plain"]; $image = "$SITE/icon-512.png"; $found = false;

if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
    $c = rest("checkins?id=eq.$id&select=id,user_id,product_id,taste,texture,rating,review,photo&limit=1");
    $c = $c[0] ?? null;
    if ($c) {
        $pr = rest("products?id=eq." . $c["product_id"] . "&select=brand,variant,flavor,photo&limit=1");
        $pf = rest("profiles?id=eq." . $c["user_id"] . "&select=username&limit=1");
        $pr = $pr[0] ?? []; $pf = $pf[0] ?? [];
        $name = trim(($pr["brand"] ?? "") . " " . ($pr["flavor"] ?? "")) ?: "chips";
        $score = number_format((float)$c["rating"], 1, ".", "");
        $title = ($pf["username"] ?? "Chipstrack") . " " . $T["who"] . " $name $score ★ " . $T["on"];
        $review = trim(preg_replace('/\s+/', ' ', (string)($c["review"] ?? "")));
        if ($review !== "") $desc = "“" . mb_substr($review, 0, 180) . "”";
        elseif ($c["taste"] !== null && $c["texture"] !== null)
            $desc = $T["t"] . " " . number_format((float)$c["taste"], 1, ".", "") . " · " . $T["x"] . " " . number_format((float)$c["texture"], 1, ".", "") . ". " . $T["see"];
        $og = "$SB/storage/v1/object/public/photos/" . $c["user_id"] . "/og_$id.jpg";
        [$oc] = http($og, true);
        $image = $oc === 200 ? $og : (ok_img($c["photo"] ?? "") ?: (ok_img($pr["photo"] ?? "") ?: $image));
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
