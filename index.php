<?php
if (file_exists(__DIR__ . '/config.php')) require __DIR__ . '/config.php';
$MM = [
    'base_path' => '',
    'site_name' => 'MusicMan',
    'default_title' => 'MusicMan — Music Player',
    'default_desc' => 'Listen to and download music — artists, albums, and tracks.',
    'default_image' => '/assets/og-default.jpg',
    'default_kw' => 'music, download, mp3, artist, album, track, MusicMan',
    'api_base' => 'https://3rah.ir/mm/api',
    'api_token' => defined('API_TOKEN') ? API_TOKEN : 'change_me_to_a_secure_token',
    'api_timeout' => 5,
];
if (defined('BASE_URL') && BASE_URL) {
    $__u = parse_url(BASE_URL, PHP_URL_PATH);
    $MM['base_path'] = ($__u === null || $__u === false || $__u === '/') ? '' : rtrim($__u, '/');
}
$__base = $MM['base_path'];
$__req = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$__scope = ($__base ?: '') . '/';

if ($__req === $__base . '/musicman.db' || preg_match('/\.db$/i', $__req)) {
    http_response_code(403); header('Content-Type: text/plain'); echo 'Access denied'; exit;
}

if ($__req === $__base . '/sw.js') {
    header('Content-Type: application/javascript; charset=utf-8');
    header('Service-Worker-Allowed: ' . $__scope);
    header('Cache-Control: no-cache, no-store, must-revalidate'); ?>
const MM_CACHE='mm-shell-v36';
const MM_SHELL=['<?=$__scope?>','<?=$__scope?>manifest.json','<?=$__scope?>icon.svg','https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css','https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css','https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js'];
self.addEventListener('install',e=>{e.waitUntil((async()=>{const c=await caches.open(MM_CACHE);await Promise.allSettled(MM_SHELL.map(u=>c.add(new Request(u,{cache:'reload'})).catch(()=>{})));self.skipWaiting()})())});
self.addEventListener('activate',e=>{e.waitUntil((async()=>{const k=await caches.keys();await Promise.all(k.filter(x=>x!==MM_CACHE).map(x=>caches.delete(x)));self.clients.claim()})())});
self.addEventListener('message',e=>{if(e.data==='skipWaiting')self.skipWaiting()});
self.addEventListener('fetch',e=>{const r=e.request;if(r.method!=='GET')return;const u=new URL(r.url);
if(u.origin!==self.location.origin)return;
if(u.pathname.startsWith('<?=$__scope?>api/')||u.pathname.startsWith('<?=$__scope?>api2/'))return;
if(u.pathname.endsWith('/sw.js'))return;
if(r.mode==='navigate'){e.respondWith((async()=>{try{const res=await fetch(r);const cp=res.clone();caches.open(MM_CACHE).then(c=>c.put('<?=$__scope?>',cp)).catch(()=>{});return res}catch{const c=await caches.open(MM_CACHE);const ca=(await c.match('<?=$__scope?>'))||(await c.match(r));if(ca)return ca;return new Response('Offline',{status:503})}})());return}
e.respondWith((async()=>{const ca=await caches.match(r);if(ca)return ca;try{const res=await fetch(r);if(res&&res.ok&&(res.type==='basic'||res.type==='cors')){const cp=res.clone();caches.open(MM_CACHE).then(c=>c.put(r,cp)).catch(()=>{})}return res}catch{return new Response('',{status:504})}})())});
<?php exit;
}

if ($__req === $__base . '/manifest.json' || $__req === $__base . '/manifest.webmanifest') {
    header('Content-Type: application/manifest+json; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    echo json_encode([
        'name' => 'MusicMan', 'short_name' => 'MusicMan',
        'description' => 'Listen to and download music — artists, albums, and tracks.',
        'start_url' => $__scope, 'scope' => $__scope,
        'display' => 'standalone', 'orientation' => 'any',
        'background_color' => '#0b0b0d', 'theme_color' => '#0b0b0d',
        'categories' => ['music', 'entertainment'],
        'icons' => [
            ['src' => $__scope . 'icon.svg', 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any'],
            ['src' => $__scope . 'icon.svg', 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'maskable'],
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

// FIXED favicon: perfectly centered waveform
if ($__req === $__base . '/icon.svg') {
    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Cache-Control: public, max-age=31536000, immutable');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32">
  <rect width="32" height="32" rx="7" fill="#14b8a6"/>
  <g fill="#fff">
    <rect x="15" y="5" width="2" height="22" rx="1"/>
    <rect x="11" y="9" width="2" height="14" rx="1"/>
    <rect x="19" y="9" width="2" height="14" rx="1"/>
    <rect x="7" y="12" width="2" height="8" rx="1"/>
    <rect x="23" y="12" width="2" height="8" rx="1"/>
    <rect x="3" y="14" width="2" height="4" rx="1"/>
    <rect x="27" y="14" width="2" height="4" rx="1"/>
  </g>
</svg>';
    exit;
}

function mm_parse_url($basePath) {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $path = urldecode($path);
    if ($basePath !== '' && strpos($path, $basePath) === 0) $path = substr($path, strlen($basePath));
    $path = trim($path, '/');
    if ($path === '') return [null, null];
    $parts = explode('/', $path);
    if (count($parts) < 2) return [null, null];
    $type = $parts[0];
    if (!in_array($type, ['artist', 'track', 'collection', 'album'], true)) return [null, null];
    if ($type === 'album') $type = 'collection';
    $clean = '';
    foreach (str_split($parts[1]) as $c) if (ctype_alnum($c) || $c === '_' || $c === '-') $clean .= $c;
    return $clean === '' ? [null, null] : [$type, $clean];
}
function mm_pick_artwork($item, $preferPx = 600) {
    if (!empty($item['attachments']['artworkUrls']) && is_array($item['attachments']['artworkUrls'])) {
        $best = null; $bestPx = -1;
        foreach ($item['attachments']['artworkUrls'] as $a) {
            if (empty($a['url'])) continue;
            $px = (int)filter_var($a['size'] ?? '0', FILTER_SANITIZE_NUMBER_INT);
            if ($px > $bestPx) { $bestPx = $px; $best = $a['url']; }
        }
        if ($best) return preg_replace('~/\d+x\d+(bb)?\.~', "/{$preferPx}x{$preferPx}bb.", $best);
    }
    $art = $item['artworkUrl100'] ?? '';
    return $art ? str_replace(['1000x1000','100x100', '60x60', '30x30'], '600x600', $art) : '';
}
function mm_fetch_item($type, $id, $cfg) {
    $entity = $type === 'artist' ? 'musicArtist' : ($type === 'track' ? 'song' : 'album');
    $url = rtrim($cfg['api_base'], '/') . '/lookup?id=' . urlencode($id) . '&entity=' . $entity;
    $headers = ['Accept: application/json', 'User-Agent: MusicMan-SEO/1.0'];
    if (!empty($cfg['api_token'])) $headers[] = 'Authorization: Bearer ' . $cfg['api_token'];
    $body = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>$cfg['api_timeout'],CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_HTTPHEADER=>$headers]);
        $body = curl_exec($ch); curl_close($ch);
    } else {
        $ctx = stream_context_create(['http'=>['timeout'=>$cfg['api_timeout'],'method'=>'GET','header'=>implode("\r\n",$headers)."\r\n",'ignore_errors'=>true]]);
        $body = @file_get_contents($url, false, $ctx);
    }
    if (!$body) return null;
    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['results'][0])) return null;
    return $data['results'][0];
}
function mm_jsonld($type, $item, $url) {
    $name = $item['trackName'] ?? $item['collectionName'] ?? $item['artistName'] ?? '';
    $artist = $item['artistName'] ?? '';
    $art = mm_pick_artwork($item);
    if ($type === 'track') {
        $d = ['@context'=>'https://schema.org','@type'=>'MusicRecording','name'=>$name,'url'=>$url];
        if ($artist) $d['byArtist'] = ['@type'=>'MusicGroup','name'=>$artist];
        if (!empty($item['collectionName'])) $d['inAlbum'] = ['@type'=>'MusicAlbum','name'=>$item['collectionName']];
        if (!empty($item['trackTimeMillis'])) $d['duration'] = 'PT' . (int)floor($item['trackTimeMillis'] / 1000) . 'S';
        if ($art) $d['image'] = $art;
    } elseif ($type === 'collection') {
        $d = ['@context'=>'https://schema.org','@type'=>'MusicAlbum','name'=>$name,'url'=>$url];
        if ($artist) $d['byArtist'] = ['@type'=>'MusicGroup','name'=>$artist];
        if ($art) $d['image'] = $art;
        if (!empty($item['releaseDate'])) $d['datePublished'] = $item['releaseDate'];
        if (!empty($item['trackCount'])) $d['numTracks'] = (int)$item['trackCount'];
    } else {
        $d = ['@context'=>'https://schema.org','@type'=>'MusicGroup','name'=>$name,'url'=>$url];
        if (!empty($item['primaryGenreName'])) $d['genre'] = $item['primaryGenreName'];
        if ($art) $d['image'] = $art;
    }
    return json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
function mm_hydration_payload($item) {
    if (!$item) return null;
    return [
        'wrapperType'=>$item['wrapperType']??null,'trackId'=>$item['trackId']??null,'collectionId'=>$item['collectionId']??null,'artistId'=>$item['artistId']??null,
        'trackName'=>$item['trackName']??null,'collectionName'=>$item['collectionName']??null,'artistName'=>$item['artistName']??null,
        'artworkUrl100'=>$item['artworkUrl100']??null,'primaryGenreName'=>$item['primaryGenreName']??null,'releaseDate'=>$item['releaseDate']??null,
        'trackCount'=>$item['trackCount']??null,'trackTimeMillis'=>$item['trackTimeMillis']??null,'trackNumber'=>$item['trackNumber']??null,
        'lyrics'=>$item['lyrics']??($item['attachments']['lyrics']??null),
        'attachments'=>['artworkUrls'=>$item['attachments']['artworkUrls']??[],'previewUrls'=>$item['attachments']['previewUrls']??[],'audioUrls'=>$item['attachments']['audioUrls']??[]],
    ];
}

list($seoType, $seoId) = mm_parse_url($MM['base_path']);
$seoItem = ($seoType && $seoId) ? mm_fetch_item($seoType, $seoId, $MM) : null;
$seoTitle = $MM['default_title']; $seoDesc = $MM['default_desc']; $seoImage = $MM['default_image'];
$seoKeywords = $MM['default_kw']; $seoOgType = 'website'; $seoUrl = '';
if ($seoItem) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $seoUrl = $scheme . '://' . $host . $MM['base_path'] . '/' . $seoType . '/' . rawurlencode($seoId);
    $name = $seoItem['trackName'] ?? $seoItem['collectionName'] ?? $seoItem['artistName'] ?? '';
    $artist = $seoItem['artistName'] ?? '';
    $art = mm_pick_artwork($seoItem);
    if ($art) $seoImage = $art;
    if ($seoType === 'track') {
        $seoTitle = $name . ($artist ? ' — ' . $artist : '') . ' · ' . $MM['site_name'];
        $seoDesc = 'Listen to "' . $name . '"' . ($artist ? ' by ' . $artist : '') . (!empty($seoItem['collectionName']) ? ' from the album "' . $seoItem['collectionName'] . '"' : '') . ' — free streaming and offline download on MusicMan.';
        $seoOgType = 'music.song';
        $seoKeywords = $name . ', ' . $artist . ', song, mp3, music, download';
    } elseif ($seoType === 'collection') {
        $year = !empty($seoItem['releaseDate']) ? date('Y', strtotime($seoItem['releaseDate'])) : '';
        $seoTitle = $name . ($artist ? ' — ' . $artist : '') . ($year ? " ({$year})" : '') . ' · ' . $MM['site_name'];
        $seoDesc = 'Listen to the album "' . $name . '"' . ($artist ? ' by ' . $artist : '') . ($year ? " ({$year})" : '') . (!empty($seoItem['trackCount']) ? ' — ' . $seoItem['trackCount'] . ' tracks.' : '.') . ' Download & stream on MusicMan.';
        $seoOgType = 'music.album';
        $seoKeywords = $name . ', ' . $artist . ', ' . $year . ', album, mp3, download';
    } else {
        $genre = $seoItem['primaryGenreName'] ?? '';
        $seoTitle = $name . ' · ' . $MM['site_name'];
        $seoDesc = 'Listen to music by ' . $name . ($genre ? " ({$genre})" : '') . ' on MusicMan.';
        $seoOgType = 'music.musician';
        $seoKeywords = $name . ', ' . $genre . ', artist, music';
    }
}
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?><!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover,user-scalable=no,maximum-scale=1">
<meta name="theme-color" content="#0b0b0d">
<meta name="color-scheme" content="dark light">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="MusicMan">
<title><?=$e($seoTitle)?></title>
<meta name="description" content="<?=$e($seoDesc)?>">
<meta name="keywords" content="<?=$e($seoKeywords)?>">
<meta name="robots" content="index, follow">
<meta name="author" content="<?=$e($MM['site_name'])?>">
<?php if ($seoUrl):?><link rel="canonical" href="<?=$e($seoUrl)?>"><?php endif;?>
<meta property="og:site_name" content="<?=$e($MM['site_name'])?>">
<meta property="og:type" content="<?=$e($seoOgType)?>">
<meta property="og:title" content="<?=$e($seoTitle)?>">
<meta property="og:description" content="<?=$e($seoDesc)?>">
<meta property="og:image" content="<?=$e($seoImage)?>">
<meta property="og:image:width" content="600"><meta property="og:image:height" content="600">
<meta property="og:locale" content="en_US">
<?php if ($seoUrl):?><meta property="og:url" content="<?=$e($seoUrl)?>"><?php endif;?>
<?php if ($seoType === 'track' && $seoItem):?>
<?php if (!empty($seoItem['artistName'])):?><meta property="music:musician" content="<?=$e($seoItem['artistName'])?>"><?php endif;?>
<?php if (!empty($seoItem['collectionName'])):?><meta property="music:album" content="<?=$e($seoItem['collectionName'])?>"><?php endif;?>
<?php if (!empty($seoItem['trackTimeMillis'])):?><meta property="music:duration" content="<?=(int)floor($seoItem['trackTimeMillis'] / 1000)?>"><?php endif;?>
<?php endif;?>
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?=$e($seoTitle)?>">
<meta name="twitter:description" content="<?=$e($seoDesc)?>">
<meta name="twitter:image" content="<?=$e($seoImage)?>">
<?php if ($seoItem):?><script type="application/ld+json"><?=mm_jsonld($seoType, $seoItem, $seoUrl ?: '/')?></script>
<?php else:?><script type="application/ld+json">{"@context":"https://schema.org","@type":"WebSite","name":"<?=$e($MM['site_name'])?>","url":"<?=$e(((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'))?>"}</script><?php endif;?>
<link rel="manifest" href="<?=$e($__scope)?>manifest.json">
<link rel="icon" type="image/svg+xml" href="<?=$e($__scope)?>icon.svg">
<link rel="apple-touch-icon" href="<?=$e($__scope)?>icon.svg">
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="dns-prefetch" href="//cdn.jsdelivr.net">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script>(function(){try{var hc=navigator.hardwareConcurrency||4,dm=navigator.deviceMemory||4,rm=matchMedia('(prefers-reduced-motion: reduce)').matches,rd=navigator.connection?.saveData;if((hc<=4&&dm<=4)||hc<=2||dm<=2||rm||rd)document.documentElement.classList.add('low-end');var s=JSON.parse(localStorage.getItem('mm_settings')||'{}');var t=s.theme||'dark';if(s.autoTheme){var h=new Date().getHours();t=(h>=7&&h<19)?'light':'dark';}document.documentElement.setAttribute('data-bs-theme',t);var m=document.querySelector('meta[name="theme-color"]');if(m)m.content=t==='dark'?'#0b0b0d':'#ffffff';}catch(e){}})();</script>
<style>
:root{
  --mm-ease:cubic-bezier(.32,.72,0,1);
  --mm-ease-out:cubic-bezier(.16,1,.3,1);
  --safe-top:env(safe-area-inset-top,0px);
  --safe-bot:env(safe-area-inset-bottom,0px);
  --r-xs:6px;--r-sm:10px;--r-md:14px;--r-lg:18px;--r-xl:24px;--r-full:999px;
  --s-1:4px;--s-2:8px;--s-3:12px;--s-4:16px;--s-5:20px;--s-6:24px;--s-8:32px;
  --mm-surface:rgba(var(--bs-body-color-rgb),.045);
  --mm-surface-2:rgba(var(--bs-body-color-rgb),.07);
  --mm-surface-3:rgba(var(--bs-body-color-rgb),.12);
  --mm-divider:rgba(var(--bs-body-color-rgb),.08);
  --el-1:0 1px 2px rgba(0,0,0,.06),0 1px 3px rgba(0,0,0,.08);
  --el-2:0 2px 6px rgba(0,0,0,.08),0 4px 12px rgba(0,0,0,.10);
  --el-3:0 8px 28px rgba(0,0,0,.16),0 4px 12px rgba(0,0,0,.08);
  --el-4:0 20px 60px rgba(0,0,0,.30),0 8px 24px rgba(0,0,0,.18);
  --mm-preview:#38bdf8;--mm-preview-rgb:56,189,248;
  --mm-accent-grad:linear-gradient(135deg,#7b2ff7,#f107a3);
  --mm-accent-1:#7b2ff7;
}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html,body{height:100%}
body{margin:0;height:100dvh;display:flex;flex-direction:column;overflow:hidden;overscroll-behavior:none;-webkit-font-smoothing:antialiased;user-select:none;font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;letter-spacing:-.005em;background:var(--bs-body-bg);position:relative}
.app-bg{position:fixed;inset:-20%;background-size:cover;background-position:center;filter:blur(90px) saturate(10) brightness(110%);opacity:.1;pointer-events:none;z-index:-1;transition:background-image .5s ease,opacity .1s ease;}
[data-bs-theme="light"] .app-bg{opacity:.1;filter:blur(100px) saturate(10)}
input,textarea{user-select:text}
a{text-decoration:none;color:inherit}
a.mm-link{color:inherit;border-bottom:1px solid transparent;transition:border-color .15s,color .15s}
a.mm-link:hover{color:var(--bs-primary);border-bottom-color:currentColor;cursor:pointer}
.min-w-0{min-width:0}
button i.bi{line-height:1;display:inline-block}
:focus-visible{outline:2px solid var(--bs-primary);outline-offset:2px;border-radius:4px}
.low-end,.low-end *,.low-end *::before,.low-end *::after{filter:none!important;backdrop-filter:none!important;-webkit-backdrop-filter:none!important}
.low-end #fpBg,.low-end #appBg{
    display:none!important;
}
.no-animations,.no-animations *,.no-animations *::before,.no-animations *::after{animation:none!important;transition:none!important}
.app-bar{flex:0 0 auto;display:flex;align-items:center;gap:8px;padding:calc(var(--safe-top) + 10px) 12px 10px;border-bottom:1px solid var(--mm-divider);z-index:20}
.app-bar .brand{display:flex;align-items:center;gap:8px;font-weight:800;font-size:1.02rem;letter-spacing:-.02em}
.app-bar .brand i{color:inherit;font-size:1.35rem}
.app-bar .page-title{font-size:.95rem;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.app-main{flex:1 1 auto;overflow-y:auto;overflow-x:hidden;-webkit-overflow-scrolling:touch;scrollbar-width:none;overscroll-behavior-y:contain}
.app-main::-webkit-scrollbar{display:none}
.app-bottom{flex:0 0 auto;padding-bottom:var(--safe-bot);border-top:1px solid var(--mm-divider);z-index:20}
.net-pill{display:none;align-items:center;gap:5px;font-size:.65rem;font-weight:800;letter-spacing:.05em;text-transform:uppercase;padding:3px 8px;border-radius:var(--r-full);background:rgba(var(--bs-success-rgb),.14);color:var(--bs-success);margin-right:4px}
.net-pill.off{display:inline-flex;background:rgba(var(--bs-warning-rgb),.16);color:var(--bs-warning)}
.mini{position:relative;cursor:pointer;transition:background .2s}
.mini:active{background:var(--mm-surface)}
.mini-progress{height:2px;background:rgba(var(--bs-body-color-rgb),.13)}
.mini-progress>div{height:100%;width:0;background:var(--bs-primary);transition:width .2s linear}
.mini-row{display:flex;align-items:center;gap:10px;padding:8px 12px}
.mini-art{width:44px;height:44px;border-radius:var(--r-sm);object-fit:cover;flex:0 0 auto;background:var(--mm-surface-2)}
.mini-title{font-size:.83rem;font-weight:700;line-height:1.25}
.mini-sub{font-size:.72rem;color:var(--bs-secondary-color);line-height:1.25}
.mini-eq{display:none;gap:2px;height:14px;align-items:flex-end;margin-right:2px}
.mini-eq.on{display:inline-flex}
.mini-eq span{width:2px;background:var(--bs-primary);border-radius:1px;animation:eqbar .9s ease-in-out infinite}
.mini-eq span:nth-child(1){height:40%;animation-delay:-.6s}
.mini-eq span:nth-child(2){height:100%;animation-delay:-.4s}
.mini-eq span:nth-child(3){height:60%;animation-delay:-.2s}
.mini-eq span:nth-child(4){height:80%;animation-delay:0s}
@keyframes eqbar{0%,100%{transform:scaleY(.35)}50%{transform:scaleY(1)}}
.tabbar{display:flex}
.tabbar a{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;padding:7px 0 8px;position:relative;color:var(--bs-secondary-color);font-size:.66rem;font-weight:600;transition:color .18s}
.tabbar a i{font-size:1.28rem;line-height:1;transition:transform .22s var(--mm-ease)}
.tabbar a.active{color:var(--bs-primary)}
.tabbar a.active i{transform:scale(1.1)}
.tab-badge{position:absolute;top:2px;left:50%;margin-left:10px;min-width:16px;height:16px;padding:0 4px;border-radius:var(--r-full);background:var(--bs-primary);color:#fff;font-size:.6rem;font-weight:700;line-height:16px;text-align:center;display:none}
.tab-badge.on{display:inline-block}
.icon-btn{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;flex:0 0 auto;border:0;background:transparent;color:var(--bs-body-color);border-radius:50%;font-size:1.12rem;padding:0;transition:background .15s,color .15s,transform .12s var(--mm-ease);cursor:pointer}
.icon-btn:active{background:var(--mm-surface-3);transform:scale(.92)}
.icon-btn.sm{width:32px;height:32px;font-size:.95rem}
.icon-btn.lg{width:54px;height:54px;font-size:1.7rem}
.icon-btn.liked{color:var(--bs-danger)}
.icon-btn.on{color:var(--bs-primary)}
.icon-btn i{line-height:1}
.pill-btn{display:inline-flex;align-items:center;gap:6px;border:0;border-radius:var(--r-full);padding:9px 18px;font-size:.83rem;font-weight:700;line-height:1;background:var(--mm-surface-2);color:var(--bs-body-color);transition:transform .12s var(--mm-ease),background .15s,filter .15s;cursor:pointer;white-space:nowrap}
.pill-btn:active{transform:scale(.96)}
.pill-btn>i{font-size:1em;line-height:1}
.pill-btn.primary{background:var(--bs-primary);color:#fff}
.pill-btn.success{background:var(--bs-success);color:#fff}
.pill-btn.warn{background:rgba(var(--bs-warning-rgb),.15);color:var(--bs-warning)}
.pill-btn.danger{background:rgba(var(--bs-danger-rgb),.15);color:var(--bs-danger)}
.pill-btn.info{background:rgba(var(--bs-info-rgb),.16);color:var(--bs-info)}
.pill-btn.preview{background:rgba(var(--mm-preview-rgb),.16);color:var(--mm-preview)}
.pill-btn.telegram{background:rgba(41,171,226,.16);color:#29abe2}
.pill-btn.sm{font-size:.75rem;padding:6px 12px}
.pill-btn[disabled]{opacity:.5;pointer-events:none}
.badge-chip{display:inline-flex;align-items:center;gap:4px;font-size:.58rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;padding:2px 6px;border-radius:var(--r-xs);flex:0 0 auto;white-space:nowrap;vertical-align:middle;line-height:1.3}
.badge-chip.preview{background:rgba(var(--mm-preview-rgb),.16);color:var(--mm-preview)}
.badge-chip.crawled{background:rgba(var(--bs-success-rgb),.16);color:var(--bs-success)}
.badge-chip.crawling{background:rgba(var(--bs-info-rgb),.16);color:var(--bs-info)}
.badge-chip.ready{background:rgba(var(--bs-warning-rgb),.16);color:var(--bs-warning)}
.badge-chip.telegram{background:rgba(41,171,226,.16);color:#29abe2}
.badge-chip.public{background:rgba(var(--bs-primary-rgb),.16);color:var(--bs-primary)}
.badge-chip.private{background:rgba(var(--bs-secondary-rgb),.18);color:var(--bs-secondary-color)}
.badge-chip>.spinner-border{width:10px!important;height:10px!important;border-width:2px!important}
.sec-title{font-size:.7rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:var(--bs-secondary-color);padding:20px 16px 10px;margin:0}
.sec-title.tight{padding-top:12px}
.sec-head{display:flex;align-items:center;gap:8px;padding:22px 16px 10px}
.sec-head .sec-title{padding:0;flex:1}
.sec-head .sec-link{font-size:.72rem;font-weight:700;color:var(--bs-primary);cursor:pointer;display:inline-flex;align-items:center;gap:4px;padding:4px 8px;border-radius:var(--r-full);background:rgba(var(--bs-primary-rgb),.1)}
.mm-grid{display:flex;gap:12px;overflow-x:auto;padding:0 0 4px;-webkit-overflow-scrolling:touch;scrollbar-width:none}
.mm-grid::-webkit-scrollbar{display:none}
.mm-grid>*{flex:0 0 auto}
.card-item{width:138px;display:block;padding-left:15px}
.card-art{width:138px;height:138px;border-radius:var(--r-md);object-fit:cover;display:block;background:var(--mm-surface-2);box-shadow:var(--el-2);transition:transform .2s var(--mm-ease)}
.card-item:hover .card-art{transform:translateY(-2px);box-shadow:var(--el-3)}
.card-art-ph{display:flex;align-items:center;justify-content:center;color:var(--bs-secondary-color);font-size:3rem}
.card-name{font-size:.82rem;font-weight:700;margin-top:8px}
.card-sub{font-size:.72rem;color:var(--bs-secondary-color)}
.artist-item{width:104px;display:block;text-align:center;padding-left:15px}
.artist-art{width:104px;max-width:100%;aspect-ratio:1/1;border-radius:50%;object-fit:cover;display:block;background:linear-gradient(145deg,rgba(var(--bs-primary-rgb),.28),rgba(var(--bs-primary-rgb),.08));transition:transform .2s var(--mm-ease)}
.artist-item:hover .artist-art{transform:scale(1.03)}
.artist-art-ph{display:flex;align-items:center;justify-content:center;color:var(--bs-primary);font-size:2.6rem}
.row-item{display:flex;align-items:center;gap:6px;padding:6px 8px;border-radius:var(--r-md);transition:background .15s}
.row-item:hover{background:var(--mm-surface)}
.row-item.playing{background:rgba(var(--bs-primary-rgb),.09)}
.row-play{width:42px;height:42px;flex:0 0 auto;border:0;border-radius:50%;background:var(--mm-surface-2);color:var(--bs-body-color);display:flex;align-items:center;justify-content:center;font-size:1.35rem;padding:0;transition:background .15s,transform .12s var(--mm-ease);cursor:pointer}
.row-play.is-preview{background:rgba(var(--mm-preview-rgb),.16);color:var(--mm-preview)}
.row-play:active{transform:scale(.92)}
.row-play.playing{background:var(--bs-primary);color:#fff}
.row-info{flex-grow:1;display:flex;align-items:center;min-width:0;transition:opacity .15s}
.row-item:hover .row-info{opacity:.85}
.row-art{margin-right:10px;width:46px;height:46px;border-radius:var(--r-sm);object-fit:cover;flex:0 0 auto;background:var(--mm-surface-2)}
.row-art-ph{display:flex;align-items:center;justify-content:center;color:var(--bs-secondary-color);font-size:1.1rem}
.row-title{font-size:.83rem;font-weight:700;line-height:1.3}
.row-sub{font-size:.72rem;color:var(--bs-secondary-color);line-height:1.3}
.row-actions{display:flex;align-items:center;gap:0;opacity:.4;transition:opacity .15s}
.row-item:hover .row-actions{opacity:1}
@media (min-width:1024px){
  .mm-grid{display:grid;grid-auto-flow:row;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:18px;overflow:visible;padding:0 16px 4px}
  .mm-grid>*{flex:initial;width:100%;padding-left:0}
  .mm-grid .card-item{width:100%}
  .mm-grid .card-art{width:100%;height:auto;aspect-ratio:1/1}
  .mm-grid .artist-item{width:100%}
  .mm-grid .artist-art{width:100%;max-width:140px;margin:0 auto}
  .mm-grid.mm-grid-narrow{grid-template-columns:repeat(auto-fill,minmax(130px,1fr))}
  .row-item{padding:8px 12px}
}
.row-info:empty{display:none}
.row-info:not(:has(.row-art)){flex-grow:1}
.row-item:has(.min-w-0.flex-grow-1) .row-info:has(.row-art):not(:has(.min-w-0.flex-grow-1)){flex-grow:0;flex-shrink:0}
.dl-row{display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:var(--r-md);transition:background .15s}
.dl-row+.dl-row{margin-top:2px}
.dl-row:hover{background:var(--mm-surface)}
.dl-body{flex:1;min-width:0}
.dl-title{font-size:.83rem;font-weight:700;line-height:1.3}
.dl-sub{font-size:.7rem;color:var(--bs-secondary-color);line-height:1.3;margin-top:1px}
.dl-progress{height:4px;border-radius:var(--r-full);margin-top:6px;background:var(--mm-surface-3);overflow:hidden}
.dl-progress>div{height:100%;background:var(--bs-primary);border-radius:var(--r-full);transition:width .4s ease}
.dl-progress.saving>div{background:var(--bs-info)}
.dl-progress.error>div{background:var(--bs-danger)}
.dl-progress.ready>div{background:var(--bs-warning)}
.dl-status{font-size:.62rem;font-weight:800;letter-spacing:.05em;text-transform:uppercase;padding:2px 6px;border-radius:var(--r-xs)}
.dl-status.queued{background:rgba(var(--bs-secondary-rgb),.2);color:var(--bs-secondary-color)}
.dl-status.crawling,.dl-status.saving{background:rgba(var(--bs-info-rgb),.18);color:var(--bs-info)}
.dl-status.error{background:rgba(var(--bs-danger-rgb),.18);color:var(--bs-danger)}
.dl-status.ready{background:rgba(var(--bs-warning-rgb),.18);color:var(--bs-warning)}
.sk{background:linear-gradient(90deg,rgba(var(--bs-body-color-rgb),.05) 0%,rgba(var(--bs-body-color-rgb),.12) 50%,rgba(var(--bs-body-color-rgb),.05) 100%);background-size:200% 100%;animation:sk 1.4s var(--mm-ease) infinite;border-radius:var(--r-sm)}
html.low-end .sk{animation:none;background:var(--mm-surface-2)}
@keyframes sk{0%{background-position:200% 0}100%{background-position:-200% 0}}
.sk-card{width:138px;flex:0 0 auto;display:flex;flex-direction:column;gap:8px}
.sk-card-art{width:138px;height:138px;border-radius:var(--r-md)}
.sk-row{display:flex;align-items:center;gap:12px;padding:8px 12px;border-radius:var(--r-md)}
.sk-row-play{width:40px;height:40px;border-radius:50%;flex:0 0 auto}
.sk-row-art{width:46px;height:46px;border-radius:var(--r-sm);flex:0 0 auto}
.state{text-align:center;padding:56px 24px;color:var(--bs-secondary-color)}
.state>i{font-size:3rem;display:block;margin-bottom:14px;opacity:.55}
.state p{margin:0;font-size:.88rem}
.state button i{font-size:1em;display:inline;margin:0;opacity:1}
.search-wrap{position:sticky;top:0;z-index:30;padding:10px 12px;backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border-bottom:1px solid var(--mm-divider)}
.search-wrap form{position:relative}
.search-box{display:flex;align-items:center;gap:8px;background:var(--mm-surface-2);border-radius:var(--r-md);padding:0 12px;transition:box-shadow .15s,background .15s}
.search-box:focus-within{background:var(--mm-surface-3);box-shadow:0 0 0 2px rgba(var(--bs-primary-rgb),.35)}
.search-box i{color:var(--bs-secondary-color);font-size:1rem}
.search-box input{flex:1;border:0;background:transparent;outline:none;padding:11px 0;font-size:.9rem;color:var(--bs-body-color);min-width:0}
.search-box input::placeholder{color:var(--bs-secondary-color)}
.search-box input::-webkit-search-cancel-button{display:none}
.filter-row{display:flex;gap:6px;overflow-x:auto;padding:8px 12px 4px;scrollbar-width:none}
.filter-row::-webkit-scrollbar{display:none}
.chip{flex:0 0 auto;border:0;border-radius:var(--r-full);padding:6px 14px;font-size:.75rem;font-weight:700;background:var(--mm-surface-2);color:var(--bs-body-color);transition:all .15s;cursor:pointer}
.chip.on{background:var(--bs-primary);color:#fff}
.search-suggest{position:absolute;top:calc(100% + 8px);left:0;right:0;background:var(--bs-body-bg);border-radius:var(--r-md);box-shadow:var(--el-4),0 0 0 1px rgba(var(--bs-body-color-rgb),.06);padding:6px;z-index:200;max-height:min(65vh,440px);overflow-y:auto;display:none;overscroll-behavior:contain;-webkit-overflow-scrolling:touch}
.search-suggest.show{display:block;animation:sgIn .14s var(--mm-ease)}
@keyframes sgIn{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:translateY(0)}}
.sg-item{display:flex;align-items:center;gap:12px;padding:11px 12px;border-radius:var(--r-sm);border:0;background:transparent;width:100%;text-align:left;color:var(--bs-body-color);cursor:pointer;transition:background .1s}
.sg-item.active{background:rgba(var(--bs-primary-rgb),.13)}
.sg-icon{width:22px;flex:0 0 auto;text-align:center;color:var(--bs-secondary-color);font-size:1rem}
.sg-item.active .sg-icon{color:var(--bs-primary)}
.sg-title{font-size:.86rem;font-weight:600;line-height:1.25;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sg-title b{color:var(--bs-primary);font-weight:800}
.sg-badge{font-size:.58rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase;padding:3px 7px;border-radius:5px;background:var(--mm-surface-2);color:var(--bs-secondary-color);flex:0 0 auto}
.sg-loading,.sg-empty{padding:14px;font-size:.8rem;color:var(--bs-secondary-color);display:flex;align-items:center;gap:8px;justify-content:center}
.full-player{position:fixed;inset:0;z-index:1080;display:flex;flex-direction:column;background:var(--bs-body-bg);transform:translateY(100%);opacity:0;pointer-events:none;transition:transform .36s var(--mm-ease),opacity .22s ease;padding-top:var(--safe-top);padding-bottom:var(--safe-bot);overflow:hidden}
.full-player.show{transform:translateY(0);opacity:1;pointer-events:auto}
.fp-bg{position:absolute;inset:-25%;background-size:cover;background-position:center;filter:blur(80px) saturate(1.7);opacity:.36;z-index:0;pointer-events:none;transition:background-image .35s ease}
.fp-scrim{position:absolute;inset:0;z-index:0;pointer-events:none;background:linear-gradient(180deg,rgba(0,0,0,.05),rgba(0,0,0,.55))}
[data-bs-theme="light"] .fp-scrim{background:linear-gradient(180deg,rgba(255,255,255,.45),rgba(255,255,255,.85))}
.fp-inner{position:relative;z-index:1;display:flex;flex-direction:column;height:100%;overflow:hidden;max-width:560px;margin:0 auto;width:100%}
.fp-head{display:flex;align-items:center;gap:4px;padding:6px 8px 2px;flex:0 0 auto}
.fp-head-tabs{display:flex;gap:2px;flex:1;min-width:0;justify-content:center;background:var(--mm-surface-2);border-radius:var(--r-sm);padding:3px;max-width:320px;margin:0 auto}
.fp-head-tab{flex:1;min-width:0;display:flex;align-items:center;justify-content:center;gap:6px;border:0;background:transparent;color:var(--bs-secondary-color);font-size:.72rem;font-weight:800;padding:7px 8px;border-radius:var(--r-xs);cursor:pointer;transition:background .15s,color .15s;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.fp-head-tab.active{background:var(--bs-body-bg);color:var(--bs-body-color);box-shadow:var(--el-1)}
.fp-head-tab i{font-size:.95rem;flex:0 0 auto}
.fp-head-tab .rs-badge{min-width:16px;height:16px;padding:0 5px;border-radius:var(--r-full);background:var(--bs-primary);color:#fff;font-size:.58rem;font-weight:800;line-height:16px;text-align:center;display:none}
.fp-head-tab .rs-badge.on{display:inline-block}
.fp-tabbody{flex:1 1 auto;overflow-y:auto;overflow-x:hidden;min-height:0;scrollbar-width:none;padding:4px 0 30px;overscroll-behavior:contain;-webkit-overflow-scrolling:touch}
.fp-tabbody::-webkit-scrollbar{display:none}
.fp-body{display:flex;flex-direction:column;justify-content:center;padding:0 26px;min-height:100%}
.fp-art{width:100%;max-width:310px;aspect-ratio:1;margin:0 auto;border-radius:var(--r-xl);object-fit:cover;background:var(--mm-surface-2);box-shadow:var(--el-4)}
.fp-art-ph{display:flex;align-items:center;justify-content:center;color:var(--bs-secondary-color);font-size:5rem}
.fp-meta{margin-top:20px;text-align:center}
.fp-title{font-size:1.16rem;font-weight:800;line-height:1.3;letter-spacing:-.015em}
.fp-artist{font-size:.85rem;color:var(--bs-secondary-color);margin-top:4px}
.fp-seek{margin-top:16px;position:relative}
.fp-times{display:flex;justify-content:space-between;font-size:.68rem;color:var(--bs-secondary-color);font-variant-numeric:tabular-nums;margin-top:2px}
.fp-controls{display:flex;align-items:center;justify-content:space-between;margin-top:12px}
.fp-play{width:66px;height:66px;border-radius:50%;border:0;background:var(--bs-primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:2rem;padding:0;transition:transform .12s;box-shadow:0 8px 24px rgba(var(--bs-primary-rgb),.4);cursor:pointer}
.fp-play:active{transform:scale(.93)}
.fp-play i{line-height:1;display:block;padding-left:2px}
.fp-play i.bi-pause-fill{padding-left:0}
.fp-volume{display:flex;align-items:center;gap:10px;margin-top:16px;padding:0 4px}
.fp-volume i{font-size:.9rem;color:var(--bs-secondary-color);flex:0 0 auto}
.fp-actions{display:flex;justify-content:center;gap:8px;margin-top:16px;flex-wrap:wrap}
.fp-list-head{display:flex;align-items:center;gap:6px;padding:6px 14px 6px 18px;border-bottom:1px solid var(--mm-divider);flex:0 0 auto}
.fp-list-title{font-size:.66rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:var(--bs-secondary-color)}
.fp-list-actions{margin-left:auto;display:flex;gap:2px}
.q-row{display:flex;align-items:center;gap:10px;padding:7px 12px 7px 8px;border-radius:var(--r-md);transition:background .13s;cursor:pointer}
.q-row:hover{background:var(--mm-surface-2)}
.q-row.active{background:rgba(var(--bs-primary-rgb),.13)}
.q-row.active .row-title{color:var(--bs-primary)}
.q-row.dragging{opacity:.5}
.q-row .q-drag{display:flex;flex-direction:column;gap:0;flex:0 0 auto;width:20px;opacity:.7}
.q-row .q-drag button{border:0;background:transparent;color:var(--bs-secondary-color);width:20px;height:16px;padding:0;border-radius:4px;cursor:pointer;display:flex;align-items:center;justify-content:center}
.q-row .q-drag button:disabled{opacity:.2}
.q-row .q-drag button i{font-size:.7rem}
.q-row .row-art{width:40px;height:40px}
.q-bars{display:inline-flex;align-items:flex-end;gap:2px;height:14px;flex:0 0 auto}
.q-bars span{width:2px;background:var(--bs-primary);border-radius:1px;transform-origin:bottom;animation:eqbar .9s ease-in-out infinite}
.q-bars span:nth-child(1){height:40%;animation-delay:-.6s}
.q-bars span:nth-child(2){height:100%;animation-delay:-.4s}
.q-bars span:nth-child(3){height:60%;animation-delay:-.2s}
.q-bars span:nth-child(4){height:80%;animation-delay:0s}
.fp-lyrics-body{padding:20px 14px 60px;font-size:.86rem;line-height:1.85;white-space:pre-wrap;color:var(--bs-body-color)}
.fp-lyrics-body.synced{display:flex;flex-direction:column;gap:2px;padding:22px 8px 80px;white-space:normal}
.fp-lyric-line{padding:9px 18px;border-radius:var(--r-sm);font-size:.92rem;font-weight:500;line-height:1.5;color:rgba(var(--bs-body-color-rgb),.42);border-left:3px solid transparent;transition:color .35s,background .35s,transform .35s,font-size .35s,font-weight .35s,border-color .35s;cursor:pointer;white-space:pre-wrap;transform-origin:left center;content-visibility:auto;contain-intrinsic-size:auto 2.6em}
.fp-lyric-line.passed{color:rgba(var(--bs-body-color-rgb),.32)}
.fp-lyric-line.active{color:var(--bs-body-color);font-weight:800;font-size:1.08rem;background:linear-gradient(90deg,rgba(var(--bs-primary-rgb),.18),rgba(var(--bs-primary-rgb),.02) 72%,transparent);border-left-color:var(--bs-primary);padding-left:15px;text-shadow:0 0 22px rgba(var(--bs-primary-rgb),.4)}
.fp-lyric-line.blank{pointer-events:none;height:10px;padding:0;border:0}
.fp-lyrics-empty{padding:60px 24px;text-align:center;color:var(--bs-secondary-color);font-size:.85rem;line-height:1.6}
.fp-lyrics-empty i{display:block;font-size:2.6rem;opacity:.45;margin-bottom:12px}
input[type=range].mm-range{-webkit-appearance:none;appearance:none;width:100%;height:20px;background:transparent;margin:0;display:block;cursor:pointer;position:relative}
input[type=range].mm-range::-webkit-slider-runnable-track{height:4px;border-radius:var(--r-full);background:linear-gradient(to right,var(--bs-primary) var(--p,0%),var(--mm-surface-3) var(--p,0%))}
input[type=range].mm-range::-webkit-slider-thumb{-webkit-appearance:none;width:13px;height:13px;border-radius:50%;background:#fff;margin-top:-4.5px;box-shadow:0 1px 4px rgba(0,0,0,.35)}
input[type=range].mm-range::-moz-range-track{height:4px;border-radius:var(--r-full);background:var(--mm-surface-3)}
input[type=range].mm-range::-moz-range-progress{height:4px;border-radius:var(--r-full);background:var(--bs-primary)}
input[type=range].mm-range::-moz-range-thumb{width:13px;height:13px;border:0;border-radius:50%;background:#fff}
input[type=range].mm-range.vol::-webkit-slider-runnable-track{background:linear-gradient(to right,var(--bs-secondary-color) var(--p,0%),var(--mm-surface-3) var(--p,0%))}
input[type=range].mm-range.vol::-moz-range-progress{background:var(--bs-secondary-color)}
.offcanvas.mm-sheet{height:auto;max-height:90vh;border-radius:var(--r-xl) var(--r-xl) 0 0;border:0;padding-bottom:var(--safe-bot);background:var(--bs-body-bg);z-index:1090!important;max-width:600px;margin:0 auto}
.offcanvas-backdrop{z-index:1085!important}
.sheet-handle{width:38px;height:4px;border-radius:var(--r-full);background:rgba(var(--bs-body-color-rgb),.22);margin:10px auto 4px}
.sheet-title{font-size:.72rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--bs-secondary-color);padding:8px 18px 6px}
.sheet-item{display:flex;align-items:center;gap:14px;width:100%;border:0;background:transparent;color:var(--bs-body-color);text-align:left;padding:13px 18px;font-size:.9rem;font-weight:600;border-radius:var(--r-md);transition:background .13s;cursor:pointer}
.sheet-item:hover{background:var(--mm-surface-2)}
.sheet-item>i:first-child{font-size:1.18rem;width:24px;text-align:center;flex:0 0 auto;color:var(--bs-secondary-color)}
.sheet-item.danger{color:var(--bs-danger)}
.sheet-item.danger>i:first-child{color:var(--bs-danger)}
.sheet-item.telegram>i:first-child{color:#29abe2}
.sheet-item .sub{font-size:.72rem;color:var(--bs-secondary-color);font-weight:400}
.settings-section{padding:8px 6px 4px}
.settings-section-title{font-size:.66rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:var(--bs-secondary-color);padding:10px 14px 6px}
.settings-row{display:flex;align-items:center;gap:14px;padding:12px 14px;border-radius:var(--r-sm)}
.settings-row>i:first-child{font-size:1.15rem;width:22px;text-align:center;color:var(--bs-secondary-color);flex:0 0 auto}
.settings-row .sr-body{flex:1;min-width:0}
.settings-row .sr-title{font-size:.88rem;font-weight:600}
.settings-row .sr-sub{font-size:.72rem;color:var(--bs-secondary-color);margin-top:2px}
.settings-row .sr-control{flex:0 0 auto;display:flex;align-items:center;gap:8px}
.settings-row .sr-value{font-size:.8rem;color:var(--bs-secondary-color);font-weight:700;font-variant-numeric:tabular-nums;min-width:38px;text-align:right}
.mm-switch{position:relative;display:inline-block;width:42px;height:24px;flex:0 0 auto;cursor:pointer}
.mm-switch input{opacity:0;width:0;height:0}
.mm-switch .slider{position:absolute;inset:0;border-radius:var(--r-full);background:var(--mm-surface-3);transition:background .18s}
.mm-switch .slider::before{content:'';position:absolute;left:2px;top:2px;width:20px;height:20px;border-radius:50%;background:#fff;transition:transform .18s var(--mm-ease);box-shadow:0 1px 2px rgba(0,0,0,.2)}
.mm-switch input:checked+.slider{background:var(--bs-primary)}
.mm-switch input:checked+.slider::before{transform:translateX(18px)}
.seg-ctl{display:inline-flex;border-radius:var(--r-sm);background:var(--mm-surface-2);padding:3px;gap:2px}
.seg-ctl button{border:0;background:transparent;color:var(--bs-secondary-color);font-size:.76rem;font-weight:700;padding:5px 10px;border-radius:var(--r-xs);cursor:pointer;transition:all .12s}
.seg-ctl button.on{background:var(--bs-body-bg);color:var(--bs-body-color);box-shadow:var(--el-1)}
.crawl-card{margin:8px 16px 0;border-radius:var(--r-md);padding:12px 14px;display:flex;align-items:center;gap:12px;font-size:.82rem}
.crawl-card.pending{background:rgba(var(--bs-info-rgb),.12)}
.crawl-card.ready{background:rgba(var(--bs-success-rgb),.13)}
.crawl-card.fail{background:rgba(var(--bs-danger-rgb),.12)}
.crawl-card.idle{background:rgba(var(--bs-primary-rgb),.11)}
.crawl-card.preview{background:rgba(var(--mm-preview-rgb),.11)}
.crawl-card.longtip{background:rgba(var(--bs-warning-rgb),.13)}
.crawl-card .cc-body{flex:1;min-width:0}
.crawl-card .cc-title{font-weight:700;font-size:.85rem}
.crawl-card .cc-sub{color:var(--bs-secondary-color);font-size:.72rem;margin-top:2px}
.crawl-card .progress{height:5px;border-radius:var(--r-full);background:var(--mm-surface-3);margin-top:8px;overflow:hidden}
.crawl-card .progress-bar{transition:width .4s ease}
.hero{padding:4px 20px 0;padding-bottom:30px;padding-top:10px;}
.hero-art{width:200px;height:200px;max-width:58vw;max-height:58vw;border-radius:var(--r-lg);object-fit:cover;margin-bottom:16px;display:block;background:var(--mm-surface-2);box-shadow:var(--el-3)}
.hero-art.round{border-radius:50%}
.hero-art-ph{display:flex;align-items:center;justify-content:center;color:var(--bs-secondary-color);font-size:4rem}
.hero-title{font-size:1.22rem;font-weight:800;line-height:1.3;letter-spacing:-.02em}
.hero-sub{font-size:.82rem;color:var(--bs-secondary-color);margin-top:4px}
.action-bar{display:flex;gap:10px;padding:16px 20px 12px;flex-wrap:wrap}
.action-bar.scrollable{flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none}
.action-bar.scrollable::-webkit-scrollbar{display:none}
.action-bar.scrollable>*{flex:0 0 auto}
.lyrics-card{margin:20px 16px 0;border-radius:var(--r-lg);background:var(--mm-surface);overflow:hidden}
.lyrics-head{display:flex;align-items:center;gap:8px;padding:12px 16px;font-size:.78rem;font-weight:800;border-bottom:1px solid var(--mm-divider)}
.lyrics-head .lyrics-badge{font-size:.6rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;padding:3px 7px;border-radius:var(--r-xs);background:rgba(var(--bs-primary-rgb),.16);color:var(--bs-primary)}
.lyrics-body{padding:14px 16px;font-size:.85rem;line-height:1.75;white-space:pre-wrap;color:var(--bs-body-color);max-height:340px;overflow-y:auto;scrollbar-width:none;position:relative;overscroll-behavior:contain;-webkit-overflow-scrolling:touch}
.lyrics-body::-webkit-scrollbar{display:none}
.lyrics-body.synced{display:flex;flex-direction:column;gap:2px;padding:16px 4px;white-space:normal}
.lyric-line{padding:8px 14px;border-radius:var(--r-sm);font-size:.88rem;font-weight:500;line-height:1.5;color:rgba(var(--bs-body-color-rgb),.42);border-left:3px solid transparent;transition:color .3s,background .3s,font-size .3s,font-weight .3s;cursor:pointer;white-space:pre-wrap;content-visibility:auto;contain-intrinsic-size:auto 2.4em}
.lyric-line.passed{color:rgba(var(--bs-body-color-rgb),.32)}
.lyric-line.active{color:var(--bs-body-color);font-weight:800;font-size:1rem;padding-left:11px}
.lyric-line.blank{pointer-events:none;height:8px;padding:0;border:0}
.install-step{display:flex;gap:12px;align-items:flex-start;padding:10px 4px}
.install-step .n{flex:0 0 auto;width:24px;height:24px;border-radius:50%;background:rgba(var(--bs-primary-rgb),.16);color:var(--bs-primary);display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:800}
.install-step .t{font-size:.84rem;line-height:1.45}
.offline-banner{display:none;align-items:center;gap:8px;padding:6px 14px;font-size:.72rem;font-weight:700;background:rgba(var(--bs-warning-rgb),.16);color:var(--bs-warning);border-bottom:1px solid rgba(var(--bs-warning-rgb),.22)}
.offline-banner.on{display:flex}
.toast-container{z-index:3000}
/* FIXED toast radius */
.toast{border-radius:var(--r-lg)!important;overflow:hidden;box-shadow:var(--el-3)!important}
.toast .toast-body{padding:11px 14px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.ab-chip{display:inline-flex;align-items:center;gap:4px;font-size:.66rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;padding:3px 7px;border-radius:5px;background:rgba(var(--bs-warning-rgb),.18);color:var(--bs-warning);cursor:pointer}
.ab-chip.off{display:none}
.accent-row{display:flex;gap:10px;padding:4px 14px 8px;flex-wrap:wrap}
.accent-dot{width:26px;height:26px;border-radius:50%;cursor:pointer;border:none;padding:0;transition:transform .12s,border-color .12s;position:relative}
.accent-dot:active{transform:scale(.92)}
.accent-dot.on{box-shadow:var(--bs-body-color) 0 0 0 2px;}
.accent-dot.auto i{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.7rem;text-shadow:0 1px 3px rgba(0,0,0,.5)}
.stat-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;padding:8px 16px 0}
.stat-card{background:var(--mm-surface);border-radius:var(--r-md);padding:14px;transition:transform .15s}
.stat-card:hover{transform:translateY(-2px)}
.stat-card .n{font-size:1.5rem;font-weight:800;letter-spacing:-.02em;line-height:1.1}
.stat-card .l{font-size:.68rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--bs-secondary-color);margin-top:4px}
.hist-row{display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:var(--r-md);transition:background .13s}
.hist-row:hover{background:var(--mm-surface)}
.hist-row .row-art{width:44px;height:44px}
.week-chart{display:flex;gap:6px;align-items:flex-end;padding:4px 16px 0;height:100px}
.week-day{flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;min-width:0}
.week-bar{width:100%;max-width:36px;background:var(--mm-accent-grad);border-radius:var(--r-xs);min-height:4px;transition:height .35s var(--mm-ease)}
.week-lbl{font-size:.6rem;font-weight:700;text-transform:uppercase;color:var(--bs-secondary-color);letter-spacing:.05em}
.lib-tabs{display:flex;gap:6px;padding:10px 12px;position:sticky;top:0;z-index:10;backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border-bottom:1px solid var(--mm-divider);overflow-x:auto;scrollbar-width:none}
.lib-tabs::-webkit-scrollbar{display:none}
.lib-tabs .lt{position:relative;display:inline-flex;align-items:center;gap:6px;padding:9px 14px;border-radius:var(--r-full);background:var(--mm-surface-2);color:var(--bs-secondary-color);font-size:.8rem;font-weight:700;white-space:nowrap;flex:0 0 auto;transition:all .18s var(--mm-ease);border:1px solid transparent;cursor:pointer}
.lib-tabs .lt i{font-size:1rem;line-height:1}
.lib-tabs .lt.on{background:var(--bs-primary);color:#fff;border-color:var(--bs-primary);}
.lib-tabs .lt-badge{min-width:18px;height:18px;padding:0 5px;border-radius:var(--r-full);background:rgba(var(--bs-primary-rgb),.18);color:var(--bs-primary);font-size:.62rem;font-weight:800;line-height:18px;text-align:center}
.lib-tabs .lt.on .lt-badge{background:rgba(255,255,255,.24);color:#fff}
.app-root{display:flex;flex-direction:column;height:100dvh;overflow:hidden}
.content-col{flex:1 1 auto;display:flex;flex-direction:column;min-height:0;overflow:hidden}
.sidebar,.right-panel,.player-bar{display:none}
#main{margin:0 auto;width:100%;max-width:700px}
.recent-rail{display:flex;flex-direction:column;gap:4px;padding:0 6px}
.recent-rail .q-row .row-art{width:36px;height:36px}
.smart-row{display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:var(--r-md);transition:background .13s;cursor:pointer}
.smart-row:hover{background:var(--mm-surface)}
.smart-row .row-art-ph{width:46px;height:46px;margin-right:10px}
@media (min-width:1024px){
  .app-root{display:grid;grid-template-columns:240px minmax(0,1fr) 340px;grid-template-rows:1fr 88px;grid-template-areas:"sidebar main right" "player player player";height:100dvh;overflow:hidden}
  .content-col{grid-area:main;border-left:1px solid var(--mm-divider);border-right:1px solid var(--mm-divider)}
  .app-bar{padding:14px 24px;border-bottom:1px solid var(--mm-divider)}
  .app-bar .brand{display:none!important}
  .app-bar .page-title{display:block!important;font-size:1.15rem;font-weight:800;letter-spacing:-.02em}
  .app-main{padding-bottom:24px}
  .app-bottom{display:none!important}
  #installBtn{display:none!important}
  .fp-expand-btn{display:none!important}
  .sidebar{grid-area:sidebar;display:flex;flex-direction:column;overflow:hidden;min-height:0}
  .sb-brand{display:flex;align-items:center;gap:10px;padding:22px 20px 18px;font-weight:800;font-size:1.15rem;letter-spacing:-.02em}
  .sb-brand i{color:inherit!important;font-size:1.65rem}
  .sb-nav{padding:4px 10px}
  .sb-link{display:flex;align-items:center;gap:14px;padding:10px 12px;border-radius:var(--r-sm);color:var(--bs-secondary-color);font-weight:600;font-size:.86rem;transition:all .15s;cursor:pointer}
  .sb-link:hover{background:var(--mm-surface);color:var(--bs-body-color)}
  .sb-link.active{color:var(--bs-body-color);background:var(--mm-surface)}
  .sb-link.active i{color:var(--bs-primary)}
  .sb-link i{font-size:1.1rem;width:20px;text-align:center;flex:0 0 auto;transition:color .15s}
  .sb-link span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .sb-section{display:flex;flex-direction:column;min-height:0;padding-top:14px}
  .sb-section.sb-playlists{flex:1 1 auto;min-height:0;overflow:hidden}
  .sb-section-head{display:flex;align-items:center;gap:8px;padding:6px 12px 6px 22px;font-size:.68rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:var(--bs-secondary-color)}
  .sb-section-head span{flex:1}
  .sb-list{padding:2px 10px;overflow-y:auto;scrollbar-width:none}
  .sb-list::-webkit-scrollbar{display:none}
  .sb-section.sb-playlists .sb-list{flex:1 1 auto;min-height:0}
  .sb-foot{border-top:1px solid var(--mm-divider);padding:8px;margin-top:auto}
  .sb-install{display:flex;align-items:center;gap:14px;width:100%;padding:10px 12px;border:0;background:transparent;color:var(--bs-secondary-color);font-weight:600;font-size:.85rem;border-radius:var(--r-sm);cursor:pointer;text-align:left;font-family:inherit;transition:all .15s}
  .sb-install:hover{background:var(--mm-surface);color:var(--bs-body-color)}
  .sb-install i{font-size:1.1rem;width:20px;text-align:center;flex:0 0 auto}
  .right-panel{grid-area:right;display:flex;flex-direction:column;overflow:hidden;min-height:0}
  .rp-head{display:flex;align-items:center;gap:2px;padding:18px 14px 8px 20px}
  .rp-tabs{display:flex;gap:2px;flex:1;min-width:0;background:var(--mm-surface-2);border-radius:var(--r-sm);padding:3px}
  .rp-tab{flex:1;min-width:0;display:flex;align-items:center;justify-content:center;gap:4px;border:0;background:transparent;color:var(--bs-secondary-color);font-size:.68rem;font-weight:800;padding:7px 6px;border-radius:var(--r-xs);cursor:pointer;transition:background .15s,color .15s;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .rp-tab.active{background:var(--bs-body-bg);color:var(--bs-body-color);box-shadow:var(--el-1)}
  .rp-tab i{font-size:.9rem;flex:0 0 auto}
  .rp-tab .rp-badge{min-width:16px;height:16px;padding:0 5px;border-radius:var(--r-full);background:var(--bs-primary);color:#fff;font-size:.58rem;font-weight:800;line-height:16px;text-align:center;display:none}
  .rp-tab .rp-badge.on{display:inline-block}
  .rp-body{flex:1;overflow-y:auto;scrollbar-width:none;padding:6px 10px 20px;min-height:0}
  .rp-body::-webkit-scrollbar{display:none}
  .rp-now{padding:8px 6px}
  .rp-now-art{width:100%;aspect-ratio:1;border-radius:var(--r-lg);object-fit:cover;margin-bottom:16px;box-shadow:var(--el-3);background:var(--mm-surface-2)}
  .rp-now-title{font-size:1.1rem;font-weight:800;line-height:1.3;margin-bottom:3px}
  .rp-now-artist{font-size:.82rem;color:var(--bs-secondary-color)}
  .rp-quick{display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin-top:14px;padding:0 4px}
  .rp-quick button{display:flex;flex-direction:column;align-items:center;gap:4px;border:0;background:var(--mm-surface);color:var(--bs-body-color);border-radius:var(--r-md);padding:10px 4px;cursor:pointer;font-size:.62rem;font-weight:700;transition:background .12s,transform .12s}
  .rp-quick button:hover{background:var(--mm-surface-2)}
  .rp-quick button:active{transform:scale(.95)}
  .rp-quick button i{font-size:1.15rem;color:var(--bs-primary)}
  .rp-section-title{font-size:.66rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:var(--bs-secondary-color);padding:18px 10px 8px}
  .player-bar{grid-area:player;display:grid;grid-template-columns:minmax(200px,280px) 1fr minmax(200px,280px);align-items:center;gap:16px;padding:10px 20px;border-top:1px solid var(--mm-divider)}
  .pb-left{display:flex;align-items:center;gap:12px;min-width:0}
  .pb-art{width:52px;height:52px;border-radius:var(--r-sm);object-fit:cover;background:var(--mm-surface-2);flex:0 0 auto;transition:opacity .2s}
  .pb-info{min-width:0;flex:1}
  .pb-title{font-size:.85rem;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;line-height:1.3;cursor:pointer;border-bottom:1px solid transparent;transition:border-color .15s}
  .pb-title:hover{border-bottom-color:currentColor}
  .pb-artist{font-size:.74rem;color:var(--bs-secondary-color);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;line-height:1.3;margin-top:2px;cursor:pointer;border-bottom:1px solid transparent;transition:border-color .15s}
  .pb-artist:hover{border-bottom-color:currentColor;color:var(--bs-primary)}
  .pb-center{display:flex;flex-direction:column;align-items:center;gap:5px;max-width:640px;width:100%;margin:0 auto;min-width:0}
  .pb-controls{display:flex;align-items:center;gap:6px}
  .pb-play{width:36px;height:36px;border-radius:50%;border:0;background:var(--bs-body-color);color:var(--bs-body-bg);display:flex;align-items:center;justify-content:center;font-size:1.2rem;cursor:pointer;transition:transform .12s var(--mm-ease);margin:0 4px}
  .pb-play:hover{transform:scale(1.06)}
  .pb-play:active{transform:scale(.94)}
  .pb-play i{padding-left:2px;line-height:1}
  .pb-play i.bi-pause-fill{padding-left:0}
  .pb-seek{display:flex;align-items:center;gap:10px;width:100%;font-size:.66rem;color:var(--bs-secondary-color);font-variant-numeric:tabular-nums;position:relative}
  .pb-seek input{flex:1;height:14px}
  .pb-tooltip{position:absolute;top:-24px;background:var(--bs-body-bg);border:1px solid var(--mm-divider);border-radius:6px;padding:2px 8px;font-size:.68rem;font-weight:700;pointer-events:none;opacity:0;transition:opacity .12s;color:var(--bs-body-color);white-space:nowrap;transform:translateX(-50%)}
  .pb-tooltip.on{opacity:1}
  .pb-right{display:flex;align-items:center;gap:6px;justify-content:flex-end}
  .pb-vol{display:flex;align-items:center;gap:6px;width:110px}
  .pb-vol i{font-size:.85rem;color:var(--bs-secondary-color);flex:0 0 auto}
  .pb-vol input{flex:1;height:14px}
}
@media (min-width:1024px) and (max-width:1280px){.app-root{grid-template-columns:220px minmax(0,1fr) 300px}}
@media (min-width:1600px){.app-root{grid-template-columns:260px minmax(0,1fr) 380px}}
.shortcut-overlay{position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:2000;display:none;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(6px)}
.shortcut-overlay.on{display:flex}
.shortcut-panel{background:var(--bs-body-bg);border-radius:var(--r-lg);padding:24px;max-width:520px;width:100%;max-height:80vh;overflow-y:auto;box-shadow:var(--el-4)}
.shortcut-panel h5{margin:0 0 14px;font-weight:800;font-size:1.1rem}
.shortcut-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px 18px}
.shortcut-row{display:flex;align-items:center;gap:10px;font-size:.82rem}
.shortcut-row kbd{display:inline-flex;align-items:center;justify-content:center;min-width:26px;height:24px;padding:0 8px;border-radius:6px;background:var(--mm-surface-2);font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.72rem;font-weight:800;border-bottom:2px solid var(--mm-divider);color:var(--bs-body-color)}
</style>
</head>
<body>
<div id="appBg" class="app-bg"></div>
<div class="app-root">

<aside class="sidebar" id="sidebar">
  <a href="<?=$e($__scope)?>" data-link class="sb-brand"><i class="bi bi-soundwave"></i><span>MusicMan</span></a>
  <nav class="sb-nav">
    <a href="<?=$e($__scope)?>" data-link data-nav="home" class="sb-link"><i class="bi bi-house-door"></i><span>Home</span></a>
    <a href="<?=$e($__scope)?>search" data-link data-nav="search" class="sb-link"><i class="bi bi-search"></i><span>Search</span></a>
  </nav>
  <div class="sb-section">
    <div class="sb-section-head"><span>Your Library</span><button class="icon-btn sm" onclick="newPlaylistPrompt()" title="New playlist"><i class="bi bi-plus-lg"></i></button></div>
    <div class="sb-list">
      <a href="<?=$e($__scope)?>library/likes" data-link class="sb-link" data-lib="likes"><i class="bi bi-heart-fill"></i><span>Liked Songs</span></a>
      <a href="<?=$e($__scope)?>library/following" data-link class="sb-link" data-lib="following"><i class="bi bi-people-fill"></i><span>Following</span></a>
      <a href="<?=$e($__scope)?>library/playlists" data-link class="sb-link" data-lib="playlists"><i class="bi bi-music-note-list"></i><span>Playlists</span></a>
      <a href="<?=$e($__scope)?>library/downloads" data-link class="sb-link" data-lib="downloads"><i class="bi bi-cloud-check-fill"></i><span>Offline</span></a>
      <a href="<?=$e($__scope)?>library/stats" data-link class="sb-link" data-lib="stats"><i class="bi bi-bar-chart-fill"></i><span>Stats</span></a>
      <a href="<?=$e($__scope)?>library/history" data-link class="sb-link" data-lib="history"><i class="bi bi-clock-history"></i><span>History</span></a>
      <div class="sb-section sb-playlists">
        <div class="sb-section-head" style="padding-left:12px"><span>Playlists</span></div>
        <div id="sbPlaylists"></div>
      </div>
    </div>
  </div>
  <div class="sb-foot">
    <button class="sb-install" id="sbInstall" onclick="promptInstall()"><i class="bi bi-box-arrow-down"></i><span>Install app</span></button>
    <button class="sb-install" onclick="openShortcuts()"><i class="bi bi-keyboard"></i><span>Shortcuts</span></button>
    <button class="sb-install" onclick="openSettings()"><i class="bi bi-gear"></i><span>Settings</span></button>
  </div>
</aside>

<div class="content-col">
  <header class="app-bar" id="appBar">
    <a class="brand" href="<?=$e($__scope)?>" data-link><i class="bi bi-soundwave"></i><span id="brandText">MusicMan</span></a>
    <div class="page-title flex-grow-1 min-w-0 d-none" id="barTitle"></div>
    <div class="ms-auto d-flex align-items-center gap-1">
      <span id="netPill" class="net-pill"><i class="bi bi-wifi"></i><span>Online</span></span>
      <button class="icon-btn" id="installBtn" onclick="promptInstall()" aria-label="Install app" style="display:none"><i class="bi bi-box-arrow-down"></i></button>
      <button class="icon-btn" id="dlIndicator" onclick="go('/library/downloads')" aria-label="Downloads" style="display:none;position:relative"><i class="bi bi-cloud-arrow-down"></i><span class="tab-badge on" id="dlIndicatorCount" style="position:absolute;top:2px;right:2px;left:auto;margin:0">0</span></button>
      <button class="icon-btn" onclick="openSettings()" aria-label="Settings"><i class="bi bi-gear"></i></button>
    </div>
  </header>
  <div id="offlineBanner" class="offline-banner"><i class="bi bi-wifi-off"></i><span>You're offline — playing from saved tracks</span></div>
  <main id="main" class="app-main scroll" role="main" aria-live="polite"></main>
</div>

<aside class="right-panel" id="rightPanel">
  <div class="rp-head">
    <div class="rp-tabs" id="rpTabs">
      <button class="rp-tab active" data-rp-tab="now"><i class="bi bi-play-circle"></i><span>Now</span></button>
      <button class="rp-tab" data-rp-tab="queue"><i class="bi bi-list-ul"></i><span>Queue</span><span class="rp-badge" id="rpQueueBadge">0</span></button>
      <button class="rp-tab" data-rp-tab="lyrics"><i class="bi bi-music-note-list"></i><span>Lyrics</span></button>
      <button class="rp-tab" data-rp-tab="info"><i class="bi bi-info-circle"></i><span>Info</span></button>
    </div>
  </div>
  <div class="rp-body" id="rpBody"></div>
</aside>

<footer class="player-bar" id="playerBar">
  <div class="pb-left">
    <img id="pbArt" class="pb-art" alt="">
    <div class="pb-info">
      <div id="pbTitle" class="pb-title">—</div>
      <div id="pbArtist" class="pb-artist">—</div>
    </div>
    <button class="icon-btn sm" id="pbLikeBtn" onclick="toggleLikeCurrent()" aria-label="Like"><i id="pbLike" class="bi bi-heart"></i></button>
  </div>
  <div class="pb-center">
    <div class="pb-controls">
      <button class="icon-btn sm" id="pbShuffleBtn" onclick="toggleShuffle()" aria-label="Shuffle"><i class="bi bi-shuffle"></i></button>
      <button class="icon-btn" onclick="prevTrack()" aria-label="Previous"><i class="bi bi-skip-start-fill"></i></button>
      <button class="pb-play" onclick="togglePlay()" aria-label="Play/pause"><i id="pbPlayIcon" class="bi bi-play-fill"></i></button>
      <button class="icon-btn" onclick="nextTrack()" aria-label="Next"><i class="bi bi-skip-end-fill"></i></button>
      <button class="icon-btn sm" id="pbRepeatBtn" onclick="cycleRepeat()" aria-label="Repeat"><i class="bi bi-repeat"></i></button>
    </div>
    <div class="pb-seek" id="pbSeekWrap">
      <span id="pbCur">0:00</span>
      <input id="pbSeek" class="mm-range" type="range" min="0" max="1000" value="0" aria-label="Seek">
      <span id="pbDur">0:00</span>
      <span class="pb-tooltip" id="pbTip">0:00</span>
    </div>
  </div>
  <div class="pb-right">
    <div class="pb-vol"><i class="bi bi-volume-up"></i><input id="pbVol" class="mm-range vol" type="range" min="0" max="100" value="85" aria-label="Volume"></div>
    <button class="icon-btn sm" onclick="openSleepTimer()" title="Sleep timer"><i class="bi bi-moon" id="pbSleepIcon"></i></button>
    <button class="icon-btn sm fp-expand-btn" onclick="openFullPlayer()" title="Expand player"><i class="bi bi-arrows-angle-expand"></i></button>
  </div>
</footer>

<div class="app-bottom">
  <div id="mini" class="mini d-none">
    <div class="mini-progress"><div id="miniBar"></div></div>
    <div class="mini-row">
      <div class="d-flex align-items-center gap-2 flex-grow-1 min-w-0" onclick="openFullPlayer()">
        <img id="miniArt" class="mini-art" alt="">
        <div class="min-w-0 flex-grow-1">
          <div id="miniTitle" class="mini-title text-truncate">—</div>
          <div class="d-flex align-items-center gap-2">
            <div class="mini-eq" id="miniEq"><span></span><span></span><span></span><span></span></div>
            <div id="miniArtist" class="mini-sub text-truncate">—</div>
          </div>
        </div>
      </div>
      <button class="icon-btn" onclick="event.stopPropagation();togglePlay()" aria-label="Play/pause"><i id="miniPlayIcon" class="bi bi-play-fill"></i></button>
      <button class="icon-btn" onclick="event.stopPropagation();nextTrack()" aria-label="Next"><i class="bi bi-skip-forward-fill"></i></button>
    </div>
  </div>
  <nav class="tabbar" role="navigation">
    <a data-nav="home" href="<?=$e($__scope)?>" data-link><i class="bi bi-house-door"></i><span>Home</span></a>
    <a data-nav="search" href="<?=$e($__scope)?>search" data-link><i class="bi bi-search"></i><span>Search</span></a>
    <a data-nav="library" href="<?=$e($__scope)?>library/likes" data-link><i class="bi bi-collection"></i><span>Library</span><span class="tab-badge" id="libBadge">0</span></a>
  </nav>
</div>
</div>

<div id="full" class="full-player" aria-hidden="true">
  <div id="fpBg" class="fp-bg"></div>
  <div class="fp-scrim"></div>
  <div class="fp-inner">
    <div class="fp-head">
      <button class="icon-btn" onclick="closeFullPlayer()" aria-label="Close"><i class="bi bi-chevron-down" style="font-size:1.5rem"></i></button>
      <div class="fp-head-tabs" id="fpHeadTabs">
        <button class="fp-head-tab active" data-fp-tab="now" onclick="setFpTab('now')"><i class="bi bi-play-circle"></i><span>Playing</span></button>
        <button class="fp-head-tab" data-fp-tab="queue" onclick="setFpTab('queue')"><i class="bi bi-list-ul"></i><span>Queue</span><span class="rs-badge" id="fpQueueBadge">0</span></button>
        <button class="fp-head-tab" data-fp-tab="lyrics" onclick="setFpTab('lyrics')"><i class="bi bi-music-note-list"></i><span>Lyrics</span></button>
      </div>
      <button class="icon-btn" onclick="fpMoreMenu()" aria-label="More"><i class="bi bi-three-dots"></i></button>
    </div>
    <div class="fp-tabbody scroll" id="fpTabBody"></div>
  </div>
</div>

<div class="offcanvas offcanvas-bottom mm-sheet" tabindex="-1" id="sheetTrack"><div class="sheet-handle"></div><div id="sheetTrackBody" class="pb-3"></div></div>
<div class="offcanvas offcanvas-bottom mm-sheet" tabindex="-1" id="sheetPl"><div class="sheet-handle"></div><div class="sheet-title" id="sheetPlTitle">Add to playlist</div><div id="sheetPlBody" class="pb-2" style="max-height:60vh;overflow-y:auto"></div><div class="px-3 pt-1 pb-2"><button class="pill-btn primary w-100 justify-content-center" onclick="newPlaylistPrompt()"><i class="bi bi-plus-lg"></i> New playlist</button></div></div>
<div class="offcanvas offcanvas-bottom mm-sheet" tabindex="-1" id="sheetPrompt"><div class="sheet-handle"></div><div class="px-3 pt-2 pb-3"><div id="promptTitle" class="fw-bold mb-3">New playlist</div><input id="promptInput" type="text" class="form-control form-control-lg mb-3" placeholder="Name" autocomplete="off" style="border-radius:12px"><div class="d-flex gap-2"><button class="pill-btn flex-fill justify-content-center" data-bs-dismiss="offcanvas">Cancel</button><button class="pill-btn primary flex-fill justify-content-center" onclick="submitPrompt()">Save</button></div></div></div>
<div class="offcanvas offcanvas-bottom mm-sheet" tabindex="-1" id="sheetConfirm"><div class="sheet-handle"></div><div class="px-3 pt-2 pb-3"><div id="cfTitle" class="fw-bold mb-2">Confirm</div><div id="cfBody" class="text-secondary small mb-3"></div><div class="d-flex gap-2"><button class="pill-btn flex-fill justify-content-center" data-bs-dismiss="offcanvas">Cancel</button><button class="pill-btn flex-fill justify-content-center" id="cfOkBtn" style="background:var(--bs-danger);color:#fff">Delete</button></div></div></div>
<div class="offcanvas offcanvas-bottom mm-sheet" tabindex="-1" id="sheetSleep"><div class="sheet-handle"></div><div class="sheet-title">Sleep timer</div><div class="px-2 pb-3"><button class="sheet-item" onclick="setSleepTimer(5)"><i class="bi bi-clock"></i><span>5 minutes</span></button><button class="sheet-item" onclick="setSleepTimer(15)"><i class="bi bi-clock"></i><span>15 minutes</span></button><button class="sheet-item" onclick="setSleepTimer(30)"><i class="bi bi-clock"></i><span>30 minutes</span></button><button class="sheet-item" onclick="setSleepTimer(60)"><i class="bi bi-clock"></i><span>60 minutes</span></button><button class="sheet-item" onclick="setSleepTimer(0)"><i class="bi bi-x-circle"></i><span>Cancel timer</span></button></div></div>

<div class="offcanvas offcanvas-bottom mm-sheet" tabindex="-1" id="sheetSettings">
  <div class="sheet-handle"></div>
  <div class="d-flex align-items-center px-3 pt-1 pb-1"><div class="fw-bold flex-grow-1 ps-1" style="font-size:1.08rem">Settings</div><button class="icon-btn sm" data-bs-dismiss="offcanvas"><i class="bi bi-x-lg"></i></button></div>
  <div class="pb-3" style="overflow-y:auto;max-height:80vh">
    <div class="settings-section">
      <div class="settings-section-title">Appearance</div>
      <div class="settings-row"><i class="bi bi-circle-half"></i><div class="sr-body"><div class="sr-title">Theme</div><div class="sr-sub">Choose your preferred look</div></div><div class="sr-control seg-ctl" id="setTheme"><button data-val="dark">Dark</button><button data-val="light">Light</button></div></div>
      <div class="settings-row"><i class="bi bi-palette"></i><div class="sr-body"><div class="sr-title">Accent color</div><div class="sr-sub">"Auto" matches the current album art</div></div></div>
      <div class="accent-row" id="accentRow"></div>
      <div class="settings-row"><i class="bi bi-moon-stars"></i><div class="sr-body"><div class="sr-title">Auto theme</div><div class="sr-sub">Light in daytime, dark at night</div></div><div class="sr-control"><label class="mm-switch"><input type="checkbox" id="setAutoTheme"><span class="slider"></span></label></div></div>
      <div class="settings-row"><i class="bi bi-easel"></i><div class="sr-body"><div class="sr-title">Ambient background</div><div class="sr-sub">Blurred album art behind the app</div></div><div class="sr-control"><label class="mm-switch"><input type="checkbox" id="setAppBg"><span class="slider"></span></label></div></div>
      <div class="settings-row"><i class="bi bi-stars"></i><div class="sr-body"><div class="sr-title">Animations</div><div class="sr-sub">Smooth transitions and effects</div></div><div class="sr-control"><label class="mm-switch"><input type="checkbox" id="setAnimations"><span class="slider"></span></label></div></div>
    </div>
    <div class="settings-section">
      <div class="settings-section-title">Playback</div>
      <div class="settings-row"><i class="bi bi-shuffle"></i><div class="sr-body"><div class="sr-title">Crossfade</div><div class="sr-sub">Fade out at end of a track</div></div><div class="sr-control seg-ctl" id="setCrossfade"><button data-val="0">Off</button><button data-val="1500">1.5s</button><button data-val="3000">3s</button><button data-val="5000">5s</button></div></div>
      <div class="settings-row"><i class="bi bi-moon-stars"></i><div class="sr-body"><div class="sr-title">Sleep fade</div><div class="sr-sub">Fade out before sleep timer pauses</div></div><div class="sr-control"><label class="mm-switch"><input type="checkbox" id="setSleepFade"><span class="slider"></span></label></div></div>
      <div class="settings-row"><i class="bi bi-music-note-list"></i><div class="sr-body"><div class="sr-title">Auto-scroll lyrics</div><div class="sr-sub">Follow active line automatically</div></div><div class="sr-control"><label class="mm-switch"><input type="checkbox" id="setAutoScroll"><span class="slider"></span></label></div></div>
      <div class="settings-row"><i class="bi bi-recycle"></i><div class="sr-body"><div class="sr-title">Auto-retry downloads</div><div class="sr-sub">Retry automatically on failure</div></div><div class="sr-control"><label class="mm-switch"><input type="checkbox" id="setAutoRetry"><span class="slider"></span></label></div></div>
      <div class="settings-row"><i class="bi bi-cloud-download"></i><div class="sr-body"><div class="sr-title">Auto-save after crawl</div><div class="sr-sub">Download immediately once crawling finishes</div></div><div class="sr-control"><label class="mm-switch"><input type="checkbox" id="setAutoSaveAfterCrawl"><span class="slider"></span></label></div></div>
      <div class="settings-row"><i class="bi bi-play-circle"></i><div class="sr-body"><div class="sr-title">Auto-save played tracks</div><div class="sr-sub">Silently download every track you play</div></div><div class="sr-control"><label class="mm-switch"><input type="checkbox" id="setAutoSavePlayed"><span class="slider"></span></label></div></div>
      <div class="settings-row"><i class="bi bi-wifi"></i><div class="sr-body"><div class="sr-title">Save data</div><div class="sr-sub">Reduce quality and pause downloads on cellular</div></div><div class="sr-control"><label class="mm-switch"><input type="checkbox" id="setSaveData"><span class="slider"></span></label></div></div>
    </div>
    <div class="settings-section">
      <div class="settings-section-title">Backup</div>
      <div id="backupHost"></div>
    </div>
    <div class="settings-section">
      <div class="settings-section-title">Storage</div>
      <div class="settings-row"><i class="bi bi-hdd"></i><div class="sr-body"><div class="sr-title">Offline cache</div><div class="sr-sub" id="setCacheInfo">—</div></div><div class="sr-control"><button class="pill-btn danger sm" onclick="clearAllCache()"><i class="bi bi-trash3"></i> Clear</button></div></div>
      <div class="settings-row"><i class="bi bi-pie-chart"></i><div class="sr-body"><div class="sr-title">Storage usage</div><div class="sr-sub" id="setQuotaInfo">—</div></div></div>
      <div class="settings-row"><i class="bi bi-clock-history"></i><div class="sr-body"><div class="sr-title">Recent searches</div><div class="sr-sub" id="setRecentInfo">—</div></div><div class="sr-control"><button class="pill-btn sm" onclick="clearRecent(); updateSettingsSheet()">Clear</button></div></div>
      <div class="settings-row"><i class="bi bi-arrow-counterclockwise"></i><div class="sr-body"><div class="sr-title">Reset app data</div><div class="sr-sub">Remove all local settings, likes and playlists</div></div><div class="sr-control"><button class="pill-btn danger sm" onclick="resetAppData()">Reset</button></div></div>
    </div>
    <div class="settings-section">
      <div class="settings-section-title">About</div>
      <div class="settings-row"><i class="bi bi-info-circle"></i><div class="sr-body"><div class="sr-title">MusicMan</div><div class="sr-sub">Version 6.8 · PWA</div></div><div class="sr-control"><button class="pill-btn sm" onclick="checkForUpdate()"><i class="bi bi-arrow-repeat"></i> Update</button></div></div>
      <div class="settings-row"><i class="bi bi-telegram"></i><div class="sr-body"><div class="sr-title">Telegram bot</div><div class="sr-sub">Download large files via the bot</div></div><div class="sr-control"><a class="pill-btn telegram sm" href="https://t.me/musicman_official_bot" target="_blank" rel="noopener"><i class="bi bi-telegram"></i> Open</a></div></div>
      <div class="settings-row"><i class="bi bi-keyboard"></i><div class="sr-body"><div class="sr-title">Keyboard shortcuts</div><div class="sr-sub">Press <b>?</b> anywhere for help</div></div><div class="sr-control"><button class="pill-btn sm" onclick="openShortcuts()">View</button></div></div>
    </div>
  </div>
</div>

<div class="offcanvas offcanvas-bottom mm-sheet" tabindex="-1" id="sheetInstall"><div class="sheet-handle"></div><div class="sheet-title">Install MusicMan</div><div class="px-3 pb-3" id="installBody"></div></div>

<div class="shortcut-overlay" id="shortcutOverlay" onclick="if(event.target===this)closeShortcuts()">
  <div class="shortcut-panel">
    <div class="d-flex align-items-center justify-content-between mb-3"><h5>Keyboard shortcuts</h5><button class="icon-btn sm" onclick="closeShortcuts()"><i class="bi bi-x-lg"></i></button></div>
    <div class="shortcut-grid" id="shortcutGrid"></div>
  </div>
</div>

<div class="toast-container position-fixed bottom-0 start-50 translate-middle-x p-3" style="margin-bottom:calc(var(--safe-bot) + 84px)"><div id="toast" class="toast text-bg-dark border-0" role="alert" aria-live="polite" data-bs-delay="2800"><div class="d-flex align-items-center"><div class="toast-body" id="toastMsg"></div><button class="btn-close btn-close-white me-2 ms-auto" data-bs-dismiss="toast" style="flex:0 0 auto"></button></div></div></div>

<script>
window.__MM_CONFIG__={basePath:<?=json_encode($MM['base_path'], JSON_UNESCAPED_SLASHES)?>,apiBase:<?=json_encode(rtrim($MM['api_base'], '/'), JSON_UNESCAPED_SLASHES)?>,apiToken:<?=json_encode($MM['api_token'], JSON_UNESCAPED_SLASHES)?>,siteName:<?=json_encode($MM['site_name'], JSON_UNESCAPED_SLASHES)?>};
window.__INITIAL_DATA__=<?=$seoItem ? json_encode(mm_hydration_payload($seoItem), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) : 'null'?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
'use strict';
/* ═══════════════════════════════════════════════════════════════════════════
   MusicMan v6.8
   ─ Changes in this version ─
   • Fixed favicon (perfectly centered waveform)
   • Removed playback speed control from the entire app
   • Right sidebar now shows a smart Download/Crawl button per track
   • Fixed suggestion pick (mousedown-based, works every time)
   • Toast has rounded corners + supports action buttons (Telegram on error)
   • Fixed row play icon: preview items keep bi-play-circle (was flipping to bi-play-fill)
   ═══════════════════════════════════════════════════════════════════════════ */
const CFG=window.__MM_CONFIG__||{};
const BASE=CFG.basePath||'',SCOPE=BASE+'/';
const API_BASE=CFG.apiBase||'https://3rah.ir/mm/api';
const API_TOKEN=CFG.apiToken||'change_me';
const TG_BOT='musicman_official_bot';
const IS_LOW_END=document.documentElement.classList.contains('low-end');
const LONG_TRACK_MS=8*60*1000;
const KEY={likes:'mm_likes',pls:'mm_playlists',recent:'mm_recent',downloads:'mm_downloads',plays:'mm_recently_played',following:'mm_followed',settings:'mm_settings',notes:'mm_notes',stats:'mm_stats_v1',queue:'mm_q_v2',vol:'mm_vol_v1',fptab:'mm_fptab_v1'};
const DEF={theme:'dark',animations:true,autoScrollLyrics:true,autoRetry:true,crossfade:0,sleepFade:true,accent:'auto',autoTheme:false,lyricSize:1,appBg:false,autoSaveAfterCrawl:false,saveData:false,autoSavePlayed:false};
const POLL_MS=IS_LOW_END?3500:2500,GRACE=3000,DEF_Q='320',PTICK=IS_LOW_END?300:100,LTICK=IS_LOW_END?250:150;
const $=(s,r=document)=>r.querySelector(s),$$=(s,r=document)=>[...r.querySelectorAll(s)],MAIN=()=>document.getElementById('main');
const IS_DESKTOP=()=>window.matchMedia('(min-width: 1024px)').matches;

const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const ls={get(k,d){try{const v=localStorage.getItem(k);return v==null?d:JSON.parse(v)}catch{return d}},set(k,v){try{localStorage.setItem(k,JSON.stringify(v))}catch{}},remove(k){try{localStorage.removeItem(k)}catch{}}};
const cleanId=id=>id?String(id).replace(/^it_/,''):'',proxyUrl=u=>u?`${API_BASE}/proxy?url=${encodeURIComponent(u)}`:'',haptic=ms=>{try{navigator.vibrate?.(ms)}catch{}};
const tgLink=(type,id)=>id?`https://t.me/${TG_BOT}?start=${type}_${cleanId(id)}`:'';
const tgStart=(kind,id)=>{const url=tgLink(kind,id);if(url)window.open(url,'_blank','noopener')};
function fmtTime(s){s=Number(s);if(!s||!isFinite(s)||s<0)return'0:00';s=Math.floor(s);const h=Math.floor(s/3600),m=Math.floor((s%3600)/60),x=s%60;return h?`${h}:${String(m).padStart(2,'0')}:${String(x).padStart(2,'0')}`:`${m}:${String(x).padStart(2,'0')}`}
function fmtSize(b){b=Number(b)||0;if(b<1024)return b+' B';if(b<1048576)return(b/1024).toFixed(1)+' KB';if(b<1073741824)return(b/1048576).toFixed(1)+' MB';return(b/1073741824).toFixed(2)+' GB'}
function safeFileName(s){const o=String(s||'track').replace(/[\\/:*?"<>|\u0000-\u001f]+/g,'_').replace(/\s+/g,' ').trim().slice(0,90);return o||'track'}
function extFromType(t,u){t=String(t||'').toLowerCase();if(t.includes('mpeg')||t.includes('mp3'))return'.mp3';if(t.includes('mp4')||t.includes('m4a'))return'.m4a';if(t.includes('aac'))return'.aac';if(t.includes('ogg')||t.includes('opus'))return'.ogg';if(t.includes('webm'))return'.webm';if(t.includes('wav'))return'.wav';if(t.includes('flac'))return'.flac';const m=String(u||'').split('?')[0].match(/\.(mp3|m4a|aac|ogg|oga|opus|wav|flac|webm)$/i);return m?'.'+m[1].toLowerCase():'.mp3'}
function fmtAgo(ts){const d=Date.now()-(Number(ts)||0);if(d<60000)return'just now';if(d<3600000)return Math.floor(d/60000)+'m ago';if(d<86400000)return Math.floor(d/3600000)+'h ago';if(d<604800000)return Math.floor(d/86400000)+'d ago';return new Date(ts).toLocaleDateString()}
const getSettings=()=>({...DEF,...ls.get(KEY.settings,{})});
function setSetting(k,v){const s=getSettings();s[k]=v;ls.set(KEY.settings,s);applySettings()}

function artistLink(it,stopProp){
  const name=it?.artistName||'';if(!name)return'';
  const id=it?.artistId?String(it.artistId):'';
  const sp=stopProp?' onclick="event.stopPropagation()"':'';
  return id?`<a class="mm-link" href="${SCOPE}artist/${esc(id)}" data-link${sp}>${esc(name)}</a>`:esc(name);
}
function albumLink(it,stopProp){
  const name=it?.collectionName||it?.albumName||'';if(!name)return'';
  const id=(it?.collectionId||it?.albumId)?String(it.collectionId||it.albumId):'';
  const sp=stopProp?' onclick="event.stopPropagation()"':'';
  return id?`<a class="mm-link" href="${SCOPE}album/${esc(id)}" data-link${sp}>${esc(name)}</a>`:esc(name);
}
function badgeChip(kind,icon,label){return`<span class="badge-chip ${kind}"><i class="bi ${icon}"></i>${esc(label)}</span>`}
function progressHtml(pct,kind=''){return`<div class="progress mt-2" style="height:5px;border-radius:999px;background:var(--mm-surface-3);overflow:hidden"><div class="progress-bar ${kind}" style="width:${Math.max(0,Math.min(100,pct))}%"></div></div>`}
function emptyState(i,t,s=''){return`<div class="state"><i class="bi bi-${i}"></i><p class="fw-semibold text-body">${esc(t)}</p>${s?`<p class="mt-1" style="font-size:.78rem">${esc(s)}</p>`:''}</div>`}
function errorState(m){return`<div class="state"><i class="bi bi-exclamation-triangle text-danger"></i><p class="fw-semibold text-body">Something went wrong</p><p class="mt-1" style="font-size:.78rem">${esc(m||'Unknown error')}</p><button class="pill-btn mt-3" onclick="route()"><i class="bi bi-arrow-clockwise"></i> Retry</button></div>`}
const secTitle=(t,tight=false)=>`<h6 class="sec-title ${tight?'tight':''}">${esc(t)}</h6>`;
const PLAYER_EMPTY=(i,t)=>`<div class="fp-lyrics-empty"><i class="bi ${i}"></i>${esc(t)}</div>`;
function backBtn(){return `<div class="px-2 pt-2"><button class="icon-btn" onclick="goBack()" aria-label="Back"><i class="bi bi-chevron-left" style="font-size:1.5rem"></i></button></div>`}

const ACCENTS={auto:null,purple:['#7b2ff7','#f107a3'],blue:['#3b82f6','#06b6d4'],green:['#10b981','#84cc16'],orange:['#f97316','#facc15'],pink:['#ec4899','#f43f5e'],teal:['#14b8a6','#0ea5e9'],red:['#ef4444','#f97316'],indigo:['#6366f1','#a855f7'],slate:['#64748b','#334155']};
function parseRGB(c){
  if(!c)return[123,47,247];if(Array.isArray(c))return c.map(v=>Math.max(0,Math.min(255,Math.round(v))));
  if(c.startsWith('rgb')){const m=c.match(/\d+/g);if(m&&m.length>=3)return[+m[0],+m[1],+m[2]]}
  let h=c.replace('#','').trim();if(h.length===3)h=h.split('').map(x=>x+x).join('');
  const n=parseInt(h,16);if(isNaN(n))return[123,47,247];
  return[(n>>16)&255,(n>>8)&255,n&255];
}
function applyAccent(name,colors){
  let c1,c2;
  if(Array.isArray(colors)){c1=colors[0];c2=colors[1]||colors[0]}
  else if(name==='auto'){c1='#7b2ff7';c2='#f107a3'}
  else{[c1,c2]=ACCENTS[name]||ACCENTS.purple}
  const [r,g,b]=parseRGB(c1);
  const isDark=document.documentElement.getAttribute('data-bs-theme')!=='light';
  let adjR=r,adjG=g,adjB=b;
  const lum=0.2126*r+0.7152*g+0.0722*b;
  if(isDark&&lum<70){const f=85/(lum||1);adjR=Math.min(255,Math.round(r*f+20));adjG=Math.min(255,Math.round(g*f+20));adjB=Math.min(255,Math.round(b*f+20))}
  else if(!isDark&&lum>190){const f=170/lum;adjR=Math.max(0,Math.round(r*f));adjG=Math.max(0,Math.round(g*f));adjB=Math.max(0,Math.round(b*f))}
  const hex1=`rgb(${adjR},${adjG},${adjB})`,[r2,g2,b2]=parseRGB(c2),hex2=`rgb(${r2},${g2},${b2})`;
  const root=document.documentElement;
  root.style.setProperty('--bs-primary',hex1);
  root.style.setProperty('--bs-primary-rgb',`${adjR},${adjG},${adjB}`);
  root.style.setProperty('--mm-accent-grad',`linear-gradient(135deg,${hex1},${hex2})`);
  root.style.setProperty('--mm-accent-1',hex1);
}
// RGB (0-255) → HSL { h: 0-360, s: 0-1, l: 0-1 }
function _rgbToHsl(r, g, b) {
  r /= 255; g /= 255; b /= 255;
  const max = Math.max(r, g, b), min = Math.min(r, g, b);
  const l = (max + min) / 2;
  let h = 0, s = 0;
  const d = max - min;
  if (d !== 0) {
    s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
    switch (max) {
      case r: h = (g - b) / d + (g < b ? 6 : 0); break;
      case g: h = (b - r) / d + 2; break;
      case b: h = (r - g) / d + 4; break;
    }
    h *= 60;
  }
  return { h, s, l };
}

// HSL (h in deg, s/l in 0-1) → [r, g, b] 0-255
function _hslToRgb(h, s, l) {
  h = (((h % 360) + 360) % 360) / 360;
  const a = s * Math.min(l, 1 - l);
  const f = n => {
    const k = (n + h * 12) % 12;
    return Math.round(255 * (l - a * Math.max(-1, Math.min(k - 3, 9 - k, 1))));
  };
  return [f(0), f(8), f(4)];
}
const _artTintCache = new Map();

// Tunables — adjust once and every cover obeys them
const ACCENT_SAT_DARK   = 62;   // % saturation on dark theme
const ACCENT_SAT_LIGHT  = 68;   // % saturation on light theme
const ACCENT_L_DARK     = 58;   // % lightness on dark theme
const ACCENT_L_LIGHT    = 44;   // % lightness on light theme
const ACCENT_HUE_SHIFT  = 28;   // secondary hue = primary + this many degrees

async function computeArtAccent(imgUrl) {
  if (!imgUrl) return null;

  const isDark = document.documentElement.getAttribute('data-bs-theme') !== 'light';
  const cacheKey = imgUrl + '|' + (isDark ? 'd' : 'l');
  if (_artTintCache.has(cacheKey)) return _artTintCache.get(cacheKey);

  try {
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.src = imgUrl;
    await img.decode();

    const c = document.createElement('canvas');
    c.width = 48; c.height = 48;
    const x = c.getContext('2d', { willReadFrequently: true });
    x.drawImage(img, 0, 0, 48, 48);
    const d = x.getImageData(0, 0, 48, 48).data;

    // Collect hues of "colorful" pixels only
    const hues = [];
    let rSum = 0, gSum = 0, bSum = 0, n = 0;

    for (let i = 0; i < d.length; i += 4) {
      if (d[i + 3] < 128) continue;          // skip transparent
      const R = d[i], G = d[i + 1], B = d[i + 2];
      rSum += R; gSum += G; bSum += B; n++;

      const { h, s, l } = _rgbToHsl(R, G, B);
      if (s < 0.15) continue;                // skip near-grayscale
      if (l < 0.12 || l > 0.90) continue;    // skip near-black / near-white
      hues.push(h);
    }
    if (!n) return null;

    // Circular mean of hue (hue is an angle — plain average is wrong)
    let hue;
    if (hues.length > 0) {
      let hx = 0, hy = 0;
      for (const h of hues) {
        const rad = h * Math.PI / 180;
        hx += Math.cos(rad);
        hy += Math.sin(rad);
      }
      hue = Math.atan2(hy, hx) * 180 / Math.PI;
      if (hue < 0) hue += 360;
    } else {
      // No colorful pixel → fall back to hue of the average RGB
      hue = _rgbToHsl(rSum / n, gSum / n, bSum / n).h;
    }

    // Rebuild at fixed S & L — this is the key step
    const SAT   = isDark ? ACCENT_SAT_DARK  : ACCENT_SAT_LIGHT;
    const LIGHT = isDark ? ACCENT_L_DARK    : ACCENT_L_LIGHT;
    const hue2  = (hue + ACCENT_HUE_SHIFT) % 360;

    const [r1, g1, b1] = _hslToRgb(hue,  SAT / 100, LIGHT / 100);
    const [r2, g2, b2] = _hslToRgb(hue2, SAT / 100, Math.max(0.22, LIGHT / 100 - 0.14));

    const res = [`rgb(${r1}, ${g1}, ${b1})`, `rgb(${r2}, ${g2}, ${b2})`];
    _artTintCache.set(cacheKey, res);
    return res;
  } catch {
    return null;
  }
}
function applySettings(){
  const s=getSettings();
  const theme=s.autoTheme?((new Date().getHours()>=7&&new Date().getHours()<19)?'light':'dark'):s.theme;
  document.documentElement.setAttribute('data-bs-theme',theme);
  document.body.classList.toggle('no-animations',!s.animations);
  const m=document.querySelector('meta[name="theme-color"]');if(m)m.content=theme==='dark'?'#0b0b0d':'#ffffff';
  if(s.accent==='auto'&&Player&&Player.track){
    const art=getArtwork(Player.track,300)||Player.track.artworkUrl||'';
    if(art){computeArtAccent(art).then(c=>{if(c&&getSettings().accent==='auto')applyAccent(null,c)})}
  } else {applyAccent(s.accent||'purple')}
  const bg=document.getElementById('appBg');
  if(bg){
    if(s.appBg){bg.style.display='';if(Player&&Player.track){const art=getArtwork(Player.track,300)||Player.track.artworkUrl||'';if(art)bg.style.backgroundImage=`url("${art}")`}}
    else{bg.style.display='none';bg.style.backgroundImage=''}
  }
}
function autoTheme(){const s=getSettings();if(!s.autoTheme)return;const h=new Date().getHours(),want=(h>=7&&h<19)?'light':'dark';if(document.documentElement.getAttribute('data-bs-theme')!==want){document.documentElement.setAttribute('data-bs-theme',want);const m=document.querySelector('meta[name="theme-color"]');if(m)m.content=want==='dark'?'#0b0b0d':'#ffffff'}}
setInterval(autoTheme,60000);

/* ── Toast system (with rounded corners + action buttons) ── */
let _toastInstance=null,_toastTimer=null;
function _showToast(content,variant,delay){
  const el=$('#toast');if(!el)return;
  try{if(_toastInstance)_toastInstance.hide()}catch{}
  if(_toastTimer){clearTimeout(_toastTimer);_toastTimer=null}
  el.className=`toast text-bg-${variant} border-0`;
  const body=$('#toastMsg');
  body.replaceChildren();
  if(content instanceof Node)body.appendChild(content);
  else body.textContent=String(content||'');
  _toastInstance=new bootstrap.Toast(el,{delay,autohide:true});
  _toastInstance.show();
}
function toast(msg,variant='dark'){_showToast(msg,variant,2600)}
function toastAction(msg,actions,variant='danger',delay=6500){
  const wrap=document.createElement('div');
  wrap.className='d-flex align-items-center gap-2 flex-wrap';
  const span=document.createElement('span');
  span.textContent=msg;
  wrap.appendChild(span);
  for(const a of actions||[]){
    const btn=document.createElement('button');
    btn.className=`pill-btn ${a.class||'telegram'} sm`;
    btn.innerHTML=a.html;
    btn.onclick=()=>{try{a.fn&&a.fn()}catch(e){}try{if(_toastInstance)_toastInstance.hide()}catch{}};
    wrap.appendChild(btn);
  }
  _showToast(wrap,variant,delay);
}
function sheet(id){return bootstrap.Offcanvas.getOrCreateInstance($(id))}
function closeAllSheets(){['#sheetTrack','#sheetPl','#sheetPrompt','#sheetConfirm','#sheetSleep','#sheetSettings','#sheetInstall'].forEach(s=>{const el=$(s);if(!el)return;const i=bootstrap.Offcanvas.getInstance(el);if(i)i.hide()})}
let _pr=null;
function askText(t,v='',ph='Name'){return new Promise(r=>{_pr=r;$('#promptTitle').textContent=t;const i=$('#promptInput');i.value=v;i.placeholder=ph;sheet('#sheetPrompt').show();setTimeout(()=>i.focus(),350)})}
function submitPrompt(){const v=$('#promptInput').value.trim();sheet('#sheetPrompt').hide();if(_pr){_pr(v||null);_pr=null}}
$('#sheetPrompt')?.addEventListener('hidden.bs.offcanvas',()=>{if(_pr){_pr(null);_pr=null}});
$('#promptInput')?.addEventListener('keydown',e=>{if(e.key==='Enter')submitPrompt()});
let _cf=null;
function askConfirm(t,b,ok='Delete',tone='danger'){return new Promise(r=>{_cf=r;$('#cfTitle').textContent=t;$('#cfBody').textContent=b;const o=$('#cfOkBtn');o.textContent=ok;o.style.background=tone==='primary'?'var(--bs-primary)':'var(--bs-danger)';o.style.color='#fff';sheet('#sheetConfirm').show()})}
$('#cfOkBtn')?.addEventListener('click',()=>{sheet('#sheetConfirm').hide();if(_cf){_cf(true);_cf=null}});
$('#sheetConfirm')?.addEventListener('hidden.bs.offcanvas',()=>{if(_cf){_cf(false);_cf=null}});

async function api(path,opts={}){
  const ctrl=new AbortController();const to=setTimeout(()=>ctrl.abort(),opts.timeout||12000);
  let res;
  try{res=await fetch(API_BASE+path,{...opts,signal:ctrl.signal,headers:{'Content-Type':'application/json','Authorization':'Bearer '+API_TOKEN,...(opts.headers||{})}})}
  catch(e){clearTimeout(to);if(e&&e.name==='AbortError')throw new Error('Request timed out');throw new Error('Network error: '+(e.message||'unknown'))}
  clearTimeout(to);
  const text=await res.text().catch(()=>'');
  let data={};if(text){try{data=JSON.parse(text)}catch{data={raw:text.slice(0,400)}}}
  if(!res.ok){throw new Error((data&&data.error)||(data&&data.message)||`HTTP ${res.status}`)}
  return data;
}
const apiSearch=async t=>(await api(`/search?term=${encodeURIComponent(t)}&limit=50&entity=musicArtist,album,song`)).results||[];
const apiFresh=async()=>(await api('/fresh')).results||[];
const apiPopular=async(l=40)=>(await api('/popular?limit='+l+'&minViews=1')).results||[];
const apiLookup=async(id,e)=>(await api(`/lookup?id=${cleanId(id)}&entity=${e}&limit=200`)).results||[];
const apiArtistTracks=async(id,{page=1,limit=50,sort='album'}={})=>api(`/artist/tracks?id=${encodeURIComponent(cleanId(id))}&page=${page}&limit=${limit}&sort=${encodeURIComponent(sort)}`);
const apiQueueAdd=(b,q)=>api('/download/add',{method:'POST',body:JSON.stringify({quality:q||DEF_Q,skipExisting:true,...b})});
async function apiCrawlStatus(id){try{const r=await api(`/download/status?trackId=${cleanId(id)}`);if(!r||!r.download)return{download_status:'completed',percent:100,found:false};return{...r.download,found:true}}catch{return{download_status:'completed',percent:100,found:false}}}
let _batchStatusSupported=null;
async function apiBatchStatus(ids){
  if(!ids||!ids.length)return{};
  ids=ids.map(String);const out={};
  if(_batchStatusSupported!==false){
    try{
      const chunks=[];for(let i=0;i<ids.length;i+=150)chunks.push(ids.slice(i,i+150));
      const results=await Promise.all(chunks.map(c=>api(`/download/batch-status?ids=${c.join(',')}`)));
      if(results.every(r=>r&&r.items)){_batchStatusSupported=true;for(const r of results)Object.assign(out,r.items);if(Object.keys(out).length>=ids.length)return out}else _batchStatusSupported=false;
    }catch{_batchStatusSupported=false}
  }
  const have=new Set(Object.keys(out));
  const missing=ids.filter(id=>!have.has(id));
  if(missing.length){const r=await Promise.all(missing.map(async id=>{try{return[id,await apiCrawlStatus(id)]}catch{return null}}));for(const x of r)if(x)out[x[0]]=x[1]}
  return out;
}

const _ftCache=new Map();const _FT_TTL=5*60*1000,_FT_MAX=500;
function _ftCachePrune(){if(_ftCache.size<=_FT_MAX)return;const now=Date.now();for(const[k,v]of _ftCache){if(v.exp<now)_ftCache.delete(k)}while(_ftCache.size>_FT_MAX){const k=_ftCache.keys().next().value;_ftCache.delete(k)}}

const _sgc=new Map();const SGC_MAX=60;
function _sh(n,q){if(!q)return esc(n);const l=String(n).toLowerCase(),nd=q.toLowerCase(),i=l.indexOf(nd);if(i<0)return esc(n);return esc(n.slice(0,i))+'<b>'+esc(n.slice(i,i+nd.length))+'</b>'+esc(n.slice(i+nd.length))}

/* ── Suggestion: FIXED pick (mousedown based, reliable) ── */
function initSuggest(input,dropdown){
  if(!input||!dropdown||input.__sg)return;input.__sg=1;
  let items=[],ai=-1,lq='',dt=null,ct=null;
  const close=()=>{dropdown.classList.remove('show');dropdown.replaceChildren();input.setAttribute('aria-expanded','false');items=[];ai=-1};
  const render=()=>{
    if(!items.length){close();return}
    dropdown.innerHTML=items.map((it,i)=>{
      const ic=it.type==='artist'?'bi-person':it.type==='collection'?'bi-disc':'bi-music-note';
      const lb=it.type==='artist'?'Artist':it.type==='collection'?'Album':'Song';
      return`<button type="button" role="option" class="sg-item${i===ai?' active':''}" data-idx="${i}"><i class="bi ${ic} sg-icon"></i><span class="sg-title flex-grow-1 min-w-0">${_sh(it.name,lq)}</span><span class="sg-badge">${lb}</span></button>`;
    }).join('');
    dropdown.classList.add('show');input.setAttribute('aria-expanded','true');
    if(ai>=0){const e=dropdown.querySelector('.sg-item.active');e?.scrollIntoView?.({block:'nearest'})}
  };
  const pick=i=>{
    const it=items[i];if(!it)return;
    close();input.blur();
    const l=ls.get(KEY.recent,[]).filter(x=>x!==it.name);
    l.unshift(it.name);ls.set(KEY.recent,l.slice(0,12));
    go(`/${it.type}/${it.id}`);
  };
  input.addEventListener('input',()=>{
    const q=input.value.trim();if(q===lq)return;lq=q;
    if(dt)clearTimeout(dt);
    if(q.length<1){close();return}
    const c=_sgc.get(q.toLowerCase());
    if(c){items=c;ai=-1;render();return}
    if(!dropdown.classList.contains('show')){
      dropdown.innerHTML=`<div class="sg-loading"><span class="spinner-border spinner-border-sm" style="width:12px;height:12px;border-width:2px"></span> Searching…</div>`;
      dropdown.classList.add('show');input.setAttribute('aria-expanded','true');
    }
    dt=setTimeout(async()=>{
      if(ct)ct.abort();ct=new AbortController();
      try{
        const r=await fetch(`${API_BASE}/suggest?q=${encodeURIComponent(q)}&limit=8`,{headers:{'Authorization':'Bearer '+API_TOKEN},signal:ct.signal});
        if(input.value.trim()!==q)return;
        const d=await r.json();const s=Array.isArray(d.suggestions)?d.suggestions:[];
        if(_sgc.size>=SGC_MAX){const f=_sgc.keys().next().value;_sgc.delete(f)}
        _sgc.set(q.toLowerCase(),s);items=s;ai=-1;
        if(!items.length){dropdown.innerHTML=`<div class="sg-empty"><i class="bi bi-search"></i> No matches</div>`;dropdown.classList.add('show');input.setAttribute('aria-expanded','true')}
        else render()
      }catch(e){if(e.name!=='AbortError')close()}
    },160)
  });
  input.addEventListener('keydown',e=>{
    if(!dropdown.classList.contains('show'))return;
    if(e.key==='ArrowDown'){e.preventDefault();ai=Math.min(ai+1,items.length-1);render()}
    else if(e.key==='ArrowUp'){e.preventDefault();ai=Math.max(ai-1,-1);render()}
    else if(e.key==='Enter'){
      if(ai>=0&&items[ai]){e.preventDefault();e.stopPropagation();pick(ai);return}
      if(items.length===1){e.preventDefault();e.stopPropagation();pick(0);return}
      // If nothing selected but dropdown is open, close it and let form submit
      close();
    } else if(e.key==='Escape'){close();input.blur()}
  });
  // FIXED: use mousedown so blur never steals the click
  dropdown.addEventListener('mousedown',e=>{
    const b=e.target.closest('.sg-item');
    if(!b)return;
    e.preventDefault();
    e.stopPropagation();
    pick(Number(b.dataset.idx));
  });
  // touch fallback (mobile)
  dropdown.addEventListener('touchstart',e=>{
    const b=e.target.closest('.sg-item');
    if(!b)return;
    e.preventDefault();
    pick(Number(b.dataset.idx));
  },{passive:false});
  input.addEventListener('blur',()=>setTimeout(close,180));
  input.addEventListener('focus',()=>{if(items.length&&input.value.trim()===lq)render()});
  document.addEventListener('click',e=>{if(!input.contains(e.target)&&!dropdown.contains(e.target))close()});
}

const TYPE_MAP={musicArtist:'artist',artist:'artist',album:'collection',collection:'collection',song:'track',track:'track'};
const itemType=it=>TYPE_MAP[it?.wrapperType]||it?.wrapperType||'';
const itemId=it=>it?.trackId||it?.collectionId||it?.artistId||'';
function getArtwork(it,size=300){const u=it?.attachments?.artworkUrls;if(!Array.isArray(u)||!u.length)return it?.artworkUrl||'';const b=u.find(x=>String(x.size||'').includes(String(size)))||u[u.length-1];const url=b?.url||'';return url.replace(/\/(\d+)x(\d+)(bb)?\./,`/${size}x${size}bb.`).replace('1000x1000','200x200')}
function hasAudio(it){const a=it?.attachments?.audioUrls;return Array.isArray(a)&&a.some(x=>x&&x.url)}
function hasPreview(it){const p=it?.attachments?.previewUrls;return Array.isArray(p)&&p.some(x=>x&&x.url)}
function getPreviewUrl(it){const p=it?.attachments?.previewUrls;if(!Array.isArray(p)||!p.length)return null;for(const x of p)if(x&&x.url)return x.url;return null}
function getAudioOptions(it){const a=it?.attachments?.audioUrls;if(!Array.isArray(a))return[];return a.filter(x=>x&&x.url&&x.quality)}
function pickAudio(it,pq){const o=getAudioOptions(it);if(!o.length){const a=it?.attachments?.audioUrls;if(Array.isArray(a)&&a.length&&a[0]?.url)return a[0].url;return getPreviewUrl(it)}const t=parseInt(pq,10)||192,e=o.find(x=>String(x.quality)===String(pq));if(e)return e.url;let c=o[0],bd=Infinity;for(const x of o){const q=parseInt(x.quality,10);if(!isFinite(q))continue;const d=Math.abs(q-t);if(d<bd){bd=d;c=x}}return c?.url||null}
const getPlayable=it=>pickAudio(it,DEF_Q);
function dlActionFor(t){const d=hasAudio(t);return{isDirect:d,label:d?'Download':'Crawl',icon:d?'bi-download':'bi-cloud-arrow-down',idleTitle:d?'Not downloaded':'Not crawled',idleSub:d?'Full audio available — download for offline':(hasPreview(t)?'Crawl to listen offline · preview available now':'Crawl to listen offline')}}
function isLongTrack(it){return (Number(it?.trackTimeMillis)||0)>LONG_TRACK_MS}
const itemCache=new Map();
function cacheItems(items){for(const it of items||[]){const t=itemType(it),i=itemId(it);if(t&&i)itemCache.set(`${t}:${i}`,it)}}
const getCached=(t,id)=>itemCache.get(`${t}:${id}`);

let _dbp;
function getDB(){if(!_dbp)_dbp=new Promise((res,rej)=>{const r=indexedDB.open('mm_audio',2);r.onupgradeneeded=e=>{const db=r.result;if(!db.objectStoreNames.contains('audio'))db.createObjectStore('audio',{keyPath:'trackId'})};r.onsuccess=()=>res(r.result);r.onerror=()=>rej(r.error)});return _dbp}
async function idb(st,mode,fn){const db=await getDB();return new Promise((res,rej)=>{const tx=db.transaction(st,mode),r=fn(tx.objectStore(st));tx.oncomplete=()=>res(r?.result);tx.onerror=()=>rej(tx.error);tx.onabort=()=>rej(tx.error)})}
const cachePut=(id,b,m)=>idb('audio','readwrite',s=>s.put({trackId:String(id),blob:b,meta:m,size:b.size,at:Date.now()}));
const cacheGet=id=>idb('audio','readonly',s=>s.get(String(id))).then(r=>r||null);
const cacheAll=()=>idb('audio','readonly',s=>s.getAll()).then(r=>r||[]);
const cacheDel=id=>idb('audio','readwrite',s=>s.delete(String(id)));
const cacheClear=()=>idb('audio','readwrite',s=>s.clear());
const cachedIds=new Set();
async function refreshCacheIndex(){cachedIds.clear();(await cacheAll()).forEach(e=>cachedIds.add(String(e.trackId)))}
const isCached=id=>cachedIds.has(String(id));
async function getStorageEstimate(){if(!navigator.storage?.estimate)return null;try{const e=await navigator.storage.estimate();return{usage:e.usage||0,quota:e.quota||0}}catch{return null}}

function metaFromTrack(t){
  return{wrapperType:'track',trackId:String(t.trackId),trackName:t.trackName||'',artistName:t.artistName||'',artistId:t.artistId?String(t.artistId):'',collectionName:t.collectionName||'',collectionId:t.collectionId?String(t.collectionId):'',artworkUrl100:t.artworkUrl100||getArtwork(t,100)||'',primaryGenreName:t.primaryGenreName||'',releaseDate:t.releaseDate||'',trackTimeMillis:t.trackTimeMillis||0,trackNumber:t.trackNumber||0,lyrics:t.lyrics||t.attachments?.lyrics||null,attachments:{artworkUrls:t.attachments?.artworkUrls||(t.artworkUrl100?[{url:t.artworkUrl100,size:'100x100'}]:[])}}
}

const DL={
  items:[],listeners:new Set(),timer:null,saving:new Set(),_polling:false,
  load(){this.items=ls.get(KEY.downloads,[]).map(it=>it.status==='saving'?{...it,status:'paused',percent:0,error:'Interrupted'}:it);this.persist()},
  persist(){ls.set(KEY.downloads,this.items)},
  all(){return this.items},
  active(){return this.items.filter(i=>i.type!=='batch'&&['queued','crawling','saving'].includes(i.status))},
  activeBatches(){return this.items.filter(i=>i.type==='batch'&&['queued','crawling','saving'].includes(i.status))},
  ready(){return this.items.filter(i=>i.type!=='batch'&&i.status==='ready')},
  readyBatches(){return this.items.filter(i=>i.type==='batch'&&i.status==='ready')},
  failed(){return this.items.filter(i=>i.type!=='batch'&&['failed','paused'].includes(i.status))},
  failedBatches(){return this.items.filter(i=>i.type==='batch'&&i.status==='failed')},
  get(id){return this.items.find(i=>String(i.trackId)===String(id))},
  notify(){this.persist();updateDownloadBadges();this.listeners.forEach(fn=>{try{fn(this)}catch(e){}})},
  onChange(fn){this.listeners.add(fn);return()=>this.listeners.delete(fn)},
  upsert(e){
    const i=this.items.findIndex(x=>String(x.trackId)===String(e.trackId));
    if(i>=0){this.items[i]={...this.items[i],...e,updatedAt:Date.now()}}
    else{const defaults={trackId:String(e.trackId),name:e.name||'Track',artist:e.artist||'',artistId:e.artistId||'',album:e.album||'',collectionId:e.collectionId||'',artwork:e.artwork||'',type:e.type||'save',status:e.status||'queued',percent:e.percent||0,error:e.error||'',bytes:e.bytes||0,totalBytes:e.totalBytes||0,addedAt:e.addedAt||Date.now(),updatedAt:Date.now()};const extra={};for(const k in e)if(!(k in defaults))extra[k]=e[k];this.items.unshift({...defaults,...extra})}
    this.notify()
  },
  update(id,p){const it=this.get(id);if(!it)return;Object.assign(it,p,{updatedAt:Date.now()});this.notify()},
  remove(id){this.items=this.items.filter(i=>String(i.trackId)!==String(id));this.notify()},
  async add(track,_t,quality){
    if(!track?.trackId)return;const id=String(track.trackId);
    if(isCached(id)){toast('Already offline');return}
    const ex=this.get(id);if(ex&&['queued','crawling','saving'].includes(ex.status)){toast('Already in progress','warning');return}
    this.upsert({trackId:id,name:track.trackName||'Track',artist:track.artistName||'',artistId:track.artistId?String(track.artistId):'',album:track.collectionName||'',collectionId:track.collectionId?String(track.collectionId):'',artwork:getArtwork(track,100),status:'queued',percent:0,error:''});
    haptic(12);
    if(hasAudio(track)){this.update(id,{status:'saving',percent:0});this._runSave(id,track).catch(e=>this.update(id,{status:'failed',error:e.message}));return}
    try{await apiQueueAdd({trackId:cleanId(id)},quality)}catch(e){}
    this.update(id,{status:'queued',percent:0,addedAt:Date.now()});this._ensureTimer()
  },
  async addBatch({subType,sourceId,name,artist,artwork,tracks,forceSave=false}){
    if(!tracks||!tracks.length){toast('Nothing to crawl','warning');return}
    const batchId=`batch_${subType}_${sourceId}`;
    const ex=this.get(batchId);
    if(ex&&['queued','crawling','saving'].includes(ex.status)){toast('Already in progress','warning');return}
    const ids=tracks.map(t=>String(t.trackId)).filter(Boolean);
    this.upsert({trackId:batchId,type:'batch',subType,sourceId:String(sourceId),name:name||'Batch',artist:artist||'',artwork:artwork||'',trackIds:ids,trackCount:ids.length,readyCount:0,status:'crawling',percent:0,error:'',forceSave:!!forceSave});
    haptic(15);this._ensureTimer()
  },
  async retry(id){
    const it=this.get(id);if(!it)return;
    if(it.type==='batch'){this.update(id,{status:'crawling',error:'',percent:0,addedAt:Date.now()});this._ensureTimer();return}
    const track=getCached('track',id)||await this._fetchTrack(id);
    if(!track){toast('Track unavailable','danger');return}
    this.update(id,{status:'queued',error:'',percent:0,addedAt:Date.now(),_markTries:0});
    if(hasAudio(track)){this.update(id,{status:'saving'});this._runSave(id,track).catch(e=>this.update(id,{status:'failed',error:e.message}));return}
    try{await apiQueueAdd({trackId:cleanId(id)})}catch(e){}
    this.update(id,{status:'queued'});this._ensureTimer()
  },
  async _fetchTrack(id){
    const k=String(id);
    const c1=getCached('track',k);if(c1&&hasAudio(c1))return c1;
    const c2=_ftCache.get(k);if(c2&&c2.exp>Date.now())return c2.t;
    try{const r=await apiLookup(k,'song');const it=r.find(x=>itemType(x)==='track')||r[0];if(it){cacheItems([it]);_ftCache.set(k,{t:it,exp:Date.now()+_FT_TTL});_ftCachePrune()}return it}catch{return null}
  },
  async _runSave(id,track){
    if(this.saving.has(id))return;this.saving.add(id);
    const url=getPlayable(track);
    if(!url){this.saving.delete(id);throw new Error('No audio URL')}
    try{
      const res=await fetch(proxyUrl(url));
      if(!res.ok)throw new Error(`HTTP ${res.status}`);
      const total=Number(res.headers.get('content-length'))||0,mime=(res.headers.get('content-type')||'').split(';')[0]||'audio/mpeg';
      const reader=res.body?.getReader?.();
      let blob;
      if(reader){
        const ch=[];let rec=0,last=0;
        while(true){const{done,value}=await reader.read();if(done)break;ch.push(value);rec+=value.length;const pct=total?Math.min(99,Math.round(rec/total*100)):0;const n=performance.now();if(n-last>250||rec===total){last=n;this.update(id,{percent:pct,bytes:rec,totalBytes:total})}}
        blob=new Blob(ch)
      }else blob=await res.blob();
      await cachePut(id,blob,{...metaFromTrack(track),mime,url});
      cachedIds.add(String(id));
      this.update(id,{status:'completed',percent:100,bytes:blob.size,totalBytes:blob.size,completedAt:Date.now()});
      haptic(18);toast(`Downloaded · ${fmtSize(blob.size)}`);
      if(isLibDl())updateDlLists();
      if(isTrackRoute()){const t=getCached('track',id);if(t)renderCrawlCard(t,null)}
      setTimeout(()=>{if(this.get(id)?.status==='completed')this.remove(id)},3500)
    }finally{this.saving.delete(id)}
  },
  async _markCompleted(id){
    const item=this.get(id);const tries=(item?._markTries||0)+1;
    const f=await this._fetchTrack(id);
    if(!f||!hasAudio(f)){
      if(tries>=4)this.update(id,{status:'failed',error:'Audio not available after multiple attempts',_markTries:tries});
      else this.update(id,{status:'crawling',percent:100,_markTries:tries,addedAt:Date.now()});
      return
    }
    const auto=!!getSettings().autoSaveAfterCrawl;
    if(auto){this.update(id,{status:'saving',percent:0});this._runSave(id,f).catch(e=>this.update(id,{status:'failed',error:e.message}))}
    else this.update(id,{status:'ready',percent:100,readyAt:Date.now()})
  },
  async _pollBatch(batch){
    const ids=(batch.trackIds||[]).map(String);
    if(!ids.length){this.update(batch.trackId,{status:'ready',percent:100,readyCount:0});return}
    let ready=0,failed=0;const toFetch=[];
    const graceExpired=(Date.now()-(batch.addedAt||0))>GRACE;
    const statuses=await apiBatchStatus(ids);
    for(const id of ids){
      if(isCached(id)){ready++;continue}
      const st=statuses[id];const ds=st?.download_status;
      if(ds==='failed'||ds==='stopped'){failed++;continue}
      if(ds==='completed'){toFetch.push(id);continue}
      if(!st||st.found===false){if(graceExpired)toFetch.push(id);continue}
    }
    if(toFetch.length){
      const res=await Promise.all(toFetch.map(async id=>{const t=await this._fetchTrack(id);return[id,!!(t&&hasAudio(t))]}));
      for(const[,ok]of res){if(ok)ready++;else failed++}
    }
    const pct=Math.round((ready/ids.length)*100);
    if(ready===ids.length){
      if(getSettings().autoSaveAfterCrawl||batch.forceSave){this.update(batch.trackId,{status:'saving',percent:100,readyCount:ready});await this._saveBatch(batch)}
      else this.update(batch.trackId,{status:'ready',percent:100,readyCount:ready})
    }else if(failed>0&&ready+failed===ids.length){
      this.update(batch.trackId,{status:'failed',percent:pct,readyCount:ready,failedCount:failed,error:`${failed} of ${ids.length} failed tracks`});
    }else this.update(batch.trackId,{status:'crawling',percent:pct,readyCount:ready,failedCount:failed})
  },
  async _saveBatch(batch){
    const ids=batch.trackIds||[];let done=0;
    for(const id of ids){
      if(isCached(id)){done++;continue}
      const t=getCached('track',id)||await this._fetchTrack(id);
      if(t&&hasAudio(t)){try{await this._runSave(id,t);done++}catch(e){}}
      this.update(batch.trackId,{percent:Math.round(done/ids.length*100)});
    }
    this.update(batch.trackId,{status:'completed',percent:100,readyCount:done});
    haptic(20);toast(`Saved ${done}/${ids.length} tracks`);
    setTimeout(()=>{if(this.get(batch.trackId)?.status==='completed')this.remove(batch.trackId)},3500)
  },
  async _pollOnce(){
    if(!navigator.onLine)return 0;
    const cs=this.items.filter(i=>i.type!=='batch'&&['queued','crawling'].includes(i.status));
    const bs=this.items.filter(i=>i.type==='batch'&&['queued','crawling','saving'].includes(i.status));
    let progressMarker=0;
    if(cs.length){
      const ids=cs.map(i=>String(i.trackId));
      const statuses=await apiBatchStatus(ids);
      for(const item of cs){
        const id=String(item.trackId);
        const st=statuses[id]||{download_status:'completed',percent:100,found:false};
        const nf=st.found===false;
        if(nf&&Date.now()-(item.addedAt||0)<GRACE)continue;
        const s=st.download_status;const pct=Math.max(0,Math.min(100,Math.round(Number(st.percent)||0)));
        if(nf||s==='completed')await this._markCompleted(id);
        else if(s==='failed'||s==='stopped'){
          if(getSettings().autoRetry&&(item.retries||0)<3){setTimeout(()=>DL.retry(id).catch(()=>{}),3000);this.update(id,{status:'failed',error:(st.error||'Failed')+' — retrying…',retries:(item.retries||0)+1})}
          else this.update(id,{status:'failed',error:st.error||'Failed'});
        }else{this.update(id,{status:'crawling',percent:pct});progressMarker+=pct}
      }
    }
    for(const batch of bs){if(batch.status==='saving')continue;await this._pollBatch(batch);progressMarker+=(batch.readyCount||0)*1000+(batch.percent||0)}
    progressMarker+=cachedIds.size*100;
    return progressMarker
  },
  _ensureTimer(){
    if(this._polling)return;this._polling=true;
    let noProgress=0,lastProgress=-1;const self=this;
    const schedule=()=>{
      const busy=self.active().length+self.activeBatches().length;
      if(!busy){self._polling=false;self.timer=null;return}
      let base=IS_LOW_END?5000:3500;base*=Math.max(1,Math.ceil(busy/5));
      const backoff=Math.min(6,1+noProgress*.5);
      const delay=Math.min(30000,Math.round(base*backoff));
      self.timer=setTimeout(async()=>{try{const p=await self._pollOnce();if(p===lastProgress)noProgress++;else{noProgress=0;lastProgress=p}}catch(e){}schedule()},delay);
    };schedule()
  }
};
function resumeDownloadPolling(){if(DL.items.some(i=>['queued','crawling'].includes(i.status)))DL._ensureTimer()}
function updateDownloadBadges(){
  const total=DL.active().length+DL.ready().length+DL.activeBatches().length+DL.readyBatches().length;
  const ind=$('#dlIndicator'),indC=$('#dlIndicatorCount');
  if(ind&&indC){ind.style.display=total?'':'none';indC.textContent=total}
  const b=$('#libBadge');if(b){b.classList.toggle('on',total>0);b.textContent=total}
}
async function exportCached(id){let e;try{e=await cacheGet(id)}catch{e=null}if(!e?.blob){toast('File not available','danger');return}
const m=e.meta||{},base=safeFileName([m.artist,m.name].filter(Boolean).join(' - ')||m.name||id),mime=m.mime||'audio/mpeg',ext=extFromType(mime,m.url||''),blob=new Blob([e.blob],{type:mime}),url=URL.createObjectURL(blob);
try{const a=document.createElement('a');a.href=url;a.download=base+ext;a.style.display='none';document.body.appendChild(a);a.click();a.remove();haptic(14);toast(`Saved "${base+ext}"`)}catch{try{window.open(url,'_blank')}catch{}toast('Opened in new tab')}
setTimeout(()=>{try{URL.revokeObjectURL(url)}catch{}},180000)}
async function exportAllCached(){const all=await cacheAll();if(!all.length){toast('Nothing to export','warning');return}
const ok=await askConfirm('Export all tracks?',`${all.length} file${all.length!==1?'s':''} will be saved.`,'Export','primary');if(!ok)return;
for(let i=0;i<all.length;i++){await exportCached(all[i].trackId);await new Promise(r=>setTimeout(r,650))}toast('Export finished','success')}

const getLikes=()=>ls.get(KEY.likes,[]);
const isLiked=id=>getLikes().some(t=>String(t.trackId)===String(id));
function makeLikeEntry(it){return{trackId:String(it.trackId),trackName:it.trackName||'Unknown',artistName:it.artistName||'',artistId:it.artistId?String(it.artistId):'',collectionName:it.collectionName||'',collectionId:it.collectionId?String(it.collectionId):'',artworkUrl:getArtwork(it,100)||it.artworkUrl||'',addedAt:Date.now()}}
function toggleLike(it){if(!it?.trackId)return;const id=String(it.trackId),l=getLikes(),i=l.findIndex(t=>String(t.trackId)===id);if(i>=0){l.splice(i,1);toast('Removed from Liked')}else{l.unshift(makeLikeEntry(it));toast('Added to Liked')}ls.set(KEY.likes,l);haptic(8);refreshLikes();if(isLibLikes())viewLikes()}
async function toggleLikeById(id){let it=getCached('track',id);if(!it){try{const r=await apiLookup(id,'song');it=r.find(x=>itemType(x)==='track')||r[0];if(it)cacheItems([it])}catch{}}if(it)toggleLike(it);else toast('Track not available','danger')}
function refreshLikes(){
  const cur=Player.track?String(Player.track.trackId):null;
  const n=MAIN().querySelectorAll('[data-like]');
  for(let i=0;i<n.length;i++){
    const b=n[i],id=b.dataset.like;if(!id)continue;
    const on=isLiked(id);
    if(on!==b.classList.contains('liked')){
      b.classList.toggle('liked',on);
      const ic=b.firstElementChild;
      if(ic)ic.className=`bi ${on?'bi-heart-fill':'bi-heart'}`
    }
  }
  $$('.fp-like-btn').forEach(f=>{
    const on=cur?isLiked(cur):false;
    f.classList.toggle('liked',on);
    const i=f.querySelector('i');
    if(i)i.className=`bi ${on?'bi-heart-fill':'bi-heart'}`
  })
}
const getFollowed=()=>ls.get(KEY.following,[]);
const isFollowed=id=>!!id&&getFollowed().some(a=>String(a.artistId)===String(id));
function toggleFollow(aid,art){const id=String(aid);if(!id)return;const l=getFollowed(),i=l.findIndex(a=>String(a.artistId)===id);if(i>=0){l.splice(i,1);toast('Unfollowed')}else{const c=getCached('artist',id);l.unshift({artistId:id,artistName:c?.artistName||'',primaryGenreName:c?.primaryGenreName||'',artwork:art||getArtwork(c,300)||'',followedAt:Date.now()});toast('Following');haptic(10)}ls.set(KEY.following,l);refreshFollowButtons();if(isLibFoll())viewFollowing()}
function refreshFollowButtons(){$$('[data-follow]').forEach(b=>{const id=b.dataset.follow,on=isFollowed(id);b.classList.toggle('following',on);b.classList.toggle('primary',!on);const i=b.querySelector('i');if(i)i.className=`bi ${on?'bi-check-lg':'bi-plus-lg'}`;const s=b.querySelector('span');if(s)s.textContent=on?'Following':'Follow'})}
const getPlaylists=()=>ls.get(KEY.pls,[]);
const savePlaylists=p=>{ls.set(KEY.pls,p);if(typeof renderSidebarPlaylists==='function')renderSidebarPlaylists()};
function createPlaylist(n){n=(n||'').trim();if(!n)return null;const pl={id:'pl_'+Date.now().toString(36)+Math.random().toString(36).slice(2,6),name:n,tracks:[],createdAt:Date.now(),isPublic:false};const a=getPlaylists();a.push(pl);savePlaylists(a);toast(`Created "${n}"`);return pl}
function removeFromPlaylist(pid,tid){const a=getPlaylists(),pl=a.find(p=>p.id===pid);if(!pl)return;pl.tracks=pl.tracks.filter(t=>String(t.trackId)!==String(tid));savePlaylists(a);if(isPlDet(pid))renderPlaylistDetail(pid)}
const getRecentlyPlayed=()=>ls.get(KEY.plays,[]);
function pushRecentlyPlayed(it){if(!it?.trackId)return;const id=String(it.trackId),l=getRecentlyPlayed().filter(x=>String(x.trackId)!==id);l.unshift({trackId:id,trackName:it.trackName||'Track',artistName:it.artistName||'',artistId:it.artistId?String(it.artistId):'',collectionName:it.collectionName||'',collectionId:it.collectionId?String(it.collectionId):'',artworkUrl:getArtwork(it,100)||it.artworkUrl||'',at:Date.now()});ls.set(KEY.plays,l.slice(0,30))}
const getNotes=()=>ls.get(KEY.notes,{});
const getNote=id=>getNotes()[String(id)]||'';
function setNote(id,txt){const m=getNotes();if(txt&&txt.trim())m[String(id)]={t:txt.trim(),at:Date.now()};else delete m[String(id)];ls.set(KEY.notes,m);toast(txt?'Note saved':'Note removed')}
async function editNote(id){const cur=getNote(id);const v=await askText(cur?'Edit note':'Add note',cur,'Personal note about this track');if(v===null)return;setNote(id,v);if(currentPath()==='/track/'+id)viewTrack(id)}

let _sc=null;
const getStats=()=>{if(!_sc)_sc=ls.get(KEY.stats,{totalMs:0,plays:0,tracks:{},days:{}});if(!_sc||typeof _sc!=='object')_sc={totalMs:0,plays:0,tracks:{},days:{}};if(!_sc.tracks||typeof _sc.tracks!=='object')_sc.tracks={};if(!_sc.days||typeof _sc.days!=='object')_sc.days={};return _sc};
const saveStats=()=>{if(_sc)ls.set(KEY.stats,_sc)};
let _sst=0;
function _statsRecord(ms){
  if(!Player.track?.trackId||ms<1000)return;
  const s=getStats();
  s.totalMs=(s.totalMs||0)+ms;
  const id=String(Player.track.trackId);
  if(!s.tracks[id])s.tracks[id]={count:0,ms:0,name:Player.track.trackName||'Track',artist:Player.track.artistName||'',artistId:Player.track.artistId||'',artwork:getArtwork(Player.track,100)||'',lastAt:0};
  s.tracks[id].count++;s.tracks[id].ms+=ms;s.tracks[id].lastAt=Date.now();
  const day=new Date().toISOString().slice(0,10);
  s.days[day]=(s.days[day]||0)+ms;
  if(Object.keys(s.days).length>180){const ks=Object.keys(s.days).sort();for(let i=0;i<ks.length-180;i++)delete s.days[ks[i]]}
  const keys=Object.keys(s.tracks);
  if(keys.length>1000){const keep=keys.sort((a,b)=>(s.tracks[b].lastAt||0)-(s.tracks[a].lastAt||0)).slice(0,1000);const nu={};for(const k of keep)nu[k]=s.tracks[k];s.tracks=nu}
  saveStats();
}
function statsStart(){if(!Player.track?.trackId){_sst=0;return}_sst=Date.now();const s=getStats();s.plays=(s.plays||0)+1;saveStats()}
function statsTick(){if(!_sst||!Player.track?.trackId)return;const now=Date.now();const ms=now-_sst;if(ms<1000)return;_sst=now;_statsRecord(ms)}
function statsStop(){if(!_sst)return;const ms=Date.now()-_sst;_sst=0;if(ms>=1000)_statsRecord(ms)}
function resetStats(){_sc={totalMs:0,plays:0,tracks:{},days:{}};saveStats();toast('Stats cleared')}

const audio=new Audio();audio.preload='metadata';
audio.volume=(()=>{const v=ls.get(KEY.vol,null);return typeof v==='number'&&v>=0&&v<=1?v:.85})();
(function unlockAudioOnGesture(){
  if(audio.__unlockInstalled)return;audio.__unlockInstalled=true;
  const unlock=()=>{try{const A=window.AudioContext||window.webkitAudioContext;if(A){const c=new A();c.resume().then(()=>c.close()).catch(()=>{})}}catch{}
    document.removeEventListener('touchstart',unlock,true);document.removeEventListener('click',unlock,true)};
  document.addEventListener('touchstart',unlock,{capture:true,once:true,passive:true});
  document.addEventListener('click',unlock,{capture:true,once:true});
})();
function crossfadeOut(ms){return new Promise(res=>{if(!ms||ms<200||!Player.playing){res();return}const sv=audio.volume,t0=performance.now();const s=()=>{const t=(performance.now()-t0)/ms;if(t>=1){audio.volume=sv;res();return}audio.volume=Math.max(0,sv*(1-t));requestAnimationFrame(s)};s()})}
let _fi=false;
audio.addEventListener('play',()=>{const x=Number(getSettings().crossfade||0);if(x&&_fi){_fi=false;const tg=Number(ls.get(KEY.vol,null))||.85;audio.volume=0;const t0=performance.now();const s=()=>{const t=(performance.now()-t0)/x;if(t>=1||!Player.playing){audio.volume=tg;return}audio.volume=tg*t;requestAnimationFrame(s)};s()}});
let _xtr=false;
audio.addEventListener('timeupdate',()=>{const x=Number(getSettings().crossfade||0);if(!x||_xtr||!audio.duration)return;if(audio.duration-audio.currentTime<=x/1000){_xtr=true;_fi=true;crossfadeOut(x)}});
audio.addEventListener('play',()=>{_xtr=false});
function abToggle(){const ab=Player.abRepeat;if(ab.a==null){ab.a=audio.currentTime;if(ab.b!=null&&ab.b<=ab.a)ab.b=null;toast(`A · ${fmtTime(ab.a)}`)}else if(ab.b==null){ab.b=audio.currentTime;if(ab.b<=ab.a+.5){ab.b=null;toast('B too close','warning');return}toast(`B · ${fmtTime(ab.b)}`)}else{Player.abRepeat={a:null,b:null};toast('A-B cleared')}updateAbChip()}
function updateAbChip(root){
  const scope=root||document;
  scope.querySelectorAll('.ab-chip').forEach(c=>{
    const ab=Player.abRepeat;
    if(ab.a==null){c.classList.add('off');c.innerHTML='';return}
    c.classList.remove('off');
    c.innerHTML=`<i class="bi bi-repeat"></i> A ${fmtTime(ab.a)} · B ${ab.b!=null?fmtTime(ab.b):'—'}`
  })
}
audio.addEventListener('timeupdate',()=>{const ab=Player.abRepeat;if(ab.a!=null&&ab.b!=null&&audio.currentTime>=ab.b){try{audio.currentTime=ab.a}catch{}}});
window.setSleepTimer=function(m){sheet('#sheetSleep').hide();if(Player.sleepTimer){clearTimeout(Player.sleepTimer);Player.sleepTimer=null}const i=$('#sleepIcon');const pb=$('#pbSleepIcon');if(!m){if(i)i.className='bi bi-moon';if(pb)pb.className='bi bi-moon';toast('Sleep timer off');return}if(i)i.className='bi bi-moon-fill';if(pb)pb.className='bi bi-moon-fill';Player.sleepTimer=setTimeout(async()=>{const s=getSettings();if(s.sleepFade!==false)await crossfadeOut(3000);audio.pause();audio.volume=Number(ls.get(KEY.vol,null))||.85;Player.sleepTimer=null;if(i)i.className='bi bi-moon';if(pb)pb.className='bi bi-moon';toast('Sleep timer — paused')},m*60000);toast(`Sleep · ${m} min${getSettings().sleepFade!==false?' (fades out)':''}`)};
function openSleepTimer(){sheet('#sheetSleep').show()}

function exportData(){const d={__mm_backup:2,exportedAt:new Date().toISOString(),likes:ls.get(KEY.likes,[]),playlists:ls.get(KEY.pls,[]),following:ls.get(KEY.following,[]),recent:ls.get(KEY.recent,[]),plays:ls.get(KEY.plays,[]),stats:ls.get(KEY.stats,{}),settings:ls.get(KEY.settings,{}),notes:ls.get(KEY.notes,{})};const b=new Blob([JSON.stringify(d,null,2)],{type:'application/json'}),u=URL.createObjectURL(b),a=document.createElement('a');a.href=u;a.download=`musicman-${new Date().toISOString().slice(0,10)}.json`;document.body.appendChild(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(u),5000);toast('Backup downloaded')}
function importData(){const i=document.createElement('input');i.type='file';i.accept='.json,application/json';i.onchange=async()=>{const f=i.files?.[0];if(!f)return;try{const d=JSON.parse(await f.text());if(!d.__mm_backup)throw new Error('Not a MusicMan backup');const ok=await askConfirm('Import backup?','Current data will be replaced.','Import','primary');if(!ok)return;[['likes',d.likes],['pls',d.playlists],['following',d.following],['recent',d.recent],['plays',d.plays]].forEach(([k,v])=>{if(Array.isArray(v))ls.set(KEY[k],v)});if(d.stats){_sc=d.stats;saveStats()}if(d.notes)ls.set(KEY.notes,d.notes);if(d.settings)ls.set(KEY.settings,d.settings);applySettings();toast('Backup restored');sheet('#sheetSettings').hide();const y=MAIN().scrollTop;await route();MAIN().scrollTop=y}catch(e){toast('Import failed: '+e.message,'danger')}};i.click()}

function currentPath(){let p=location.pathname;if(BASE&&p.startsWith(BASE))p=p.slice(BASE.length);if(!p)p='/';if(!p.startsWith('/'))p='/'+p;p=p.replace(/\/+$/,'');return p||'/'}
function parseRoute(){const path=currentPath().replace(/^\/+/,''),parts=path?path.split('/'):[],q=new URLSearchParams(location.search||'');return{parts,q}}
function go(path){if(!path)path='/';if(!path.startsWith('/'))path='/'+path;const target=(BASE||'')+path,current=location.pathname+location.search;if(current===target){route();return}try{history.pushState(null,'',target)}catch{location.href=target;return}route()}
window.goBack=function(){if(history.length>1)history.back();else go('/')};
window.addEventListener('popstate',()=>route());
document.addEventListener('click',e=>{const a=e.target.closest('a[href]');if(!a)return;if(a.target==='_blank'||a.hasAttribute('download'))return;const href=a.getAttribute('href');if(!href)return;if(/^(https?:|mailto:|tel:|javascript:)/i.test(href))return;if(href.startsWith('#'))return;let p=href;if(BASE&&p.startsWith(BASE))p=p.slice(BASE.length);if(!p.startsWith('/'))return;e.preventDefault();go(p||'/')});
document.addEventListener('mouseover',e=>{
  if(!IS_DESKTOP())return;
  const a=e.target.closest('a[href*="/track/"]');if(!a)return;
  const m=a.getAttribute('href').match(/\/track\/([A-Za-z0-9_\-]+)/);if(!m)return;
  const id=m[1];if(getCached('track',id))return;
  if(_ftCache.has(id)&&_ftCache.get(id).exp>Date.now())return;
  DL._fetchTrack(id).catch(()=>{});
},{passive:true});
const isLibLikes=()=>currentPath()==='/library/likes';
const isLibFoll=()=>currentPath()==='/library/following';
const isLibDl=()=>currentPath()==='/library/downloads';
const isLibPl=()=>currentPath()==='/library/playlists';
const isLibHist=()=>currentPath()==='/library/history';
const isLibStats=()=>currentPath()==='/library/stats';
const isPlDet=id=>currentPath()==='/library/playlists/'+id;
const isTrackRoute=()=>currentPath().startsWith('/track/');
function activeNavFor(p){const f=p[0]||'';if(f==='search')return'search';if(f==='library')return'library';if(f==='')return'home';return''}
const NAV_ICONS={home:{on:'bi-house-door-fill',off:'bi-house-door'},search:{on:'bi-search',off:'bi-search'},library:{on:'bi-collection-fill',off:'bi-collection'}};
function updateNav(p){const a=activeNavFor(p);$$('[data-nav]').forEach(el=>{const k=el.dataset.nav,on=k===a;el.classList.toggle('active',on);const i=el.querySelector('i');if(i&&NAV_ICONS[k])i.className=`bi ${on?NAV_ICONS[k].on:NAV_ICONS[k].off}`});const bt=$('#brandText'),bar=$('#barTitle');if(p.length===0){if(bt)bt.parentElement.style.display='';if(bar){bar.classList.add('d-none');bar.textContent='MusicMan'}if(bt)bt.classList.remove('d-none')}else{if(bt)bt.classList.add('d-none');if(bar){bar.classList.remove('d-none');bar.textContent=titleFor(p)}}}
function setPageTitle(t){const site='MusicMan';document.title=t?`${t} · ${site}`:`${site} — Music Player`;const bar=$('#barTitle');if(bar)bar.textContent=t||site}
function titleFor(p){if(p[0]==='search')return'Search';if(p[0]==='library')return p[1]?p[1].charAt(0).toUpperCase()+p[1].slice(1):'Library';if(p[0]==='artist')return'Artist';if(p[0]==='album')return'Album';if(p[0]==='track')return'Track';if(p[0]==='p')return'Playlist';return'Home'}

let _navGen=0,_pageTracks=[];
async function route(){
  const gen=++_navGen;
  closeAllSheets();disconnectArtistTracksObserver();if(typeof dlUnsub==='function'&&dlUnsub){dlUnsub();dlUnsub=null}
  _pageTracks=[];
  const{parts,q}=parseRoute();updateNav(parts);syncSidebarNav();closeFullPlayer();showSkeleton();
  try{
    switch(parts[0]){
      case undefined:setPageTitle('');await viewHome();break;
      case'search':setPageTitle(q.get('q')?`Search: ${q.get('q')}`:'Search');await viewSearch(q.get('q')||'');break;
      case'artist':await viewArtist(parts[1]);break;
      case'album':await viewAlbum(parts[1]);break;
      case'track':await viewTrack(parts[1]);break;
      case'p':await viewPublicPlaylist(parts[1]);break;
      case'library':{
        if(parts[1]==='playlists'){if(parts[2])renderPlaylistDetail(parts[2]);else{setPageTitle('Playlists');viewPlaylists()}}
        else if(parts[1]==='downloads'){setPageTitle('Offline Downloads');renderDownloads()}
        else if(parts[1]==='following'){setPageTitle('Following');viewFollowing()}
        else if(parts[1]==='history'){setPageTitle('Listening History');viewHistory()}
        else if(parts[1]==='stats'){setPageTitle('Listening Stats');viewStats()}
        else{setPageTitle('Liked Songs');viewLikes()}
        break;
      }
      default:setPageTitle('');await viewHome()
    }
    if(gen!==_navGen)return;
  }catch(e){if(gen!==_navGen)return;console.error('[route]',e);MAIN().innerHTML=errorState(e.message)}
  refreshLikes();refreshFollowButtons();MAIN().scrollTop=0;updateRightPanel();scrollActiveLibTab()
}
function showSkeleton(){
  const reps=IS_LOW_END?4:7;
  MAIN().innerHTML=`<div class="px-3 pt-4 pb-4">
    <div class="sk mb-3" style="height:28px;width:40%;border-radius:8px"></div>
    <div class="sk mb-4" style="height:14px;width:60%;border-radius:6px"></div>
    <div class="d-flex gap-3 mb-4 overflow-hidden" style="margin:0 -12px;padding:0 12px">
      ${`<div class="sk-card"><div class="sk sk-card-art"></div><div class="sk" style="height:13px;width:80%"></div><div class="sk" style="height:11px;width:55%"></div></div>`.repeat(4)}
    </div>
    <div class="sk mb-3" style="height:20px;width:30%;border-radius:6px"></div>
    <div class="d-flex flex-column gap-2">
      ${Array.from({length:reps}).map(()=>`<div class="sk-row"><div class="sk sk-row-play"></div><div class="sk sk-row-art"></div><div class="flex-grow-1 min-w-0 d-flex flex-column gap-2"><div class="sk" style="height:14px;width:70%"></div><div class="sk" style="height:11px;width:45%"></div></div></div>`).join('')}
    </div>
  </div>`;
}
function cardAlbum(c){const a=getArtwork(c,300);return`<a class="card-item" href="${SCOPE}album/${esc(c.collectionId)}" data-link>${a?`<img class="card-art" src="${esc(a)}" loading="lazy" decoding="async" alt="">`:`<div class="card-art card-art-ph"><i class="bi bi-disc"></i></div>`}<div class="card-name text-truncate">${esc(c.collectionName||'Album')}</div><div class="card-sub text-truncate">${esc(c.artistName||'')}</div></a>`}
function cardTrack(t){const a=getArtwork(t,300);return`<a class="card-item" href="${SCOPE}track/${esc(t.trackId)}" data-link>${a?`<img class="card-art" src="${esc(a)}" loading="lazy" decoding="async" alt="">`:`<div class="card-art card-art-ph"><i class="bi bi-music-note"></i></div>`}<div class="card-name text-truncate">${esc(t.trackName||'Track')}</div><div class="card-sub text-truncate">${esc(t.artistName||'')}</div></a>`}
function cardTrackPopular(t){const a=getArtwork(t,300),v=Number(t.views)||0,vl=v>=1000?(v/1000).toFixed(v>=10000?0:1)+'K':String(v);const sub=[t.artistName].filter(Boolean).join(' · ');return`<a class="card-item" href="${SCOPE}track/${esc(t.trackId)}" data-link>${a?`<img class="card-art" src="${esc(a)}" loading="lazy" decoding="async" alt="">`:`<div class="card-art card-art-ph"><i class="bi bi-music-note"></i></div>`}<div class="card-name text-truncate">${esc(t.trackName||'Track')}</div><div class="card-sub text-truncate">${esc(sub)}</div></a>`}
function cardArtist(a){const ar=getArtwork(a,300);return`<a class="artist-item" href="${SCOPE}artist/${esc(a.artistId)}" data-link>${ar?`<img class="artist-art" src="${esc(ar)}" loading="lazy" decoding="async" alt="">`:`<div class="artist-art artist-art-ph"><i class="bi bi-person-fill"></i></div>`}<div class="card-name text-truncate">${esc(a.artistName||'Artist')}</div></a>`}

/* ── trackRow (FIXED: data-preview attribute for correct play-circle icon) ── */
function trackRow(item,opts={}){
  if(!item)return'';
  const id=String(item.trackId||''),liked=isLiked(id),cached=isCached(id),dl=DL.get(id);
  const art=getArtwork(item,100)||item.artworkUrl||'';
  const dur=item.trackTimeMillis?fmtTime(item.trackTimeMillis/1000):(item.duration?fmtTime(item.duration):'');
  const isCur=Player.track&&String(Player.track.trackId)===id;
  const isPlaying=isCur&&Player.playing;
  const fa=hasAudio(item);
  const po=!fa&&hasPreview(item)&&!cached;
  const artistName=item.artistName||'';
  const albumName=item.collectionName||item.albumName||'';
  const subParts=[];
  if(artistName)subParts.push(artistLink(item,true));
  if(albumName&&!opts.hideAlbum)subParts.push(albumLink(item,true));
  if(dur)subParts.push(dur);
  if(opts.extraSub)subParts.push(esc(opts.extraSub));
  const sub=subParts.filter(Boolean).join(' · ');
  let badge='';
  if(cached)badge=badgeChip('crawled','bi-cloud-check-fill','Offline');
  else if(dl&&['queued','crawling','saving'].includes(dl.status))badge=`<span class="badge-chip crawling"><span class="spinner-border"></span>${dl.status==='saving'?'Downloading':'Crawling'}</span>`;
  else if(dl&&dl.status==='ready')badge=badgeChip('ready','bi-cloud-download','Ready');
  else if(po)badge=badgeChip('preview','bi-play-circle','Preview');
  const em=opts.playlistId?`,'${esc(opts.playlistId)}'`:'';
  const iconCls=isPlaying?'bi-pause-fill':(po?'bi-play-circle':'bi-play-fill');
  return`<div class="row-item ${isCur?'playing':''}" data-track-id="${esc(id)}">
    <button class="row-play ${isPlaying?'playing':''} ${po?'is-preview':''}" data-play-id="${esc(id)}" data-preview="${po?1:0}" onclick="event.preventDefault();event.stopPropagation();playTrackFromList('${esc(id)}')" aria-label="Play"><i class="bi ${iconCls}"></i></button>
    <a class="row-info" href="${SCOPE}track/${esc(id)}" data-link>
      ${art?`<img class="row-art" src="${esc(art)}" loading="lazy" decoding="async" alt="">`:`<div class="row-art row-art-ph"><i class="bi bi-music-note"></i></div>`}
      <div class="min-w-0 flex-grow-1">
        <div class="row-title text-truncate">${esc(item.trackName||'Track')}${badge?' '+badge:''}</div>
        <div class="row-sub text-truncate">${sub}</div>
      </div>
    </a>
    <div class="row-actions">
      <button class="icon-btn sm ${liked?'liked':''}" data-like="${esc(id)}" onclick="event.preventDefault();event.stopPropagation();toggleLikeById('${esc(id)}')" aria-label="Like"><i class="bi ${liked?'bi-heart-fill':'bi-heart'}"></i></button>
      <button class="icon-btn sm" onclick="event.preventDefault();event.stopPropagation();openTrackMenu('${esc(id)}'${em})" aria-label="More"><i class="bi bi-three-dots-vertical"></i></button>
    </div>
  </div>`;
}

async function viewHome(){
  const[fr,po]=await Promise.all([apiFresh().catch(()=>[]),apiPopular(40).catch(()=>[])]);
  cacheItems(fr);cacheItems(po);
  const artists=fr.filter(i=>itemType(i)==='artist'),albums=fr.filter(i=>itemType(i)==='collection'),tracks=fr.filter(i=>itemType(i)==='track');
  const seen=new Set();const pop=po.filter(i=>itemType(i)==='track').filter(i=>{const id=String(i.trackId||'');if(!id||seen.has(id))return false;seen.add(id);return true});
  const recents=getRecentlyPlayed();
  const h=new Date().getHours(),greet=h<5?'Good night':h<12?'Good morning':h<18?'Good afternoon':'Good evening';
  let html=`<div class="pb-4"><div class="px-3 pt-4 pb-1"><div style="font-size:1.5rem;font-weight:800;letter-spacing:-.02em">${greet}</div><div class="text-secondary" style="font-size:.82rem">Welcome back to MusicMan</div></div>`;
  if(recents.length){html+=secTitle('Recently Played');html+=`<div class="mm-grid">${recents.slice(0,10).map(r=>cardTrack({wrapperType:'track',trackId:r.trackId,trackName:r.trackName,artistName:r.artistName,artistId:r.artistId,collectionName:r.collectionName,collectionId:r.collectionId,attachments:{artworkUrls:r.artworkUrl?[{url:r.artworkUrl}]:[]}})).join('')}</div>`}
  if(pop.length){html+=secTitle('Popular');html+=`<div class="mm-grid">${pop.slice(0,20).map(cardTrackPopular).join('')}</div>`}
  if(tracks.length){html+=secTitle(recents.length?'Fresh Music':'Fresh');html+=`<div class="mm-grid">${tracks.map(cardTrack).join('')}</div>`}
  if(artists.length){html+=secTitle('Fresh Artists');html+=`<div class="mm-grid mm-grid-narrow">${artists.map(cardArtist).join('')}</div>`}
  if(albums.length){html+=secTitle('Fresh Albums');html+=`<div class="mm-grid">${albums.map(cardAlbum).join('')}</div>`}
  if(!fr.length&&!po.length&&!recents.length)html+=emptyState('collection','Nothing to show yet','Try searching for something.');
  html+=`</div>`;MAIN().innerHTML=html
}
function playSmartShuffle(){const s=getStats(),tracks=s.tracks||{},likes=getLikes();const ids=[...new Set([...Object.keys(tracks),...likes.map(l=>String(l.trackId))])];const shuffled=[...ids].sort(()=>Math.random()-.5).slice(0,100);if(!shuffled.length){toast('Nothing to shuffle','warning');return}playIds(shuffled.join(','))}

let searchFilter='all',searchCache={term:null,items:null};
function searchBarHtml(t=''){return`<div class="search-wrap"><form onsubmit="event.preventDefault();submitSearch()"><div class="search-box"><i class="bi bi-search"></i><input id="searchInput" type="search" placeholder="Songs, albums, artists…" value="${esc(t)}" autocomplete="off" enterkeyhint="search" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="searchSuggest">${t?`<button type="button" class="icon-btn sm" onclick="clearSearch()" aria-label="Clear"><i class="bi bi-x-circle-fill"></i></button>`:''}</div><div class="search-suggest" id="searchSuggest" role="listbox" aria-label="Search suggestions"></div></form>${t?`<div class="filter-row"><button class="chip ${searchFilter==='all'?'on':''}" onclick="setSearchFilter('all')">All</button><button class="chip ${searchFilter==='artist'?'on':''}" onclick="setSearchFilter('artist')">Artists</button><button class="chip ${searchFilter==='album'?'on':''}" onclick="setSearchFilter('album')">Albums</button><button class="chip ${searchFilter==='track'?'on':''}" onclick="setSearchFilter('track')">Songs</button></div>`:''}</div>`}
function wireSearchSuggest(f){requestAnimationFrame(()=>{const i=document.getElementById('searchInput'),d=document.getElementById('searchSuggest');if(i&&d)initSuggest(i,d);if(f&&i&&document.activeElement===document.body)i.focus()})}
function setSearchFilter(f){searchFilter=f;if(searchCache.term&&searchCache.items){renderSearchResults(searchCache.term,searchCache.items);refreshLikes()}else{const{q}=parseRoute();viewSearch(q.get('q')||'')}}
function submitSearch(){const q=($('#searchInput')?.value||'').trim();if(!q)return;const l=ls.get(KEY.recent,[]).filter(x=>x!==q);l.unshift(q);ls.set(KEY.recent,l.slice(0,12));searchFilter='all';go('/search?q='+encodeURIComponent(q))}
function clearSearch(){const i=$('#searchInput');if(i){i.value='';i.focus()}}
function clearRecent(){ls.remove(KEY.recent);viewSearch('')}
function removeRecent(term){const l=ls.get(KEY.recent,[]).filter(x=>x!==term);ls.set(KEY.recent,l);viewSearch('')}
async function viewSearch(term){
  if(!term){const r=ls.get(KEY.recent,[]);let html=`<div class="pb-4">${searchBarHtml('')}`;
    if(r.length){html+=`<div class="d-flex align-items-center px-3 pt-4 pb-2"><div class="sec-title p-0 flex-grow-1">Recent searches</div><button class="icon-btn sm" onclick="clearRecent()" aria-label="Clear"><i class="bi bi-trash3"></i></button></div>`;
    html+=`<div class="px-3 pb-2">`+r.map(x=>`<div class="d-inline-flex align-items-center gap-2 me-2 mb-2" style="background:var(--mm-surface-2);border-radius:999px;padding:4px 6px 4px 14px;font-size:.82rem"><i class="bi bi-clock-history" style="opacity:.6"></i><span style="cursor:pointer" onclick="go('/search?q=${encodeURIComponent(x)}')">${esc(x)}</span><button class="icon-btn sm" onclick="event.stopPropagation();removeRecent(${JSON.stringify(x).replace(/"/g,'&quot;')})" style="width:24px;height:24px;font-size:.75rem"><i class="bi bi-x-lg"></i></button></div>`).join('')+`</div>`}
    else html+=emptyState('search','Find your music','Search for songs, albums and artists.');
    html+=`</div>`;MAIN().innerHTML=html;wireSearchSuggest(true);return}
  let items;if(searchCache.term===term&&searchCache.items)items=searchCache.items;else{items=await apiSearch(term);searchCache={term,items};cacheItems(items)}
  renderSearchResults(term,items)
}
function renderSearchResults(term,items){
  const artists=items.filter(i=>itemType(i)==='artist'),albums=items.filter(i=>itemType(i)==='collection'),tracks=items.filter(i=>itemType(i)==='track');
  const sT=searchFilter==='all'||searchFilter==='track',sA=searchFilter==='all'||searchFilter==='album',sAr=searchFilter==='all'||searchFilter==='artist';
  let html=`<div class="pb-4">${searchBarHtml(term)}`;
  if(!items.length)html+=emptyState('emoji-frown',`No results for "${term}"`,'Try different keywords.');
  else{let has=false;
    if(sAr&&artists.length){has=true;html+=secTitle(`Artists · ${artists.length}`);html+=`<div class="mm-grid mm-grid-narrow">${artists.map(cardArtist).join('')}</div>`}
    if(sA&&albums.length){has=true;html+=secTitle(`Albums · ${albums.length}`);html+=`<div class="mm-grid">${albums.map(cardAlbum).join('')}</div>`}
    if(sT&&tracks.length){has=true;html+=secTitle(`Songs · ${tracks.length}`);html+=tracks.map(t=>trackRow(t)).join('')}
    if(!has)html+=emptyState('funnel','No matches for this filter')}
  html+=`</div>`;MAIN().innerHTML=html;wireSearchSuggest(false)
}

const _at={artistId:null,sort:'album',page:0,limit:50,total:0,pages:0,hasMore:false,loading:false,items:[]};
let _ato=null;
function disconnectArtistTracksObserver(){if(_ato){_ato.disconnect();_ato=null}}
async function loadArtistTracks(aid,{reset=false,sort=null}={}){
  if(!aid)return;const st=_at;
  if(reset||st.artistId!==String(aid)){st.artistId=String(aid);st.page=0;st.items=[];st.total=0;st.pages=0;st.hasMore=false}
  if(sort)st.sort=sort;if(st.loading||(st.page>0&&!st.hasMore))return;
  st.loading=true;const np=st.page+1,sent=document.getElementById('artistTracksSentinel'),list=document.getElementById('artistTracksList');
  if(sent)sent.innerHTML=`<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>`;
  try{
    const res=await apiArtistTracks(aid,{page:np,limit:st.limit,sort:st.sort});
    const tracks=(res.results||[]).filter(t=>itemType(t)==='track');
    st.page=np;st.total=res.total??tracks.length;st.pages=res.pages??1;st.hasMore=!!res.hasMore;st.items=st.items.concat(tracks);cacheItems(tracks);_pageTracks=st.items;
    if(list&&tracks.length)list.insertAdjacentHTML('beforeend',tracks.map(t=>trackRow(t)).join(''));
    const ce=document.getElementById('artistTrackCount');if(ce)ce.textContent=`${st.items.length} of ${st.total}`;
    const pb=document.getElementById('artistPlayAllBtn');if(pb)pb.disabled=st.items.length===0;
    const lb=document.getElementById('artistLikeAllBtn');if(lb){const al=st.items.length>0&&st.items.every(t=>isLiked(t.trackId));lb.classList.toggle('liked',al);const i=lb.querySelector('i');if(i)i.className=`bi ${al?'bi-heart-fill':'bi-heart'}`}
    refreshLikes()
  }catch(e){if(sent)sent.innerHTML=`<div class="text-center py-4"><button class="pill-btn" onclick="loadArtistTracks('${esc(aid)}')"><i class="bi bi-arrow-repeat"></i> Retry</button></div>`}
  finally{
    st.loading=false;
    if(sent&&st.hasMore)sent.innerHTML=`<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-secondary" style="opacity:.4"></div></div>`;
    else if(sent&&!st.hasMore)sent.innerHTML=st.items.length?`<div class="text-center py-4 text-secondary" style="font-size:.75rem">All ${st.total} track${st.total!==1?'s':''} loaded</div>`:''
  }
  requestAnimationFrame(()=>{if(!st.hasMore||st.loading)return;const s=document.getElementById('artistTracksSentinel'),m=document.getElementById('main');if(!s||!m)return;const r=s.getBoundingClientRect(),rr=m.getBoundingClientRect();if(r.top<rr.bottom+300)loadArtistTracks(aid)})
}
function attachArtistTracksObserver(){disconnectArtistTracksObserver();const root=document.getElementById('main'),sent=document.getElementById('artistTracksSentinel');if(!root||!sent)return;_ato=new IntersectionObserver(e=>{for(const x of e){if(!x.isIntersecting)continue;if(_at.loading||!_at.hasMore)continue;loadArtistTracks(_at.artistId)}},{root,rootMargin:'500px 0px',threshold:0.01});_ato.observe(sent)}
function setArtistSort(sort){if(_at.sort===sort)return;const l=document.getElementById('artistTracksList');if(l)l.innerHTML='';_at.items=[];_at.page=0;_at.hasMore=true;_at.sort=sort;$$('#artistSort .chip').forEach(b=>b.classList.toggle('on',b.dataset.sort===sort));loadArtistTracks(_at.artistId,{reset:true,sort})}
async function viewArtist(id){
  if(!id){MAIN().innerHTML=emptyState('person','Artist not found');return}
  _at.artistId=String(id);_at.sort='album';_at.page=0;_at.items=[];_at.hasMore=true;_at.loading=false;
  let a=getCached('artist',id);
  if(!a){try{const r=await apiLookup(id,'musicArtist');a=r.find(x=>itemType(x)==='artist')||r[0];if(a)cacheItems([a])}catch{}}
  if(!a){MAIN().innerHTML=emptyState('person','Artist not found');return}
  setPageTitle(a.artistName||'Artist');
  const aR=await apiLookup(id,'album').catch(()=>[]);
  const am=new Map();for(const x of aR.filter(x=>itemType(x)==='collection')){const cid=String(x.collectionId||'');if(cid&&!am.has(cid))am.set(cid,x)}
  const albums=[...am.values()];cacheItems(albums);const fullAlbums=albums.filter(x=>(x.trackCount||0)>1);
  // ── Artist image: prefer the artist's own image (e.g. Deezer), fall back to first album cover ──
let art = getArtwork(a, 600) || '';
if (!art) {
  for (const x of albums) {
    const ar = getArtwork(x, 600);
    if (ar) { art = ar; break; }
  }
}
  const fl=isFollowed(id),genre=a.primaryGenreName||'';
  const sp=[];if(genre)sp.push(esc(genre));if(albums.length)sp.push(`${albums.length} album${albums.length!==1?'s':''}`);sp.push(`<span id="artistTrackCount">0 of —</span>`);
  let html=`<div class="pb-4">${backBtn()}<div class="hero">${art?`<img class="hero-art round" src="${esc(art)}" loading="lazy" decoding="async" alt="">`:`<div class="hero-art hero-art-ph round"><i class="bi bi-person-fill"></i></div>`}<div class="hero-title">${esc(a.artistName||'Artist')}</div><div class="hero-sub">${sp.join(' · ')}</div></div>`;
  html+=`<div class="action-bar scrollable"><button class="pill-btn success" id="artistPlayAllBtn" disabled onclick="playIds(_pageTracks.map(t=>t.trackId).join(','))"><i class="bi bi-play-fill"></i> Play all</button><button class="pill-btn ${fl?'':'primary'}" data-follow="${esc(id)}" onclick="toggleFollow('${esc(id)}','${esc(art)}')"><i class="bi ${fl?'bi-check-lg':'bi-plus-lg'}"></i><span>${fl?'Following':'Follow'}</span></button><button class="pill-btn" onclick="artistRadio('${esc(id)}')"><i class="bi bi-broadcast"></i> Radio</button><button class="pill-btn info" onclick="crawlAll('artist','${esc(id)}',this,false)"><i class="bi bi-cloud-arrow-down"></i> Crawl all</button><a class="pill-btn telegram" href="${tgLink('artist',id)}" target="_blank" rel="noopener"><i class="bi bi-telegram"></i></a><button class="icon-btn" style="background:rgba(var(--bs-body-color-rgb),.09)" onclick="artistMore('${esc(id)}')"><i class="bi bi-three-dots"></i></button></div>`;
  if(fullAlbums.length){html+=secTitle(`Albums · ${fullAlbums.length}`);html+=`<div class="mm-grid">${fullAlbums.map(cardAlbum).join('')}</div>`}
  html+=`<div class="d-flex align-items-center gap-2 px-3" style="margin-top:8px"><div class="sec-title p-0 flex-grow-1" style="padding-top:0">Tracks</div></div><div class="filter-row" id="artistSort" style="padding-top:2px"><button class="chip on" data-sort="album" onclick="setArtistSort('album')">Album</button><button class="chip" data-sort="recent" onclick="setArtistSort('recent')">Recent</button><button class="chip" data-sort="name" onclick="setArtistSort('name')">A–Z</button><button class="chip" data-sort="views" onclick="setArtistSort('views')">Popular</button></div><div id="artistTracksList"></div><div id="artistTracksSentinel"></div></div>`;
  MAIN().innerHTML=html;await loadArtistTracks(id,{reset:true,sort:'album'});attachArtistTracksObserver()
}
async function artistRadio(aid){const res=await apiArtistTracks(aid,{page:1,limit:30,sort:'views'}).catch(()=>null);if(!res||!res.results?.length){toast('Nothing to play','warning');return}const items=res.results.filter(t=>itemType(t)==='track');cacheItems(items);Player.queue=items;Player.index=0;Player.shuffle=true;renderFpTabBody();updateRightPanel();playItem(items[0],'Radio');toast(`Artist radio · ${items.length} tracks`)}
function artistMore(id){const r=[`<button class="sheet-item" onclick="closeAllSheets(); addAllToQueue(_pageTracks)"><i class="bi bi-list-ul"></i><span>Queue all tracks</span></button>`,`<button class="sheet-item" onclick="closeAllSheets(); openPlaylistPickerForItems(_pageTracks)"><i class="bi bi-plus-lg"></i><span>Add all to playlist</span></button>`,`<button class="sheet-item" onclick="closeAllSheets(); toggleLikeAll(_pageTracks)"><i class="bi bi-heart"></i><span>Like / unlike all</span></button>`,`<button class="sheet-item telegram" onclick="closeAllSheets(); tgStart('artist','${esc(id)}')"><i class="bi bi-telegram"></i><span>Open in Telegram bot</span></button>`];$('#sheetTrackBody').innerHTML=`<div class="px-3 pb-2 pt-1"><div class="fw-bold">Artist actions</div></div><div class="px-2">${r.join('')}</div>`;sheet('#sheetTrack').show()}

async function viewAlbum(id){
  if(!id){MAIN().innerHTML=emptyState('disc','Album not found');return}
  let a=getCached('collection',id);
  if(!a){try{const r=await apiLookup(id,'album');a=r.find(x=>itemType(x)==='collection')||r[0];if(a)cacheItems([a])}catch{}}
  if(!a){MAIN().innerHTML=emptyState('disc','Album not found');return}
  setPageTitle(`${a.collectionName||'Album'}${a.artistName?' — '+a.artistName:''}`);
  const tracks=(await apiLookup(id,'song')).filter(x=>itemType(x)==='track');cacheItems(tracks);
  const art=getArtwork(a,600),missing=tracks.filter(t=>!hasAudio(t)&&!isCached(t.trackId)).length,ids=tracks.map(t=>String(t.trackId)).join(','),year=a.releaseDate?new Date(a.releaseDate).getFullYear():'';
  _pageTracks=tracks;
  const batchId=`batch_collection_${id}`,batch=DL.get(batchId);
  let batchBtn='';
  if(batch&&['queued','crawling'].includes(batch.status)){batchBtn=`<button class="pill-btn info" onclick="go('/library/downloads')"><span class="spinner-border spinner-border-sm me-1" style="width:14px;height:14px;border-width:2px"></span>Crawling · ${batch.readyCount||0}/${batch.trackCount||0}</button>`}
  else if(batch&&batch.status==='saving'){batchBtn=`<button class="pill-btn info" onclick="go('/library/downloads')"><span class="spinner-border spinner-border-sm me-1" style="width:14px;height:14px;border-width:2px"></span>Downloading · ${batch.percent||0}%</button>`}
  else if(batch&&batch.status==='ready'){batchBtn=`<button class="pill-btn warn" onclick="saveBatch('${esc(batchId)}')"><i class="bi bi-download"></i> Save all · ${batch.readyCount||0}/${batch.trackCount||0}</button>`}
  else if(batch&&batch.status==='failed'){batchBtn=`<button class="pill-btn danger" onclick="go('/library/downloads')"><i class="bi bi-exclamation-triangle"></i> View · ${batch.failedCount||0} failed</button>`}
  let html=`<div class="pb-4">${backBtn()}<div class="hero">${art?`<img class="hero-art" src="${esc(art)}" loading="lazy" decoding="async" alt="">`:`<div class="hero-art hero-art-ph"><i class="bi bi-disc"></i></div>`}<div class="hero-title">${esc(a.collectionName||'Album')}</div><div class="hero-sub">${artistLink(a)}${year?` · ${year}`:''}${a.trackCount?` · ${a.trackCount} tracks`:''}</div></div>`;
  html+=`<div class="action-bar scrollable">${tracks.length?`<button class="pill-btn success" onclick="playIds('${esc(ids)}')"><i class="bi bi-play-fill"></i> Play</button>`:''}${batchBtn}`;
  if(tracks.length&&!batch){html+=`<button class="pill-btn primary" onclick="crawlAll('collection','${esc(id)}',this,true)"><i class="bi bi-cloud-download"></i> Download all${missing?' ('+missing+')':''}</button>`}
  else if(tracks.length&&missing===0&&!batch){html+=`<span class="pill-btn" style="background:rgba(var(--bs-success-rgb),.16);color:var(--bs-success)"><i class="bi bi-check-circle-fill"></i> All ready</span>`}
  html+=`<a class="pill-btn telegram" href="${tgLink('album',id)}" target="_blank" rel="noopener"><i class="bi bi-telegram"></i></a>${tracks.length?`<button class="icon-btn" style="background:rgba(var(--bs-body-color-rgb),.09)" onclick="albumMore()"><i class="bi bi-three-dots"></i></button>`:''}</div>`;
  html+=tracks.length?tracks.map(t=>trackRow(t)).join(''):emptyState('music-note','No tracks found');html+=`</div>`;MAIN().innerHTML=html
}
function albumMore(){const r=[`<button class="sheet-item" onclick="closeAllSheets(); addAllToQueue(_pageTracks)"><i class="bi bi-list-ul"></i><span>Queue all tracks</span></button>`,`<button class="sheet-item" onclick="closeAllSheets(); openPlaylistPickerForItems(_pageTracks)"><i class="bi bi-plus-lg"></i><span>Add all to playlist</span></button>`,`<button class="sheet-item" onclick="closeAllSheets(); toggleLikeAll(_pageTracks)"><i class="bi bi-heart"></i><span>Like / unlike all</span></button>`];$('#sheetTrackBody').innerHTML=`<div class="px-3 pb-2 pt-1"><div class="fw-bold">Album actions</div></div><div class="px-2">${r.join('')}</div>`;sheet('#sheetTrack').show()}

let trackPoller=null,lyricsTrackId=null;
function stopTrackPoller(){if(trackPoller){clearInterval(trackPoller);trackPoller=null}}
async function viewTrack(id){
  if(!id){MAIN().innerHTML=emptyState('music-note','Track not found');return}
  stopTrackPoller();lyricsTrackId=String(id);
  let t=null;
  if(isCached(id)){const e=await cacheGet(id);if(e&&e.meta){t={wrapperType:'track',trackId:id,...e.meta}}}
  if(!t){try{const r=await apiLookup(id,'song');t=r.find(x=>itemType(x)==='track')||r[0];if(t)cacheItems([t])}catch{}}
  if(!t)t=getCached('track',id);
  if(!t){MAIN().innerHTML=emptyState('music-note','Track not found');return}
  setPageTitle(`${t.trackName||'Track'}${t.artistName?' — '+t.artistName:''}`);
  renderTrackPage(t);
  const dl=DL.get(id),crawling=dl&&['queued','crawling'].includes(dl.status);
  if(crawling||(!hasAudio(t)&&!isCached(id))){const st=await apiCrawlStatus(id);renderCrawlCard(t,st);if(crawling||(st&&['pending','downloading'].includes(st.download_status)))startTrackPoller(id)}else renderCrawlCard(t,null)
}
function renderTrackPage(t){
  const id=String(t.trackId),art=getArtwork(t,600),liked=isLiked(id),cached=isCached(id),crawled=hasAudio(t),previewable=!crawled&&hasPreview(t)&&!cached,playable=crawled||cached,dur=t.trackTimeMillis?fmtTime(t.trackTimeMillis/1000):'',year=t.releaseDate?new Date(t.releaseDate).getFullYear():'',dl=DL.get(id),action=dlActionFor(t);
  let html=`<div class="pb-4">${backBtn()}<div class="hero">${art?`<img class="hero-art" src="${esc(art)}" loading="lazy" decoding="async" alt="">`:`<div class="hero-art hero-art-ph"><i class="bi bi-music-note"></i></div>`}<div class="hero-title">${esc(t.trackName||'Track')}</div><div class="hero-sub">${artistLink(t)}${t.collectionId?` · ${albumLink(t)}`:''}</div><div class="hero-sub" style="font-size:.74rem">${[year,dur,t.primaryGenreName].filter(Boolean).join(' · ')}</div></div>`;
  let pb='';
  if(dl&&['queued','crawling','saving'].includes(dl.status)){const lb=dl.status==='queued'?'Queued':dl.status==='crawling'?'Crawling':'Downloading';pb=`<button class="pill-btn info" onclick="go('/library/downloads')"><span class="spinner-border spinner-border-sm me-1" style="width:14px;height:14px;border-width:2px"></span>${lb}${dl.percent?' · '+dl.percent+'%':''}</button>`}
  else if(dl&&dl.status==='ready'){pb=`<button class="pill-btn warn" onclick="DL.retry('${esc(id)}')"><i class="bi bi-cloud-download"></i> Ready to download</button>`}
  else if(!playable){if(previewable)pb=`<button class="pill-btn preview" onclick="playById('${esc(id)}')"><i class="bi bi-play-circle"></i> Play preview</button>`;else pb=`<button class="pill-btn primary" onclick="startCrawl('${esc(id)}',this)"><i class="bi ${action.icon}"></i> ${action.label}</button>`}
  else pb=`<button class="pill-btn success" onclick="playById('${esc(id)}')"><i class="bi bi-play-fill"></i> ${cached?'Play offline':'Play'}</button>`;
  html+=`<div class="action-bar">${pb}<a class="icon-btn" href="${tgLink('track',id)}" target="_blank" rel="noopener" style="background:rgba(41,171,226,.14);color:#29abe2" title="Open in Telegram bot"><i class="bi bi-telegram"></i></a><button class="icon-btn ${liked?'liked':''}" data-like="${esc(id)}" onclick="toggleLikeById('${esc(id)}')" style="background:rgba(var(--bs-body-color-rgb),.09)"><i class="bi ${liked?'bi-heart-fill':'bi-heart'}"></i></button><button class="icon-btn" onclick="openTrackMenu('${esc(id)}')" style="background:rgba(var(--bs-body-color-rgb),.09)"><i class="bi bi-three-dots"></i></button></div>`;
  html+=`<div id="longTip"></div><div id="crawlCard"></div><div id="noteCard"></div>`;
  html+=`<div class="lyrics-card"><div class="lyrics-head"><i class="bi bi-music-note-list"></i><span class="flex-grow-1">Lyrics</span><span class="lyrics-badge" id="lyricsBadge" style="display:none">Synced</span><button class="icon-btn sm" onclick="adjustLyricSize(-1)"><i class="bi bi-dash-lg"></i></button><button class="icon-btn sm" onclick="adjustLyricSize(1)"><i class="bi bi-plus-lg"></i></button><button class="icon-btn sm" onclick="copyLyrics()"><i class="bi bi-clipboard"></i></button></div><div class="lyrics-body" id="lyricsBody" style="font-size:calc(.85rem * var(--lyric-size,1))"><div class="text-secondary d-flex align-items-center gap-2" style="font-size:.82rem"><span class="spinner-border spinner-border-sm"></span> Loading lyrics…</div></div></div></div>`;
  MAIN().innerHTML=html;
  renderLongTrackTip(t);loadLyrics(id,t);renderNoteCard(id);applyLyricSize()
}
function renderLongTrackTip(t){
  const el=$('#longTip');if(!el)return;
  if(!isLongTrack(t)){el.innerHTML='';return}
  const id=String(t.trackId);
  el.innerHTML=`<div class="crawl-card longtip" style="flex-direction:column;align-items:stretch;gap:10px">
    <div class="d-flex align-items-start gap-2">
      <i class="bi bi-exclamation-triangle text-warning" style="font-size:1.5rem"></i>
      <div class="cc-body flex-grow-1"><div class="cc-title">Long track (over 8 minutes)</div><div class="cc-sub">This track is longer than 8 minutes and may fail to play or crawl. If it doesn't work, use the Telegram bot to download the full file.</div></div>
    </div>
    <div class="d-flex gap-2 flex-wrap"><a class="pill-btn telegram" href="${tgLink('track',id)}" target="_blank" rel="noopener"><i class="bi bi-telegram"></i> Get via Telegram bot</a></div>
  </div>`
}
function adjustLyricSize(delta){const s=getSettings();let v=Number(s.lyricSize)||1;v=Math.max(.7,Math.min(1.6,v+delta*0.1));setSetting('lyricSize',v);applyLyricSize()}
function applyLyricSize(){const v=getSettings().lyricSize||1;document.documentElement.style.setProperty('--lyric-size',v)}
function renderNoteCard(id){const el=$('#noteCard');if(!el)return;const n=getNote(id);el.innerHTML=`<div class="crawl-card idle" style="flex-direction:column;align-items:stretch"><div class="d-flex align-items-center gap-2"><i class="bi bi-journal-text" style="color:var(--bs-primary);font-size:1.3rem"></i><div class="cc-body flex-grow-1"><div class="cc-title">${n?'Your note':'Track notes'}</div>${n?`<div class="cc-sub" style="white-space:pre-wrap">${esc(n.t)}</div>`:`<div class="cc-sub">Keep a personal note about this track</div>`}</div><button class="pill-btn ${n?'sm':'primary sm'}" onclick="editNote('${esc(id)}')"><i class="bi bi-${n?'pencil':'plus-lg'}"></i> ${n?'Edit':'Add'}</button></div></div>`}
function renderCrawlCard(t,st){const el=$('#crawlCard');if(!el)return;const id=String(t.trackId),cached=isCached(id),dl=DL.get(id),action=dlActionFor(t),po=!hasAudio(t)&&hasPreview(t)&&!cached;
  if(cached){el.innerHTML=`<div class="crawl-card ready"><i class="bi bi-cloud-check-fill text-success" style="font-size:1.5rem"></i><div class="cc-body"><div class="cc-title">Saved offline</div><div class="cc-sub">Ready to play without internet</div></div><button class="pill-btn primary sm" onclick="exportCached('${esc(id)}')"><i class="bi bi-file-earmark-arrow-down"></i> Save file</button><button class="icon-btn sm text-danger" onclick="removeCached('${esc(id)}')"><i class="bi bi-trash3"></i></button></div>`;return}
  if(dl&&['queued','crawling','saving'].includes(dl.status)){const sv=dl.status==='saving',lb=dl.status==='queued'?(action.isDirect?'Queued to download':'Queued to crawl'):sv?'Downloading':'Crawling',pct=dl.percent||0;el.innerHTML=`<div class="crawl-card pending" style="flex-direction:column;align-items:stretch"><div class="d-flex align-items-center gap-2"><span class="spinner-border spinner-border-sm text-info"></span><span class="cc-title flex-grow-1">${lb}…</span><span class="fw-bold text-info" style="font-size:.8rem">${pct}%</span></div>${progressHtml(pct,'bg-info')}${sv&&dl.totalBytes?`<div class="cc-sub">${fmtSize(dl.bytes)} / ${fmtSize(dl.totalBytes)}</div>`:''}</div>`;return}
  if(dl&&dl.status==='ready'){el.innerHTML=`<div class="crawl-card ready"><i class="bi bi-cloud-download text-warning" style="font-size:1.5rem"></i><div class="cc-body"><div class="cc-title">Ready to download</div><div class="cc-sub">Crawl finished — click Save to keep offline</div></div><button class="pill-btn success sm" onclick="DL.retry('${esc(id)}')"><i class="bi bi-download"></i> Save</button><a class="pill-btn telegram sm" href="${tgLink('track',id)}" target="_blank" rel="noopener" title="Open in Telegram bot"><i class="bi bi-telegram"></i></a></div>`;return}
  if(dl&&dl.status==='failed'){const err=String(dl.error||'');const sizeIssue=/size|large|big|bigger|too\s*big|too\s*large|getFile|file\s*too/i.test(err);
    el.innerHTML=`<div class="crawl-card fail" style="flex-direction:column;align-items:stretch;gap:10px"><div class="d-flex align-items-center gap-2"><i class="bi bi-x-circle-fill text-danger" style="font-size:1.5rem"></i><div class="cc-body flex-grow-1"><div class="cc-title">${action.isDirect?'Download':'Crawl'} failed</div>${dl.error?`<div class="cc-sub text-truncate">${esc(dl.error)}</div>`:''}${sizeIssue?`<div class="cc-sub" style="color:#29abe2">File may be too large for direct download. Try the Telegram bot.</div>`:''}</div></div><div class="d-flex gap-2 flex-wrap"><button class="pill-btn danger sm" onclick="DL.retry('${esc(id)}')"><i class="bi bi-arrow-repeat"></i> Retry</button><a class="pill-btn telegram sm" href="${tgLink('track',id)}" target="_blank" rel="noopener"><i class="bi bi-telegram"></i> Get via bot</a><button class="icon-btn sm" onclick="DL.remove('${esc(id)}')"><i class="bi bi-x-lg"></i></button></div></div>`;return}
  const cc=po?'preview':'idle',ic=po?'var(--mm-preview)':'var(--bs-primary)',bc=po?'preview':'primary';
  el.innerHTML=`<div class="crawl-card ${cc}"><i class="bi ${action.icon}" style="color:${ic};font-size:1.7rem"></i><div class="cc-body"><div class="cc-title">${action.idleTitle}</div><div class="cc-sub">${action.idleSub}</div></div><button class="pill-btn ${bc}" onclick="startCrawl('${esc(id)}',this)"><i class="bi ${action.icon}"></i> ${action.label}</button><a class="pill-btn telegram" href="${tgLink('track',id)}" target="_blank" rel="noopener" title="Large file? Try the Telegram bot"><i class="bi bi-telegram"></i></a></div>`
}
function startTrackPoller(tid){stopTrackPoller();const id=String(tid);const tick=async()=>{const cur=currentPath().startsWith('/track/')?currentPath().split('/')[2]:null;if(String(cur)!==id){stopTrackPoller();return}const dl=DL.get(id),st=await apiCrawlStatus(id);if(!st||st.download_status==='completed'||(dl&&dl.status==='saving')){try{const f=await apiLookup(id,'song');const it=f.find(x=>itemType(x)==='track')||f[0];if(it&&hasAudio(it)){cacheItems([it]);if(currentPath().startsWith('/track/')){renderTrackPage(it);refreshLikes()}}}catch{}if(dl&&['queued','crawling','saving'].includes(dl.status))return;stopTrackPoller();return}
const c=getCached('track',id);if(c)renderCrawlCard(c,st);if(['failed','stopped'].includes(st?.download_status))stopTrackPoller()};tick();trackPoller=setInterval(tick,POLL_MS)}

let syncedLyricsCache={trackId:null,lines:null,synced:false,loading:false};
const numOrNull=v=>{if(v==null)return null;const n=Number(v);return isFinite(n)?n:null};
const INS_RE=/^(instrumental|no\s*lyrics?|no\s*lyrics?\s*available|lyrics?\s*not\s*available|lyrics?\s*not\s*found|not\s*found|instrumental\s*\/\s*not\s*found|instrumental\s*\/\s*(no\s*)?lyrics?|only\s*music|music\s*only|\[?\s*instrumental\s*\]?|\(\s*instrumental\s*\)|♪+\s*instrumental\s*♪*|♫+\s*instrumental\s*♫*|\.{3,}|-+|—+)$/i;
function isInsOnly(lines){if(!lines||!lines.length)return false;const j=lines.map(l=>String(l.text||'').replace(/\[[^\]]*\]/g,'').replace(/\([^)]*\)/g,'').trim()).filter(Boolean).join(' ').replace(/\s+/g,' ').trim().toLowerCase();if(!j)return true;if(INS_RE.test(j))return true;if(j.length<=60){if(/instrumental/.test(j))return true;if(/not\s*found/.test(j))return true;if(/no\s*lyrics?/.test(j))return true;if(/^\W*$/.test(j))return true}return false}
function parseLyricsPayload(r){
  let lines=[],rawText='';
  const pt=l=>numOrNull(l.time??l.startTime??l.start??l.timestamp??l.at??l.t??l.offset),px=l=>String(l.text??l.line??l.content??l.lyric??l.l??l.value??'');
  function ingest(node,d){if(node==null||d>6)return;
    if(typeof node==='string'){if(!rawText)rawText=node;return}
    if(Array.isArray(node)){if(!lines.length)lines=node.map(l=>typeof l==='string'?{time:null,text:l}:{time:pt(l),text:px(l)});return}
    if(typeof node!=='object')return;
    if(node.text!=null&&typeof node.text==='object'){ingest(node.text,d+1);if(lines.length||rawText)return}
    if(typeof node.synced==='string'&&node.synced.trim()){if(!rawText)rawText=node.synced;return}
    if(Array.isArray(node.synced)&&node.synced.length){if(!lines.length)lines=node.synced.map(l=>({time:pt(l),text:px(l)}));return}
    if(Array.isArray(node.lines)&&node.lines.length){if(!lines.length)lines=node.lines.map(l=>({time:pt(l),text:px(l)}));return}
    if(typeof node.plain==='string'&&node.plain.trim()){if(!rawText)rawText=node.plain;return}
    if(typeof node.lyrics==='string'){if(!rawText)rawText=node.lyrics;return}
    if(typeof node.text==='string'){if(!rawText)rawText=node.text;return}
    if(typeof node.unsynced==='string'){if(!rawText)rawText=node.unsynced;return}
    if(Array.isArray(node.unsynced)&&node.unsynced.length){if(!lines.length)lines=node.unsynced.map(t=>({time:null,text:String(t)}));return}}
  const L=r?.lyrics||r?.result||r?.data||r;ingest(L,0);
  if(!lines.length&&!rawText&&typeof r==='string')rawText=r;
  if(!lines.length&&rawText){const re=/\[(\d{1,2}):(\d{1,2})(?:[.:](\d{1,3}))?\]/g;const pa=[];for(const ln of String(rawText).split('\n')){re.lastIndex=0;const ts=[];let m;while((m=re.exec(ln))!==null){const mi=parseInt(m[1],10),se=parseInt(m[2],10),fr=m[3]?parseInt(m[3].padEnd(3,'0').slice(0,3),10)/1000:0;ts.push(mi*60+se+fr)}const tx=ln.replace(re,'').trim();if(ts.length)for(const t of ts)pa.push({time:t,text:tx});else pa.push({time:null,text:tx})}lines=pa}
  if(isInsOnly(lines))return{lines:[],synced:false};
  const sy=lines.some(l=>l.time!=null);return{lines,synced:sy}
}
function lyricsFromItem(it){if(!it)return null;const lyr=it.lyrics||it.attachments?.lyrics;if(!lyr)return null;try{const p=parseLyricsPayload(lyr);if(p&&p.lines&&p.lines.length)return p}catch{}return null}
async function loadLyrics(tid,item){
  const body=$('#lyricsBody'),badge=$('#lyricsBadge');if(badge)badge.style.display='none';
  const ids=String(tid);
  let it=item||getCached('track',ids);
  if(isCached(ids)){const e=await cacheGet(ids).catch(()=>null);if(e&&e.meta&&e.meta.lyrics){it=it||{};it.lyrics=e.meta.lyrics}}
  const fi=lyricsFromItem(it);
  if(fi){syncedLyricsCache={trackId:ids,lines:fi.lines,synced:fi.synced,loading:false};if(body&&String(tid)===String(lyricsTrackId)){if(badge)badge.style.display=fi.synced?'':'none';renderLyricsInto(body,fi.lines,fi.synced)}return}
  syncedLyricsCache={trackId:ids,lines:[],synced:false,loading:false};
  if(body&&String(tid)===String(lyricsTrackId))renderLyricsInto(body,[],false)
}
function getEffScroller(c){if(c.scrollHeight>c.clientHeight+2)return c;let p=c.parentElement;while(p&&p!==document.body&&p!==document.documentElement){const s=getComputedStyle(p);if(/(auto|scroll)/.test(s.overflowY)&&p.scrollHeight>p.clientHeight+2)return p;p=p.parentElement}return c}
function scrollLyricIntoView(c,el){const sc=getEffScroller(c),cr=sc.getBoundingClientRect(),er=el.getBoundingClientRect(),dl=(er.top-cr.top)-(cr.height/2-er.height/2),tg=Math.max(0,sc.scrollTop+dl);if(Math.abs(tg-sc.scrollTop)>1)sc.scrollTop=tg}
function renderLyricsInto(c,lines,synced){
  if(!c)return;c._lyricsData=null;
  if(!lines||!lines.length){c.classList.remove('synced');c.innerHTML=`<div class="text-secondary" style="font-size:.85rem;padding:8px 0">No lyrics available for this track.</div>`;return}
  if(synced){
    c.classList.add('synced');
    const fr=document.createDocumentFragment(),els=[],elByIdx=new Map(),idxMap=[];
    lines.forEach((l,i)=>{
      const el=document.createElement('div');
      if(l.time==null){el.className='lyric-line blank';el.innerHTML='&nbsp;'}
      else{el.className='lyric-line';el.textContent=l.text||'\u00A0';el.dataset.time=l.time;el.addEventListener('click',()=>{const t=parseFloat(el.dataset.time);if(isFinite(t))audio.currentTime=t});idxMap.push(i);elByIdx.set(i,el);els.push(el)}
      fr.appendChild(el)
    });
    c.replaceChildren(fr);c._lyricsData={lines,els,idxMap,elByLineIdx:elByIdx,activeIdx:-1,userScrollUntil:0};
    if(!c._lyricsScrollBound){const mk=()=>{const d=c._lyricsData;if(d)d.userScrollUntil=performance.now()+4000};c.addEventListener('wheel',mk,{passive:true});c.addEventListener('touchmove',mk,{passive:true});c.addEventListener('mousedown',mk,{passive:true});c._lyricsScrollBound=true}
    updateSyncedLyricsFor(c,true)
  } else {c.classList.remove('synced');c.textContent=lines.map(l=>l.text).join('\n')}
}
function updateSyncedLyricsFor(c,force){
  const d=c._lyricsData;if(!d||!d.lines.length)return;const t=audio.currentTime,lines=d.lines;let ai=-1;
  for(let i=0;i<lines.length;i++){const lt=lines[i].time;if(lt==null)continue;if(lt<=t+.08)ai=i;else break}
  if(ai===d.activeIdx&&!force)return;const pi=d.activeIdx;d.activeIdx=ai;
  if(pi<ai){const st=Math.max(0,pi);for(let i=st;i<ai;i++){const el=d.elByLineIdx.get(i);if(el&&!el.classList.contains('passed')){el.classList.add('passed');el.classList.remove('active')}}}
  else if(pi>ai){for(let i=ai+1;i<=pi;i++){const el=d.elByLineIdx.get(i);if(el)el.classList.remove('passed')}if(pi>=0){const el=d.elByLineIdx.get(pi);if(el)el.classList.remove('active')}}
  if(ai>=0){const el=d.elByLineIdx.get(ai);if(el){if(!el.classList.contains('active')){el.classList.remove('passed');el.classList.add('active')}if(getSettings().autoScrollLyrics&&performance.now()>(d.userScrollUntil||0))scrollLyricIntoView(c,el)}}
}
function updateVisibleLyrics(){
  const pc=document.getElementById('lyricsBody');if(pc&&pc._lyricsData&&pc.classList.contains('synced'))updateSyncedLyricsFor(pc);
  const rpc=document.getElementById('rpLyricsBody');if(rpc&&rpc._lyricsData&&rpc.classList.contains('synced'))updateSyncedLyricsFor(rpc);
  if(fpTab==='lyrics'&&$('#full')?.classList.contains('show')){const fc=$('#fpTabBody')?.querySelector('.fp-lyrics-body');if(fc&&fc._lyricsData)updateSyncedLyricsFor(fc)}
}
function copyLyrics(){const t=$('#lyricsBody')?.innerText||'';if(!t)return;copyText(t)}
function copyText(t){const done=()=>toast('Copied');if(navigator.clipboard?.writeText)navigator.clipboard.writeText(t).then(done).catch(()=>toast('Copy failed','danger'));else{const ta=document.createElement('textarea');ta.value=t;document.body.appendChild(ta);ta.select();try{document.execCommand('copy');done()}catch{toast('Copy failed','danger')}ta.remove()}}

const Player={track:null,queue:[],index:-1,playing:false,shuffle:false,repeat:'off',seeking:false,source:'MusicMan',sleepTimer:null,sleepEnd:0,abRepeat:{a:null,b:null}};
const FP_MAX_ITEMS=150;
let fpTab=(()=>{try{return ls.get(KEY.fptab,'now')}catch{return'now'}})();
if(!['now','queue','lyrics'].includes(fpTab))fpTab='now';
function setFpTab(tab){fpTab=tab;try{ls.set(KEY.fptab,tab)}catch{}$$('#fpHeadTabs .fp-head-tab').forEach(b=>b.classList.toggle('active',b.dataset.fpTab===tab));renderFpTabBody()}

function renderPlayerNow(body){
  if(!body)return;
  const t=Player.track;
  if(!t){body.innerHTML=PLAYER_EMPTY('bi-music-note','Nothing playing');return}
  const art=getArtwork(t,600)||t.artworkUrl||'';
  const liked=isLiked(t.trackId);
  const cur=fmtTime(audio.currentTime||0);
  const dur=audio.duration&&isFinite(audio.duration)?fmtTime(audio.duration):(t.trackTimeMillis?fmtTime(t.trackTimeMillis/1000):'0:00');
  const pct=audio.duration&&isFinite(audio.duration)?(audio.currentTime/audio.duration)*100:0;
  const dl=DL.get(t.trackId);
  const action=dlActionFor(t);
  let dlI=`<i class="bi ${action.icon}"></i>`;
  if(isCached(t.trackId))dlI='<i class="bi bi-cloud-check-fill text-success"></i>';
  else if(dl&&['queued','crawling','saving'].includes(dl.status))dlI=`<span class="spinner-border spinner-border-sm" style="width:14px;height:14px;border-width:2px"></span>`;
  else if(dl&&dl.status==='ready')dlI='<i class="bi bi-cloud-download text-warning"></i>';
  const artistLine=t.artistId?`<a class="fp-artist mm-link" href="${SCOPE}artist/${esc(t.artistId)}" data-link>${esc(t.artistName||'')}</a>`:`<div class="fp-artist">${esc(t.artistName||'')}</div>`;
  const albumLine=t.collectionId?`<div class="fp-artist" style="margin-top:2px;font-size:.76rem;opacity:.85"><a class="mm-link" href="${SCOPE}album/${esc(t.collectionId)}" data-link>${esc(t.collectionName||'')}</a></div>`:'';
  body.innerHTML=`<div class="fp-body">
    ${art?`<img class="fp-art" src="${esc(art)}" decoding="async" alt="">`:`<div class="fp-art fp-art-ph"><i class="bi bi-music-note"></i></div>`}
    <div class="fp-meta"><div class="fp-title">${esc(t.trackName||'Track')}</div>${artistLine}${albumLine}</div>
    <div class="fp-seek">
      <input class="mm-range fp-seek-input" type="range" min="0" max="1000" value="${Math.round(pct*10)}" style="--p:${pct}%">
      <div class="fp-times"><span class="fp-cur">${cur}</span><span class="fp-dur">${dur}</span></div>
      <div style="display:flex;justify-content:center;margin-top:6px"><span class="ab-chip off" onclick="abToggle()"></span></div>
    </div>
    <div class="fp-controls">
      <button class="icon-btn ${Player.shuffle?'on':''}" onclick="toggleShuffle()"><i class="bi bi-shuffle"></i></button>
      <button class="icon-btn lg" onclick="prevTrack()"><i class="bi bi-skip-start-fill"></i></button>
      <button class="fp-play" onclick="togglePlay()"><i class="bi ${Player.playing?'bi-pause-fill':'bi-play-fill'} fp-play-icon"></i></button>
      <button class="icon-btn lg" onclick="nextTrack()"><i class="bi bi-skip-end-fill"></i></button>
      <button class="icon-btn ${Player.repeat!=='off'?'on':''}" onclick="cycleRepeat()"><i class="bi ${Player.repeat==='one'?'bi-repeat-1':'bi-repeat'}"></i></button>
    </div>
    <div class="fp-volume"><i class="bi bi-volume-mute"></i><input class="mm-range vol fp-vol-input" type="range" min="0" max="100" value="${Math.round(audio.volume*100)}" style="--p:${Math.round(audio.volume*100)}%"><i class="bi bi-volume-up"></i></div>
    <div class="fp-actions">
      <button class="icon-btn ${liked?'liked':''} fp-like-btn" onclick="toggleLikeCurrent()"><i class="bi ${liked?'bi-heart-fill':'bi-heart'}"></i></button>
      <button class="icon-btn fp-save-btn" onclick="saveCurrentTrack()">${dlI}</button>
      <button class="icon-btn" onclick="openPlaylistPicker('${esc(t.trackId)}')"><i class="bi bi-plus-lg"></i></button>
      <button class="icon-btn" onclick="tgStart('track','${esc(t.trackId)}')" title="Open in Telegram bot"><i class="bi bi-telegram"></i></button>
      <button class="icon-btn" onclick="openSleepTimer()"><i class="bi bi-moon"></i></button>
      <button class="icon-btn" onclick="abToggle()"><i class="bi bi-repeat"></i></button>
    </div>
  </div>`;
  wirePlayerNow(body);
  updateAbChip(body);
}
function wirePlayerNow(body){
  const seek=body.querySelector('.fp-seek-input');
  if(seek){
    seek.addEventListener('pointerdown',()=>{Player.seeking=true});
    seek.addEventListener('pointerup',()=>{Player.seeking=false});
    seek.addEventListener('input',e=>{const p=Number(e.target.value)/10;e.target.style.setProperty('--p',p+'%');if(audio.duration&&isFinite(audio.duration)){const c=body.querySelector('.fp-cur');if(c)c.textContent=fmtTime((p/100)*audio.duration)}});
    seek.addEventListener('change',e=>{if(audio.duration&&isFinite(audio.duration))audio.currentTime=(Number(e.target.value)/1000)*audio.duration;Player.seeking=false})
  }
  const vol=body.querySelector('.fp-vol-input');
  if(vol)vol.addEventListener('input',e=>{const v=Number(e.target.value)/100;audio.volume=v;ls.set(KEY.vol,v);e.target.style.setProperty('--p',e.target.value+'%')})
}
function queueRow(tr,i,opts={}){
  const act=i===Player.index,art=getArtwork(tr,100)||tr.artworkUrl||'';
  const isPlaying=act&&Player.playing;
  const al=artistLink(tr,true);
  const drag=opts.drag?`<div class="q-drag" onclick="event.stopPropagation()"><button ${i===0?'disabled':''} onclick="moveQueueItem(${i},-1)"><i class="bi bi-chevron-up"></i></button><button ${i===Player.queue.length-1?'disabled':''} onclick="moveQueueItem(${i},1)"><i class="bi bi-chevron-down"></i></button></div>`:'';
  const dragging=opts.drag?` draggable="true" data-qi="${i}"`:'';
  const rem=opts.drag?`<button class="icon-btn sm" onclick="event.stopPropagation();removeQueue(${i})"><i class="bi bi-x-lg"></i></button>`:'';
  const ind=isPlaying?`<span class="q-bars"><span></span><span></span><span></span><span></span></span>`:(act?`<i class="bi bi-play-fill text-primary"></i>`:'');
  return`<div class="q-row ${act?'active':''}"${dragging} onclick="jumpQueue(${i})">${drag}${art?`<img src="${esc(art)}" class="row-art" loading="lazy" decoding="async" alt="">`:`<div class="row-art row-art-ph"><i class="bi bi-music-note"></i></div>`}<div class="flex-grow-1 min-w-0"><div class="row-title text-truncate">${esc(tr.trackName||'Track')}</div><div class="row-sub text-truncate">${al}</div></div>${ind}${rem}</div>`
}
function attachQueueDnD(root){if(!root)return;let dragIdx=null;root.querySelectorAll('.q-row[draggable="true"]').forEach(el=>{
  el.addEventListener('dragstart',e=>{dragIdx=Number(el.dataset.qi);el.classList.add('dragging');try{e.dataTransfer.effectAllowed='move';e.dataTransfer.setData('text/plain',String(dragIdx))}catch{}});
  el.addEventListener('dragend',()=>{el.classList.remove('dragging');root.querySelectorAll('.q-row').forEach(x=>x.classList.remove('drag-over'))});
  el.addEventListener('dragover',e=>{e.preventDefault();try{e.dataTransfer.dropEffect='move'}catch{};root.querySelectorAll('.q-row').forEach(x=>x.classList.remove('drag-over'));el.classList.add('drag-over')});
  el.addEventListener('drop',e=>{e.preventDefault();const from=dragIdx;const to=Number(el.dataset.qi);if(from==null||isNaN(to)||from===to)return;const cur=Player.queue[Player.index];const[m]=Player.queue.splice(from,1);Player.queue.splice(to,0,m);Player.index=Player.queue.indexOf(cur);renderFpTabBody();updateRightPanel()});
})}
function renderFpTabBody(){
  const body=$('#fpTabBody');if(!body)return;
  if(IS_DESKTOP())return;
  const fp=$('#full');if(!fp||!fp.classList.contains('show'))return;
  if(fpTab==='now')renderPlayerNow(body);
  else if(fpTab==='queue'){const q=Player.queue,items=q.length>FP_MAX_ITEMS?q.slice(0,FP_MAX_ITEMS):q;
    if(!q.length)body.innerHTML=PLAYER_EMPTY('bi-music-note-list','Queue is empty');
    else{
      let h=`<div class="fp-list-head"><div class="fp-list-title">Now playing · ${q.length}</div><div class="fp-list-actions"><button class="icon-btn sm" onclick="saveQueueAsPlaylist()" title="Save as playlist"><i class="bi bi-save"></i></button><button class="icon-btn sm" onclick="shuffleQueue()"><i class="bi bi-shuffle"></i></button><button class="icon-btn sm" onclick="clearQueue()"><i class="bi bi-trash3"></i></button></div></div>`;
      h+=items.map((tr,i)=>queueRow(tr,i,{drag:true})).join('');
      if(q.length>FP_MAX_ITEMS)h+=`<div class="text-center py-3 text-secondary" style="font-size:.72rem">+${q.length-FP_MAX_ITEMS} more</div>`;
      const rec=getRecentlyPlayed().filter(r=>!q.some(t=>String(t.trackId)===String(r.trackId))).slice(0,8);
      if(rec.length){h+=`<div class="fp-list-head" style="margin-top:6px"><div class="fp-list-title">Recently played</div></div>`;h+=rec.map(r=>`<div class="q-row" onclick="playById('${esc(r.trackId)}')">${r.artworkUrl?`<img src="${esc(r.artworkUrl)}" class="row-art" loading="lazy" decoding="async" alt="">`:`<div class="row-art row-art-ph"><i class="bi bi-music-note"></i></div>`}<div class="flex-grow-1 min-w-0"><div class="row-title text-truncate">${esc(r.trackName||'Track')}</div><div class="row-sub text-truncate">${artistLink(r,true)} · ${fmtAgo(r.at)}</div></div></div>`).join('')}
      body.innerHTML=h;attachQueueDnD(body)
    }
  }
  else{
    const t=Player.track;
    if(!t){body.innerHTML=PLAYER_EMPTY('bi-music-note-list','Nothing playing')}
    else{
      const id=String(t.trackId);
      if(syncedLyricsCache.trackId!==id||syncedLyricsCache.loading){body.innerHTML=`<div class="fp-lyrics-empty"><span class="spinner-border spinner-border-sm me-2"></span> Loading lyrics…</div>`;ensureFpLyrics()}
      else if(!syncedLyricsCache.lines||!syncedLyricsCache.lines.length){body.innerHTML=PLAYER_EMPTY('bi-music-note-list','No lyrics available for this track.')}
      else{body.innerHTML=`<div class="fp-lyrics-body" style="font-size:calc(.86rem * var(--lyric-size,1))"></div>`;renderLyricsInto(body.querySelector('.fp-lyrics-body'),syncedLyricsCache.lines,syncedLyricsCache.synced)}
    }
  }
  const b=$('#fpQueueBadge');if(b){b.textContent=Player.queue.length;b.classList.toggle('on',Player.queue.length>0)}
}

let rpMode='now';
function renderRpNow(){
  const body=document.getElementById('rpBody');if(!body)return;
  const t=Player.track;
  if(!t){body.innerHTML=PLAYER_EMPTY('bi-music-note','Nothing playing');return}
  const art=getArtwork(t,600)||t.artworkUrl||'';
  const id=String(t.trackId);
  const al=t.artistId?`<a class="mm-link" href="${SCOPE}artist/${esc(t.artistId)}" data-link>${esc(t.artistName||'')}</a>`:`<div class="rp-now-artist">${esc(t.artistName||'')}</div>`;
  const alb=t.collectionId?`<div style="margin-top:6px;font-size:.76rem;color:var(--bs-secondary-color)"><a class="mm-link" href="${SCOPE}album/${esc(t.collectionId)}" data-link>${esc(t.collectionName||'')}</a></div>`:'';
  // Smart download/crawl button (replaces speed + sleep in right panel)
  const cached=isCached(id);
  const dl=DL.get(id);
  const action=dlActionFor(t);
  let dlBtn;
  if(cached){
    dlBtn=`<button onclick="exportCached('${esc(id)}')"><i class="bi bi-file-earmark-arrow-down" style="color:var(--bs-success)"></i>Saved</button>`;
  } else if(dl&&['queued','crawling','saving'].includes(dl.status)){
    const lb=dl.status==='saving'?'Downloading':'Crawling';
    dlBtn=`<button onclick="go('/library/downloads')"><span class="spinner-border spinner-border-sm" style="width:14px;height:14px;border-width:2px;color:var(--bs-info)"></span>${lb}</button>`;
  } else if(dl&&dl.status==='ready'){
    dlBtn=`<button onclick="DL.retry('${esc(id)}')"><i class="bi bi-cloud-download" style="color:var(--bs-warning)"></i>Save</button>`;
  } else if(dl&&dl.status==='failed'){
    dlBtn=`<button onclick="DL.retry('${esc(id)}')"><i class="bi bi-arrow-repeat" style="color:var(--bs-danger)"></i>Retry</button>`;
  } else {
    const cls=action.isDirect?'':'is-preview';
    dlBtn=`<button onclick="saveCurrentTrack()" class="${cls}"><i class="bi ${action.icon}"></i>${action.label}</button>`;
  }
  body.innerHTML=`<div class="rp-now">${art?`<img class="rp-now-art" src="${esc(art)}" decoding="async" alt="">`:'<div class="rp-now-art" style="display:flex;align-items:center;justify-content:center;color:var(--bs-secondary-color);font-size:3rem"><i class="bi bi-music-note"></i></div>'}<div class="rp-now-title">${esc(t.trackName||'Track')}</div>${al}${alb}
<div class="rp-quick"><button onclick="togglePlay()"><i class="bi ${Player.playing?'bi-pause-fill':'bi-play-fill'}"></i>${Player.playing?'Pause':'Play'}</button><button onclick="toggleLikeCurrent()"><i class="bi ${isLiked(t.trackId)?'bi-heart-fill':'bi-heart'}"></i>Like</button>${dlBtn}<button onclick="tgStart('track','${esc(id)}')"><i class="bi bi-telegram" style="color:#29abe2"></i>Telegram</button></div></div>
<div class="rp-section-title">Recently played</div><div class="recent-rail">${getRecentlyPlayed().slice(0,8).map(r=>{const a=r.artworkUrl||'';const ral=artistLink(r,true);return`<div class="q-row" onclick="playById('${esc(r.trackId)}')">${a?`<img src="${esc(a)}" class="row-art" loading="lazy" decoding="async" alt="">`:`<div class="row-art row-art-ph"><i class="bi bi-music-note"></i></div>`}<div class="flex-grow-1 min-w-0"><div class="row-title text-truncate">${esc(r.trackName||'Track')}</div><div class="row-sub text-truncate">${ral}</div></div></div>`}).join('')}</div>`
}
function renderRpQueue(){
  const body=document.getElementById('rpBody');if(!body)return;
  const q=Player.queue;
  if(!q.length){body.innerHTML=PLAYER_EMPTY('bi-music-note-list','Queue is empty');return}
  const items=q.length>FP_MAX_ITEMS?q.slice(0,FP_MAX_ITEMS):q;
  let h=`<div class="fp-list-head"><div class="fp-list-title">Queue · ${q.length}</div><div class="fp-list-actions"><button class="icon-btn sm" onclick="saveQueueAsPlaylist()" title="Save as playlist"><i class="bi bi-save"></i></button><button class="icon-btn sm" onclick="shuffleQueue()" title="Shuffle"><i class="bi bi-shuffle"></i></button><button class="icon-btn sm" onclick="clearQueue()" title="Clear"><i class="bi bi-trash3"></i></button></div></div>`;
  h+=items.map((tr,i)=>queueRow(tr,i,{drag:true})).join('');
  if(q.length>FP_MAX_ITEMS)h+=`<div class="text-center py-3 text-secondary" style="font-size:.72rem">+${q.length-FP_MAX_ITEMS} more</div>`;
  body.innerHTML=h;attachQueueDnD(body)
}
function renderRpLyrics(){
  const body=document.getElementById('rpBody');if(!body)return;
  const t=Player.track;
  if(!t){body.innerHTML=PLAYER_EMPTY('bi-music-note-list','Nothing playing');return}
  const id=String(t.trackId);
  if(syncedLyricsCache.trackId!==id||syncedLyricsCache.loading){body.innerHTML=`<div class="fp-lyrics-empty"><span class="spinner-border spinner-border-sm me-2"></span>Loading lyrics…</div>`;ensureFpLyrics();return}
  if(!syncedLyricsCache.lines?.length){body.innerHTML=PLAYER_EMPTY('bi-music-note-list','No lyrics available.');return}
  body.innerHTML='<div class="fp-lyrics-body" id="rpLyricsBody" style="font-size:calc(.86rem * var(--lyric-size,1))"></div>';
  renderLyricsInto(document.getElementById('rpLyricsBody'),syncedLyricsCache.lines,syncedLyricsCache.synced)
}
function renderRpInfo(){
  const body=document.getElementById('rpBody');if(!body)return;
  const t=Player.track;
  if(!t){body.innerHTML=PLAYER_EMPTY('bi-info-circle','Nothing playing');return}
  const dur=t.trackTimeMillis?fmtTime(t.trackTimeMillis/1000):(audio.duration&&isFinite(audio.duration)?fmtTime(audio.duration):'—');
  const art=getArtwork(t,600)||'';
  const rows=[['Title',esc(t.trackName||'—')],['Artist',t.artistId?`<a class="mm-link" href="${SCOPE}artist/${esc(t.artistId)}" data-link>${esc(t.artistName||'—')}</a>`:esc(t.artistName||'—')],['Album',t.collectionId?`<a class="mm-link" href="${SCOPE}album/${esc(t.collectionId)}" data-link>${esc(t.collectionName||'—')}</a>`:esc(t.collectionName||'—')],['Duration',esc(dur)],['Genre',esc(t.primaryGenreName||'—')],['Released',t.releaseDate?esc(new Date(t.releaseDate).toLocaleDateString()):'—'],['Track #',t.trackNumber?String(t.trackNumber):'—'],['Track ID',esc(String(t.trackId||''))]];
  body.innerHTML=`<div style="padding:8px 10px 0">${art?`<img src="${esc(art)}" style="width:100%;border-radius:var(--r-lg);box-shadow:var(--el-3);margin-bottom:14px;aspect-ratio:1;object-fit:cover" loading="lazy" decoding="async" alt="">`:''}${rows.map(([k,v])=>`<div style="display:flex;justify-content:space-between;gap:12px;padding:9px 4px;border-bottom:1px solid var(--mm-divider);font-size:.8rem"><span style="color:var(--bs-secondary-color);font-weight:700">${k}</span><span style="text-align:right;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:60%">${v}</span></div>`).join('')}<div class="d-flex gap-2 mt-3"><button class="pill-btn primary flex-fill justify-content-center" onclick="copyText(location.origin+'${BASE||''}/track/${esc(String(t.trackId))}')"><i class="bi bi-link-45deg"></i> Copy link</button><a class="pill-btn telegram flex-fill justify-content-center" href="${tgLink('track',t.trackId)}" target="_blank" rel="noopener"><i class="bi bi-telegram"></i> </a></div></div>`
}
function updateRightPanel(){
  const body=document.getElementById('rpBody');
  if(!body)return;
  if(rpMode==='now')renderRpNow();
  else if(rpMode==='lyrics')renderRpLyrics();
  else if(rpMode==='info')renderRpInfo();
  else renderRpQueue();
  const b=$('#rpQueueBadge');if(b){b.textContent=Player.queue.length;b.classList.toggle('on',Player.queue.length>0)}
}

function syncPlayerUI(){
  const t=Player.track,mini=$('#mini');
  if(!mini)return;
  if(!t){mini.classList.add('d-none');document.title='MusicMan';renderFpTabBody();syncPlayerBar();updateRightPanel();return}
  mini.classList.remove('d-none');
  const art=getArtwork(t,300)||t.artworkUrl||'',artS=getArtwork(t,100)||t.artworkUrl||art,mi=$('#miniArt');
  if(artS){mi.src=artS;mi.style.visibility='visible'}else{mi.removeAttribute('src');mi.style.visibility='hidden'}
  const appBg=$('#appBg');if(appBg&&art&&getSettings().appBg){appBg.style.backgroundImage=`url("${art}")`}
  $('#miniTitle').textContent=t.trackName||'Track';$('#miniArtist').textContent=t.artistName||'';
  const bg=$('#fpBg');if(bg)bg.style.backgroundImage=art?`url("${art}")`:'';
  refreshLikes();renderFpTabBody();syncPlayIcons();syncSaveButton();updateMediaSession();document.title=`${t.trackName||'MusicMan'} · MusicMan`;syncPlayerBar();updateRightPanel();
  if(getSettings().accent==='auto'&&art){computeArtAccent(art).then(c=>{if(c&&Player.track&&String(Player.track.trackId)===String(t.trackId)&&getSettings().accent==='auto')applyAccent(null,c)})}
}
/* FIXED: preserve preview play-circle icon state after playing another track */
function syncPlayIcons(){
  const cls=Player.playing?'bi-pause-fill':'bi-play-fill';
  const m=$('#miniPlayIcon');if(m)m.className=`bi ${cls}`;
  $$('.fp-play-icon').forEach(f=>f.className=`bi ${cls} fp-play-icon`);
  const cur=Player.track?String(Player.track.trackId):null;
  $$('.row-item .row-play[data-play-id]').forEach(b=>{
    const id=String(b.dataset.playId||''),it=b.closest('.row-item'),isCur=cur&&id===cur,ic=b.querySelector('i');
    const isPreview=b.dataset.preview==='1';
    if(ic)ic.className=`bi ${isCur&&Player.playing?'bi-pause-fill':(isPreview?'bi-play-circle':'bi-play-fill')}`;
    b.classList.toggle('playing',!!isCur&&Player.playing);
    b.classList.toggle('is-preview',isPreview);
    if(it)it.classList.toggle('playing',!!isCur)
  });
  const eq=$('#miniEq');if(eq)eq.classList.toggle('on',Player.playing);
  if(typeof syncPlayerBar==='function')syncPlayerBar()
}
function syncSaveButton(){
  const id=Player.track?.trackId;
  $$('.fp-save-btn').forEach(b=>{
    if(!id){b.innerHTML='<i class="bi bi-download"></i>';return}
    if(isCached(id)){b.innerHTML='<i class="bi bi-cloud-check-fill text-success"></i>';return}
    const dl=DL.get(id);
    if(dl&&['queued','crawling','saving'].includes(dl.status)){b.innerHTML=`<span class="spinner-border spinner-border-sm" style="width:14px;height:14px;border-width:2px"></span>`;return}
    if(dl&&dl.status==='ready'){b.innerHTML='<i class="bi bi-cloud-download text-warning"></i>';return}
    const a=dlActionFor(Player.track);b.innerHTML=`<i class="bi ${a.icon}"></i>`
  })
}
function setSeekUI(pct){
  const b=$('#miniBar');if(b)b.style.width=pct+'%';
  const s=$('#pbSeek');if(s&&!Player.seeking){s.value=Math.round(pct*10);s.style.setProperty('--p',pct+'%')}
  const body=$('#fpTabBody');
  if(body){const fs=body.querySelector('.fp-seek-input');if(fs&&!Player.seeking){fs.value=Math.round(pct*10);fs.style.setProperty('--p',pct+'%')}}
}
audio.addEventListener('play',()=>{Player.playing=true;syncPlayIcons();syncSaveButton();if(fpTab==='queue')renderFpTabBody();updateRightPanel();statsStart()});
audio.addEventListener('pause',()=>{Player.playing=false;syncPlayIcons();if(fpTab==='queue')renderFpTabBody();updateRightPanel();statsStop()});
audio.addEventListener('ended',()=>onTrackEnded());
audio.addEventListener('loadedmetadata',()=>{if(isFinite(audio.duration)){if(typeof syncPlayerBar==='function')syncPlayerBar()}});
let _trp=false,_llt=0,_lpt=0,_stt=0;
audio.addEventListener('timeupdate',()=>{if(_trp)return;_trp=true;requestAnimationFrame(()=>{_trp=false;const d=audio.duration;if(!d||!isFinite(d))return;const n=performance.now();
  if(n-_lpt>=PTICK){_lpt=n;const pct=(audio.currentTime/d)*100;setSeekUI(pct);const c=$('#fpCur');if(c)c.textContent=fmtTime(audio.currentTime);updateDesktopSeek();updatePlayerTimesUI()}
  if(n-_llt>=LTICK){_llt=n;updateVisibleLyrics()}
  if(n-_stt>=5000){_stt=n;statsTick()}
})});
function updatePlayerTimesUI(){
  const t=fmtTime(audio.currentTime||0),d=audio.duration&&isFinite(audio.duration)?fmtTime(audio.duration):null;
  ['#fpTabBody'].forEach(sel=>{const root=document.querySelector(sel);if(!root)return;const c=root.querySelector('.fp-cur');if(c)c.textContent=t;const du=root.querySelector('.fp-dur');if(du&&d)du.textContent=d});
}
/* FIXED: playback error toast with Telegram action */
audio.addEventListener('error',()=>{
  const src=audio.src;if(!src)return;
  if(src.startsWith('blob:')||src.startsWith('data:'))return;
  if(audio.error&&audio.error.code===1)return;
  const t=Player.track;
  if(t&&t.trackId){
    toastAction('Playback error',[
      {html:'<i class="bi bi-telegram"></i> Telegram',class:'telegram',fn:()=>tgStart('track',t.trackId)},
      {html:'<i class="bi bi-arrow-repeat"></i> Retry',class:'info',fn:()=>{try{audio.currentTime=0;audio.play().catch(()=>{})}catch{}}}
    ],'danger',7000);
  } else {
    toast('Playback error','danger');
  }
});
function updateMediaSession(){if(!('mediaSession'in navigator))return;const t=Player.track;if(!t){try{navigator.mediaSession.metadata=null}catch{}return}
  const a=getArtwork(t,512)||t.artworkUrl||'';
  try{navigator.mediaSession.metadata=new MediaMetadata({title:t.trackName||'Track',artist:t.artistName||'',album:t.collectionName||'MusicMan',artwork:a?[{src:a,sizes:'512x512',type:'image/jpeg'}]:[]})}catch{}
  try{navigator.mediaSession.playbackState=Player.playing?'playing':'paused';
    const H=(k,f)=>{try{navigator.mediaSession.setActionHandler(k,f)}catch{}};
    H('play',()=>audio.play());H('pause',()=>audio.pause());H('previoustrack',()=>prevTrack());H('nexttrack',()=>nextTrack());
    H('seekbackward',d=>audio.currentTime=Math.max(0,audio.currentTime-(d.seekOffset||10)));
    H('seekforward',d=>audio.currentTime=Math.min(audio.duration||0,audio.currentTime+(d.seekOffset||10)));
    H('seekto',d=>{if(d.seekTime!=null)audio.currentTime=d.seekTime});H('stop',()=>audio.pause());
    if(audio.duration&&isFinite(audio.duration))navigator.mediaSession.setPositionState({duration:audio.duration,position:Math.min(audio.currentTime,audio.duration),playbackRate:1})}catch{}
}
['play','pause','loadedmetadata','ended'].forEach(e=>audio.addEventListener(e,updateMediaSession));
let _mtk=0;
audio.addEventListener('timeupdate',()=>{const n=performance.now();if(n-_mtk<1000)return;_mtk=n;if('mediaSession'in navigator&&audio.duration&&isFinite(audio.duration)){try{navigator.mediaSession.setPositionState({duration:audio.duration,position:Math.min(audio.currentTime,audio.duration),playbackRate:1})}catch{}}});
function persistQueue(){try{ls.set(KEY.queue,{q:Player.queue.slice(0,150),i:Player.index,t:Player.track,sh:Player.shuffle,rp:Player.repeat})}catch{}}

async function loadTrackSourceForPlayback(t){
  if(!t?.trackId)return false;
  const id=String(t.trackId);
  try{
    if(isCached(id)){const e=await cacheGet(id);if(e?.blob){const u=URL.createObjectURL(e.blob);if(audio.dataset.blobUrl){try{URL.revokeObjectURL(audio.dataset.blobUrl)}catch{}}audio.dataset.blobUrl=u;audio.src=u;try{audio.load()}catch{}return true}}
    let url=getPlayable(t);
    if(!url){try{const r=await apiLookup(id,'song');const f=r.find(x=>itemType(x)==='track')||r[0];if(f){cacheItems([f]);Player.track=f;url=getPlayable(f)}}catch{}}
    if(!url)return false;
    if(audio.dataset.blobUrl){try{URL.revokeObjectURL(audio.dataset.blobUrl)}catch{}delete audio.dataset.blobUrl}
    audio.src=proxyUrl(url);try{audio.load()}catch{};return true
  }catch{return false}
}
async function restoreQueue(){
  const s=ls.get(KEY.queue,null);if(!s||!s.q||!s.q.length)return;
  Player.queue=s.q;Player.index=Math.max(0,Math.min(s.i||0,s.q.length-1));Player.shuffle=!!s.sh;Player.repeat=s.rp||'off';
  const t=s.t||s.q[Player.index];if(!t?.trackId)return;
  Player.track=t;await loadTrackSourceForPlayback(t);syncPlayerUI()
}
['play','pause','ended','loadedmetadata'].forEach(e=>audio.addEventListener(e,persistQueue));
let _ptk=0;
audio.addEventListener('timeupdate',()=>{const n=performance.now();if(n-_ptk<4000)return;_ptk=n;persistQueue()});
window.addEventListener('beforeunload',()=>{persistQueue();statsStop()});
window.addEventListener('pagehide',()=>{persistQueue();statsStop()});
const _pn=new Audio();_pn.preload='auto';_pn.muted=true;
let _pk='';
function preloadNext(){if(IS_LOW_END)return;const q=Player.queue;if(q.length<2)return;let ni=Player.index+1;if(Player.shuffle){ni=Math.floor(Math.random()*q.length);if(ni===Player.index)ni=(ni+1)%q.length}
  if(ni>=q.length){if(Player.repeat!=='all')return;ni=0}
  const n=q[ni];if(!n?.trackId)return;const k=String(n.trackId);if(_pk===k)return;_pk=k;if(isCached(k))return;const u=getPlayable(n);if(!u)return;try{_pn.src=proxyUrl(u)}catch{}}
audio.addEventListener('loadedmetadata',preloadNext);audio.addEventListener('play',preloadNext);audio.addEventListener('ended',()=>{_pk=''});

function showUnavailableSheet(id,item){
  const name=item?.trackName||'Track';const artist=item?.artistName||'';
  const dl=DL.get(id);const busy=dl&&['queued','crawling','saving'].includes(dl.status);
  const art=getArtwork(item,100)||item?.artworkUrl||'';
  const rows=[];
  if(busy)rows.push(`<button class="sheet-item" onclick="closeAllSheets(); go('/library/downloads')"><i class="bi bi-clock-history text-info"></i><span>Track is still being prepared<br><span class="sub">View progress in Downloads</span></span></button>`);
  rows.push(`<a class="sheet-item telegram" href="${tgLink('track',id)}" target="_blank" rel="noopener"><i class="bi bi-telegram"></i><span>Get on Telegram bot<br><span class="sub">Recommended for large files & full audio</span></span></a>`);
  rows.push(`<button class="sheet-item" onclick="closeAllSheets(); startCrawl('${esc(id)}')"><i class="bi bi-cloud-arrow-down text-primary"></i><span>Crawl on server<br><span class="sub">Might fail for very large files</span></span></button>`);
  rows.push(`<button class="sheet-item" onclick="closeAllSheets(); go('/track/${esc(id)}')"><i class="bi bi-info-circle"></i><span>Open track details</span></button>`);
  $('#sheetTrackBody').innerHTML=`<div class="d-flex align-items-center gap-3 px-3 pb-2 pt-1">${art?`<img src="${esc(art)}" width="52" height="52" class="row-art" loading="lazy" decoding="async" alt="">`:`<div class="row-art row-art-ph"><i class="bi bi-music-note"></i></div>`}<div class="min-w-0"><div class="fw-bold text-truncate">Cannot play this track</div><div class="row-sub text-truncate">${esc(name)}${artist?' — '+esc(artist):''}</div></div></div><div class="px-3 pb-2"><div class="small text-secondary">This track's full audio isn't available right now. Use the Telegram bot to receive the file, or try crawling it on the server.</div></div><div class="px-2">${rows.join('')}</div>`;
  sheet('#sheetTrack').show()
}
let _playGen=0;
async function playItem(item,source='MusicMan'){
  if(!item)return;
  const myGen=++_playGen;
  Player.source=source;
  const id=String(item.trackId||'');
  if(!id)return;
  if(isCached(id))return playCachedById(id,item);
  let ri=item,ru=getPlayable(item);
  if(!ru){const c=getCached('track',id);if(c){if(isCached(id))return playCachedById(id,c);const u=getPlayable(c);if(u){ri=c;ru=u}}}
  if(!ru){try{const r=await apiLookup(id,'song');if(myGen!==_playGen)return;const f=r.find(x=>itemType(x)==='track')||r[0];if(f){cacheItems([f]);if(isCached(id))return playCachedById(id,f);const u=getPlayable(f);if(u){ri=f;ru=u}}}catch{}}
  if(myGen!==_playGen)return;
  if(!ru){const dl=DL.get(id);if(dl&&['queued','crawling','saving'].includes(dl.status))toast('Still preparing — try again in a moment','info');else showUnavailableSheet(id,ri||item);return}
  const po=!hasAudio(ri)&&hasPreview(ri);
  audio.pause();
  if(audio.dataset.blobUrl){try{URL.revokeObjectURL(audio.dataset.blobUrl)}catch{}delete audio.dataset.blobUrl}
  audio.src=proxyUrl(ru);audio.load();
  const pp=audio.play();
  if(pp&&typeof pp.catch==='function')pp.catch(e=>{if(!e||e.name==='AbortError')return;
    if(e.name==='NotAllowedError'){const once=()=>{document.removeEventListener('touchstart',once,true);document.removeEventListener('click',once,true);if(Player.track===ri)audio.play().catch(()=>{})};document.addEventListener('touchstart',once,{capture:true,once:true,passive:true});document.addEventListener('click',once,{capture:true,once:true});toast('Tap to continue playback','info')}
    else toast('Cannot play: '+(e.message||''),'danger')});
  Player.track=ri;pushRecentlyPlayed(ri);setQueueFromItem(ri,id);syncPlayerUI();
  if(getSettings().autoSavePlayed&&!isCached(id)&&!DL.get(id)&&hasAudio(ri)){setTimeout(()=>{try{DL.add(ri).catch(()=>{})}catch{}},800)}
  if(po)toast('Preview playing - press crawl to get full track','info')
}
function setQueueFromItem(item,id){if(!id)return;const ex=Player.queue.findIndex(t=>String(t.trackId)===id);if(ex>=0){Player.queue[ex]=item;Player.index=ex}else{Player.queue.push(item);Player.index=Player.queue.length-1}}
async function playTrackFromList(id){
  haptic(6);
  const cur=Player.track?String(Player.track.trackId):null;
  if(cur===String(id)){togglePlay();return}
  if(Array.isArray(_pageTracks)&&_pageTracks.length&&_pageTracks.some(t=>String(t.trackId)===String(id))){Player.queue=[..._pageTracks];const idx=Player.queue.findIndex(t=>String(t.trackId)===String(id));Player.index=idx>=0?idx:0;playItem(Player.queue[Player.index]);return}
  playById(id)
}
async function playById(id){haptic(6);const c=isCached(id);let it=getCached('track',id);
  if(!it&&c){const e=await cacheGet(id).catch(()=>null);if(e&&e.meta)it={wrapperType:'track',trackId:id,...e.meta}}
  if(!it){try{const r=await apiLookup(id,'song');it=r.find(x=>itemType(x)==='track')||r[0];if(it)cacheItems([it])}catch{}}
  if(!it&&c)it={wrapperType:'track',trackId:String(id),trackName:'Cached track',artistName:'',attachments:{}};
  if(!it){toast('Track not found','danger');return}
  playItem(it)
}
async function playCachedById(id,fb){
  const e=await cacheGet(id);if(!e?.blob){toast('Not available offline','danger');return}
  const m=e.meta||{},u=URL.createObjectURL(e.blob);
  audio.pause();if(audio.dataset.blobUrl){try{URL.revokeObjectURL(audio.dataset.blobUrl)}catch{}}audio.dataset.blobUrl=u;audio.src=u;audio.play().catch(()=>{});
  const f=(fb&&fb.trackId)?fb:{wrapperType:'track',trackId:String(id),trackName:m.trackName||m.name||'Cached track',artistName:m.artist||m.artistName||'',artistId:m.artistId||'',collectionName:m.collectionName||m.album||'',collectionId:m.collectionId||'',primaryGenreName:m.primaryGenreName||'',releaseDate:m.releaseDate||'',trackTimeMillis:m.trackTimeMillis||0,lyrics:m.lyrics||null,attachments:m.attachments||{artworkUrls:m.artwork?[{url:m.artwork}]:[]}};
  Player.track=f;pushRecentlyPlayed(f);setQueueFromItem(f,String(id));syncPlayerUI()
}
function togglePlay(){haptic(6);if(!audio.src){if(Player.queue.length)playItem(Player.queue[Math.max(0,Player.index)]||Player.queue[0]);return}if(audio.paused)audio.play().catch(()=>{});else audio.pause()}
function nextTrack(userInit=true){const q=Player.queue;if(!q.length)return;if(Player.repeat==='one'&&!userInit){audio.currentTime=0;audio.play().catch(()=>{});return}
  let nx;if(Player.shuffle&&q.length>1){do{nx=Math.floor(Math.random()*q.length)}while(nx===Player.index)}else{nx=Player.index+1;if(nx>=q.length){if(Player.repeat==='all')nx=0;else{audio.pause();audio.currentTime=0;syncPlayIcons();return}}}
  Player.index=nx;playItem(q[nx],Player.source)}
function prevTrack(){if(audio.currentTime>3){audio.currentTime=0;return}const q=Player.queue;if(!q.length)return;let pv=Player.index-1;if(pv<0)pv=Player.repeat==='all'?q.length-1:0;Player.index=pv;playItem(q[pv],Player.source)}
function onTrackEnded(){nextTrack(false)}
function toggleShuffle(){Player.shuffle=!Player.shuffle;toast(Player.shuffle?'Shuffle on':'Shuffle off');if(fpTab==='now')renderFpTabBody();updateRightPanel();syncPlayerBar()}
function cycleRepeat(){const o=['off','all','one'];Player.repeat=o[(o.indexOf(Player.repeat)+1)%3];toast(Player.repeat==='off'?'Repeat off':Player.repeat==='all'?'Repeat all':'Repeat one');if(fpTab==='now')renderFpTabBody();updateRightPanel();syncPlayerBar()}
function openFullPlayer(tab){if(IS_DESKTOP())return;if(!Player.track)return;const el=$('#full');el.classList.add('show');el.setAttribute('aria-hidden','false');if(tab)setFpTab(tab);else setFpTab(fpTab)}
function closeFullPlayer(){const el=$('#full');if(!el.classList.contains('show'))return;el.classList.remove('show');el.setAttribute('aria-hidden','true');el.style.transform='';el.style.opacity=''}

(function(){
  const fp=document.getElementById('full');if(!fp||fp.__ysw)return;fp.__ysw=1;
  let sy=0,dy=0,dg=false;
  const reset=()=>{fp.style.transition='';fp.style.transform='';fp.style.opacity='';dy=0};
  fp.addEventListener('touchstart',e=>{if(e.touches.length!==1)return;if(e.target.closest('input,textarea,select,button,a,.fp-head'))return;const body=fp.querySelector('.fp-tabbody');if(body&&body.scrollTop>0)return;dg=true;sy=e.touches[0].clientY;dy=0;fp.style.transition='none'},{passive:true});
  fp.addEventListener('touchmove',e=>{if(!dg)return;const delta=e.touches[0].clientY-sy;if(delta<=0){if(dy!==0){dy=0;fp.style.transform='';fp.style.opacity=''}return}dy=delta;fp.style.transform=`translateY(${dy}px)`;fp.style.opacity=String(Math.max(.35,1-dy/520))},{passive:true});
  const finish=(cancelled)=>{if(!dg)return;dg=false;const shouldClose=!cancelled&&dy>110;reset();if(shouldClose)closeFullPlayer()};
  fp.addEventListener('touchend',()=>finish(false),{passive:true});
  fp.addEventListener('touchcancel',()=>finish(true),{passive:true})
})();
function saveQueueAsPlaylist(){if(!Player.queue.length){toast('Queue is empty','warning');return}askText('Save queue as playlist','Queue · '+new Date().toLocaleDateString(),'Playlist name').then(n=>{if(!n)return;const pl=createPlaylist(n);if(!pl)return;const all=getPlaylists(),p=all.find(x=>x.id===pl.id);if(!p)return;const seen=new Set();for(const it of Player.queue){const id=String(it.trackId);if(!id||seen.has(id))continue;seen.add(id);p.tracks.push(makeLikeEntry(it))}savePlaylists(all);toast(`Saved ${p.tracks.length} tracks`)})}
function moveQueueItem(i,dir){const j=i+dir;if(j<0||j>=Player.queue.length)return;const q=Player.queue;[q[i],q[j]]=[q[j],q[i]];if(Player.index===i)Player.index=j;else if(Player.index===j)Player.index=i;renderFpTabBody();updateRightPanel()}
function shuffleQueue(){const q=Player.queue;if(q.length<2)return;const c=Player.index>=0?q[Player.index]:null;for(let i=q.length-1;i>0;i--){const j=Math.floor(Math.random()*(i+1));[q[i],q[j]]=[q[j],q[i]]}if(c)Player.index=q.indexOf(c);renderFpTabBody();updateRightPanel();toast('Queue shuffled')}
function jumpQueue(i){if(i<0||i>=Player.queue.length)return;Player.index=i;playItem(Player.queue[i],Player.source)}
function removeQueue(i){if(i<0||i>=Player.queue.length)return;Player.queue.splice(i,1);if(i<Player.index)Player.index--;else if(i===Player.index)Player.index=Math.min(Player.index,Player.queue.length-1);renderFpTabBody();updateRightPanel()}
function clearQueue(){Player.queue=Player.track?[Player.track]:[];Player.index=Player.track?0:-1;renderFpTabBody();updateRightPanel();toast('Queue cleared')}

let _lyricsInflight=new Set();
async function ensureFpLyrics(){
  const t=Player.track;if(!t)return;
  const id=String(t.trackId);
  if(syncedLyricsCache.trackId===id&&syncedLyricsCache.lines!==null)return;
  if(_lyricsInflight.has(id))return;
  _lyricsInflight.add(id);
  try{
    const ft=lyricsFromItem(t);
    if(ft){syncedLyricsCache={trackId:id,lines:ft.lines,synced:ft.synced,loading:false};refreshPlayerLyricsUI();return}
    if(isCached(id)){const e=await cacheGet(id).catch(()=>null);if(e&&e.meta&&e.meta.lyrics){const fc=parseLyricsPayload(e.meta.lyrics);if(fc&&fc.lines.length){syncedLyricsCache={trackId:id,lines:fc.lines,synced:fc.synced,loading:false};refreshPlayerLyricsUI();return}}}
    const ci=getCached('track',id);
    if(ci&&ci!==t){const fc=lyricsFromItem(ci);if(fc){syncedLyricsCache={trackId:id,lines:fc.lines,synced:fc.synced,loading:false};refreshPlayerLyricsUI();return}}
    try{const r=await apiLookup(id,'song');const f=r.find(x=>itemType(x)==='track')||r[0];if(f){cacheItems([f]);if(!Player.track||String(Player.track.trackId)!==id)return;Player.track=f;const ff=lyricsFromItem(f);if(ff){syncedLyricsCache={trackId:id,lines:ff.lines,synced:ff.synced,loading:false};refreshPlayerLyricsUI();return}}}catch{}
    syncedLyricsCache={trackId:id,lines:[],synced:false,loading:false};refreshPlayerLyricsUI()
  } finally{_lyricsInflight.delete(id)}
}
function refreshPlayerLyricsUI(){if(fpTab==='lyrics'&&!IS_DESKTOP())renderFpTabBody();if(IS_DESKTOP()&&rpMode==='lyrics')updateRightPanel()}
async function saveCurrentTrack(){const t=Player.track;if(!t?.trackId){toast('Nothing playing','warning');return}const id=String(t.trackId);if(isCached(id)){await exportCached(id);return}
  const dl=DL.get(id);if(dl&&['queued','crawling','saving'].includes(dl.status)){toast('Already in progress','warning');return}
  if(dl&&dl.status==='ready'){DL.retry(id);return}
  const f=getCached('track',id)||t;await startCrawlInternal(id,null,f);syncSaveButton()}
function toggleLikeCurrent(){if(Player.track?.trackId)toggleLike(Player.track)}
async function playIds(csv){const ids=String(csv||'').split(',').map(s=>s.trim()).filter(Boolean);if(!ids.length){toast('Nothing to play');return}toast('Loading…');const items=[];
  for(const id of ids){let it=getCached('track',id);if(!it){try{const r=await apiLookup(id,'song');it=r.find(x=>itemType(x)==='track')||r[0];if(it)cacheItems([it])}catch{}}
    if(!it&&isCached(id))it={wrapperType:'track',trackId:String(id),trackName:'Cached track',artistName:'',attachments:{}};if(it)items.push(it)}
  if(!items.length){toast('Could not load tracks','danger');return}
  Player.queue=items;Player.index=0;renderFpTabBody();updateRightPanel();playItem(items[0],Player.source)}
function addAllToQueue(items){const l=(items||[]).filter(it=>it&&it.trackId);if(!l.length){toast('Nothing to add','warning');return}let ad=0;const ex=new Set(Player.queue.map(q=>String(q.trackId)));for(const it of l){const id=String(it.trackId);if(ex.has(id))continue;Player.queue.push(it);ex.add(id);ad++}if(Player.index<0&&Player.queue.length)Player.index=0;renderFpTabBody();updateRightPanel();haptic(8);toast(ad?`Added ${ad} track${ad===1?'':'s'} to queue`:'Already in queue',ad?'success':'warning')}
function toggleLikeAll(items){const l=(items||[]).filter(it=>it&&it.trackId);if(!l.length){toast('Nothing to like','warning');return}const likes=getLikes(),ids=new Set(l.map(it=>String(it.trackId))),all=l.every(it=>likes.some(x=>String(x.trackId)===String(it.trackId)));
  if(all){const n=likes.filter(x=>!ids.has(String(x.trackId)));ls.set(KEY.likes,n);toast(`Removed ${l.length} from Liked`)}
  else{const ex=new Set(likes.map(x=>String(x.trackId)));let ad=0;for(const it of l){const id=String(it.trackId);if(ex.has(id))continue;likes.unshift(makeLikeEntry(it));ex.add(id);ad++}ls.set(KEY.likes,likes);toast(ad?`Liked ${ad} track${ad===1?'':'s'}`:'Already liked')}
  haptic(12);refreshLikes();refreshPage()}
async function refreshPage(){const y=MAIN().scrollTop;try{await route()}catch(e){}MAIN().scrollTop=y}

const menuCtx={trackId:null,playlistId:null};
async function openTrackMenu(tid,pid){
  if(!tid)return;menuCtx.trackId=String(tid);menuCtx.playlistId=pid||null;
  let it=getCached('track',tid);
  if(!it){try{const r=await apiLookup(tid,'song');it=r.find(x=>itemType(x)==='track')||r[0];if(it)cacheItems([it])}catch{}}
  if(!it){toast('Track not available','danger');return}
  const id=String(tid),liked=isLiked(id),cached=isCached(id),dl=DL.get(id),art=getArtwork(it,100),action=dlActionFor(it);
  const rows=[];
  if(dl&&['queued','crawling','saving'].includes(dl.status)){rows.push(`<button class="sheet-item" onclick="menuAction('openDl')"><i class="bi ${action.icon} text-info"></i><span>View ${action.isDirect?'download':'crawl'} progress</span></button>`);rows.push(`<button class="sheet-item danger" onclick="menuAction('cancelDl')"><i class="bi bi-x-circle"></i><span>Cancel ${action.isDirect?'download':'crawl'}</span></button>`)}
  else if(dl&&dl.status==='ready'){rows.push(`<button class="sheet-item" onclick="menuAction('saveReady')"><i class="bi bi-cloud-download text-warning"></i><span>Download now</span></button>`)}
  else if(cached){rows.push(`<button class="sheet-item" onclick="menuAction('export')"><i class="bi bi-file-earmark-arrow-down text-primary"></i><span>Save as file</span></button>`);rows.push(`<button class="sheet-item danger" onclick="menuAction('uncache')"><i class="bi bi-trash3"></i><span>Remove offline copy</span></button>`)}
  else rows.push(`<button class="sheet-item" onclick="menuAction('download')"><i class="bi ${action.icon} text-primary"></i><span>${action.label}</span></button>`);
  rows.push(`<button class="sheet-item telegram" onclick="menuAction('telegram')"><i class="bi bi-telegram"></i><span>Open in Telegram bot</span></button>`);
  rows.push(`<button class="sheet-item" onclick="menuAction('next')"><i class="bi bi-skip-end-fill"></i><span>Play next</span></button>`);
  rows.push(`<button class="sheet-item" onclick="menuAction('queue')"><i class="bi bi-list-ul"></i><span>Add to queue</span></button>`);
  rows.push(`<button class="sheet-item" onclick="menuAction('like')"><i class="bi ${liked?'bi-heart-fill text-danger':'bi-heart'}"></i><span>${liked?'Remove from Liked':'Add to Liked'}</span></button>`);
  rows.push(`<button class="sheet-item" onclick="menuAction('note')"><i class="bi bi-journal-text"></i><span>${getNote(id)?'Edit note':'Add note'}</span></button>`);
  rows.push(`<button class="sheet-item" onclick="menuAction('playlist')"><i class="bi bi-plus-circle"></i><span>Add to playlist</span></button>`);
  rows.push(`<button class="sheet-item" onclick="menuAction('share')"><i class="bi bi-share"></i><span>Share with…</span></button>`);
  rows.push(`<button class="sheet-item" onclick="menuAction('copyLink')"><i class="bi bi-link-45deg"></i><span>Copy link</span></button>`);
  rows.push(`<button class="sheet-item" onclick="menuAction('shareTs')"><i class="bi bi-clock-history"></i><span>Share with timestamp</span></button>`);
  rows.push(`<button class="sheet-item" onclick="menuAction('details')"><i class="bi bi-info-circle"></i><span>Track details</span></button>`);
  if(it.collectionId)rows.push(`<button class="sheet-item" onclick="menuAction('album')"><i class="bi bi-disc"></i><span>Go to album</span></button>`);
  if(it.artistId)rows.push(`<button class="sheet-item" onclick="menuAction('artist')"><i class="bi bi-person"></i><span>Go to artist</span></button>`);
  if(it.artistId)rows.push(`<button class="sheet-item" onclick="menuAction('radio')"><i class="bi bi-broadcast"></i><span>Go to radio</span></button>`);
  if(menuCtx.playlistId)rows.push(`<button class="sheet-item danger" onclick="menuAction('removeFromPl')"><i class="bi bi-x-circle"></i><span>Remove from playlist</span></button>`);
  $('#sheetTrackBody').innerHTML=`<div class="d-flex align-items-center gap-3 px-3 pb-3 pt-1">${art?`<img src="${esc(art)}" width="52" height="52" class="row-art" loading="lazy" decoding="async" alt="">`:`<div class="row-art row-art-ph"><i class="bi bi-music-note"></i></div>`}<div class="min-w-0"><div class="fw-bold text-truncate">${esc(it.trackName||'Track')}</div><div class="row-sub text-truncate">${esc(it.artistName||'')}</div></div></div><div class="px-2">${rows.join('')}</div>`;
  sheet('#sheetTrack').show()
}
async function menuAction(action){
  const id=menuCtx.trackId,plId=menuCtx.playlistId;sheet('#sheetTrack').hide();
  if(!id)return;let it=getCached('track',id);
  if(!it){try{const r=await apiLookup(id,'song');it=r.find(x=>itemType(x)==='track')||r[0];if(it)cacheItems([it])}catch{}}
  switch(action){
    case'details':closeFullPlayer();go('/track/'+id);break;
    case'play':playById(id);break;
    case'telegram':tgStart('track',id);break;
    case'next':{if(!it)return;Player.queue.splice(Player.index+1,0,it);toast('Playing next');renderFpTabBody();updateRightPanel();break}
    case'queue':{if(!it)return;if(Player.queue.some(t=>String(t.trackId)===id)){toast('Already in queue','warning');return}Player.queue.push(it);if(Player.index<0)Player.index=0;toast('Added to queue');renderFpTabBody();updateRightPanel();break}
    case'share':await shareTrack(id,it);break;
    case'copyLink':copyText(location.origin+(BASE||'')+'/track/'+id);break;
    case'shareTs':await shareWithTimestamp(id,it);break;
    case'like':toggleLikeById(id);break;
    case'note':closeFullPlayer();editNote(id);break;
    case'playlist':openPlaylistPicker(id);break;
    case'download':if(it)await startCrawlInternal(id,null,it);break;
    case'saveReady':DL.retry(id);break;
    case'export':await exportCached(id);break;
    case'uncache':await removeCached(id);break;
    case'openDl':closeFullPlayer();go('/library/downloads');break;
    case'cancelDl':DL.remove(id);toast('Cancelled');break;
    case'album':if(it?.collectionId){closeFullPlayer();go('/album/'+it.collectionId)}break;
    case'artist':if(it?.artistId){closeFullPlayer();go('/artist/'+it.artistId)}break;
    case'radio':if(it?.artistId){closeFullPlayer();artistRadio(it.artistId)}break;
    case'removeFromPl':if(plId){removeFromPlaylist(plId,id);toast('Removed')}break
  }
}
function fpMoreMenu(){if(IS_DESKTOP())return;if(!Player.track?.trackId)return;openTrackMenu(Player.track.trackId)}
async function shareTrack(id,it){const url=location.origin+(BASE||'')+'/track/'+id,title=it?.trackName||'Track',text=`${title}${it?.artistName?' — '+it.artistName:''}`;
  if(navigator.share){try{await navigator.share({title,text,url});toast('Shared')}catch(e){if(e.name!=='AbortError')copyText(url)}}else copyText(url)}
async function shareWithTimestamp(id,it){const isCur=Player.track&&String(Player.track.trackId)===String(id);const t=isCur?Math.floor(audio.currentTime):0;const url=location.origin+(BASE||'')+'/track/'+id+(t?`?t=${t}`:'');const title=it?.trackName||'Track';const text=`${title}${it?.artistName?' — '+it.artistName:''}${t?' @ '+fmtTime(t):''}`;
  if(navigator.share){try{await navigator.share({title,text,url});toast('Shared')}catch(e){if(e.name!=='AbortError')copyText(url)}}else copyText(url)}

let pickerItems=[];
function openPlaylistPicker(tid){const id=String(tid),c=getCached('track',id);if(c)return openPlaylistPickerForItems([c]);
  (async()=>{try{const r=await apiLookup(id,'song');const it=r.find(x=>itemType(x)==='track')||r[0];if(it){cacheItems([it]);openPlaylistPickerForItems([it])}else toast('Track not available','danger')}catch{toast('Track not available','danger')}})()}
function openPlaylistPickerForItems(items){pickerItems=(items||[]).filter(it=>it&&it.trackId);if(!pickerItems.length)return;
  const t=$('#sheetPlTitle');if(t)t.textContent=pickerItems.length>1?`Add ${pickerItems.length} tracks to playlist`:'Add to playlist';
  const pls=getPlaylists(),b=$('#sheetPlBody');
  if(!pls.length)b.innerHTML=`<div class="state" style="padding:30px 20px"><i class="bi bi-music-note-list"></i><p>No playlists yet</p></div>`;
  else b.innerHTML=pls.map(p=>{const c=p.tracks.find(t=>t.artworkUrl)?.artworkUrl||'';const badge=p.isPublic?'<span class="badge-chip public"><i class="bi bi-globe"></i>Public</span>':'<span class="badge-chip private"><i class="bi bi-lock"></i>Private</span>';return`<button class="sheet-item" onclick="pickPlaylist('${esc(p.id)}')">${c?`<img src="${esc(c)}" width="42" height="42" class="row-art" loading="lazy" decoding="async" alt="">`:`<div class="row-art row-art-ph"><i class="bi bi-music-note-list"></i></div>`}<span class="flex-grow-1 min-w-0"><span class="d-block text-truncate fw-semibold">${esc(p.name)}</span><span class="sub">${p.tracks.length} track${p.tracks.length!==1?'s':''} · ${p.isPublic?'Public':'Private'}</span></span><i class="bi bi-plus-lg text-primary"></i></button>`}).join('');
  sheet('#sheetPl').show()}
async function pickPlaylist(pid){if(!pickerItems.length)return;const all=getPlaylists(),pl=all.find(p=>p.id===pid);if(!pl)return;let ad=0;const ex=new Set(pl.tracks.map(t=>String(t.trackId)));
  for(const it of pickerItems){const id=String(it.trackId);if(ex.has(id))continue;pl.tracks.push(makeLikeEntry(it));ex.add(id);ad++}
  savePlaylists(all);sheet('#sheetPl').hide();haptic(10);toast(ad?`Added ${ad} track${ad===1?'':'s'} to "${pl.name}"`:'Already in playlist',ad?'success':'warning');pickerItems=[]}
async function newPlaylistPrompt(){
  const plEl=$('#sheetPl');
  const fromPicker=plEl&&plEl.classList.contains('show');
  const toAdd=fromPicker?pickerItems.slice():[];
  pickerItems=[];
  sheet('#sheetPl').hide();
  const n=await askText('New playlist','','Playlist name');
  if(!n)return;
  const pl=createPlaylist(n);
  if(!pl)return;
  if(toAdd.length){
    const all=getPlaylists(),p=all.find(x=>x.id===pl.id);
    if(p){const ex=new Set(p.tracks.map(t=>String(t.trackId)));for(const it of toAdd){const id=String(it.trackId);if(ex.has(id))continue;p.tracks.push(makeLikeEntry(it));ex.add(id)}savePlaylists(all)}
  }
  if(currentPath().startsWith('/library/playlists'))go('/library/playlists/'+pl.id)
}
async function startCrawl(tid,btn){
  const id=String(tid),it0=getCached('track',id),action=it0?dlActionFor(it0):{icon:'bi-cloud-arrow-down',label:'Crawl'};
  if(btn){btn.disabled=true;btn.innerHTML='<span class="spinner-border spinner-border-sm" style="width:14px;height:14px;border-width:2px"></span>'}
  try{let it=it0;if(!it){try{const r=await apiLookup(tid,'song');it=r.find(x=>itemType(x)==='track')||r[0];if(it)cacheItems([it])}catch{}}
    if(!it){toast('Track unavailable','danger');return}
    await DL.add(it);haptic(12);
    if(currentPath()===`/track/${tid}`){const st=await apiCrawlStatus(tid);renderCrawlCard(it,st);startTrackPoller(tid)}
  }catch(e){toast(action.label+' failed: '+e.message,'danger')}
  finally{if(btn){btn.disabled=false;btn.innerHTML=`<i class="bi ${action.icon}"></i> ${action.label}`}}
}
async function startCrawlInternal(tid,btn,known){const it0=known||getCached('track',tid),action=it0?dlActionFor(it0):{icon:'bi-cloud-arrow-down',label:'Crawl'};
  if(btn){btn.disabled=true;btn.innerHTML='<span class="spinner-border spinner-border-sm" style="width:14px;height:14px;border-width:2px"></span>'}
  try{let it=it0||(await DL._fetchTrack(tid));if(!it){toast('Track unavailable','danger');return}await DL.add(it)}
  finally{if(btn){btn.disabled=false;btn.innerHTML=`<i class="bi ${action.icon}"></i> ${action.label}`}}
}
async function fetchAllArtistTracks(aid,onProgress){
  if(!aid)return [];
  const all=[],seen=new Set();
  const push=t=>{const id=String(t?.trackId||'');if(!id||seen.has(id))return;seen.add(id);all.push(t)};
  try{const r=await apiLookup(aid,'song');r.filter(x=>itemType(x)==='track').forEach(push)}catch{}
  if(all.length===0||all.length<30){
    const limit=200;let page=1,guard=0;
    while(guard++<50){
      let res;try{res=await apiArtistTracks(aid,{page,limit,sort:'album'})}catch{break}
      const tr=(res.results||[]).filter(t=>itemType(t)==='track');tr.forEach(push);
      if(onProgress)try{onProgress(all.length)}catch{}
      if(!res.hasMore||tr.length<limit)break;
      page++
    }
  }
  cacheItems(all);return all
}
async function crawlAll(type,id,btn,forceSave=false){
  const orig=btn?.innerHTML;
  if(btn){btn.disabled=true;btn.innerHTML='<span class="spinner-border spinner-border-sm me-2" style="width:14px;height:14px;border-width:2px"></span>Fetching…'}
  try{
    if(type==='track'){await apiQueueAdd({trackId:cleanId(id)});toast('Queued');haptic(12);return}
    if(type==='collection'||type==='album'){
      let album=getCached('collection',id);
      if(!album){const r=await apiLookup(id,'album');album=r.find(x=>itemType(x)==='collection')||r[0];if(album)cacheItems([album])}
      const tracks=(await apiLookup(id,'song')).filter(x=>itemType(x)==='track');cacheItems(tracks);
      const need=tracks.filter(t=>!isCached(t.trackId));
      if(!need.length){toast('All tracks already saved','success');return}
      await DL.addBatch({subType:'album',sourceId:String(id),name:album?.collectionName||'Album',artist:album?.artistName||'',artwork:getArtwork(album,100),tracks:need,forceSave});
      try{await apiQueueAdd({albumId:cleanId(id)})}catch{}
      toast(`${forceSave?'Downloading':'Crawling'} ${need.length} track${need.length!==1?'s':''}…`);
      haptic(12);if(currentPath()===`/album/${id}`)refreshPage();return
    }
    if(type==='artist'){
      const artist=getCached('artist',id);
      const artistName=artist?.artistName||'Artist';
      const allTracks=await fetchAllArtistTracks(id,n=>{if(btn)btn.innerHTML=`<span class="spinner-border spinner-border-sm me-2" style="width:14px;height:14px;border-width:2px"></span>${n} tracks…`});
      if(!allTracks.length){toast('No tracks found for this artist','warning');return}
      const need=allTracks.filter(t=>!isCached(t.trackId));
      if(!need.length){toast(`All ${allTracks.length} tracks already saved`,'success');return}
      if(btn)btn.innerHTML=`<span class="spinner-border spinner-border-sm me-2" style="width:14px;height:14px;border-width:2px"></span>Queuing ${need.length}…`;
      await DL.addBatch({subType:'artist',sourceId:String(id),name:artistName,artist:'',artwork:getArtwork(artist,100),tracks:need,forceSave});
      try{await apiQueueAdd({artistId:cleanId(id)})}catch{}
      toast(`${forceSave?'Downloading':'Crawling'} ${need.length} of ${allTracks.length} track${allTracks.length!==1?'s':''}…`);
      haptic(12);if(isLibDl())updateDlLists()
    }
  }catch(e){toast('Crawl failed: '+(e.message||e),'danger')}
  finally{if(btn){btn.disabled=false;btn.innerHTML=orig}}
}
async function removeCached(id){await cacheDel(id);cachedIds.delete(String(id));toast('Removed from offline');refreshCurrentView()}
async function clearAllCache(){const ok=await askConfirm('Clear offline cache?','All downloaded tracks will be deleted.','Clear all');if(!ok)return;
  await cacheClear();cachedIds.clear();toast('Offline cache cleared');updateSettingsSheet();if(isLibDl())updateDlLists();if(isTrackRoute()){const t=getCached('track',currentPath().split('/')[2]);if(t)renderCrawlCard(t,null)}}
function refreshCurrentView(){if(isLibDl())updateDlLists();else route()}
function scrollActiveLibTab(){requestAnimationFrame(()=>{const on=document.querySelector('.lib-tabs .lt.on');if(!on)return;const p=on.parentElement;if(!p)return;const t=on.offsetLeft-(p.clientWidth-on.offsetWidth)/2;p.scrollLeft=Math.max(0,t)})}
function libraryTabs(a){
  const adl=DL.active().length+DL.ready().length+DL.activeBatches().length+DL.readyBatches().length,fol=getFollowed().length,lik=getLikes().length,pls=getPlaylists().length;
  const it=(k,l,ic,b)=>{const on=a===k?' on':'';const href=`${SCOPE}library/${k}`;const bd=(b!==undefined&&b>0)?`<span class="lt-badge">${b}</span>`:'';return`<a href="${href}" data-link class="lt${on}"><i class="bi bi-${ic}"></i><span>${l}</span>${bd}</a>`};
  return`<div class="lib-tabs">${it('likes','Liked','heart-fill',lik)+it('following','Following','people-fill',fol)+it('playlists','Lists','music-note-list',pls)+it('downloads','Offline','cloud-check-fill',adl)+it('stats','Stats','bar-chart-fill',null)+it('history','History','clock-history')}</div>`
}
function likesToItems(){return getLikes().map(l=>({wrapperType:'track',trackId:l.trackId,trackName:l.trackName,artistName:l.artistName,artistId:l.artistId,collectionName:l.collectionName,collectionId:l.collectionId,attachments:{artworkUrls:l.artworkUrl?[{url:l.artworkUrl}]:[],audioUrls:isCached(l.trackId)?[{url:'local',quality:'320'}]:[]}}))}
function viewLikes(){const likes=getLikes();let html=libraryTabs('likes');
  if(!likes.length)html+=emptyState('heart','No liked songs yet','Tap the heart on any track to save it here.');
  else{const items=likesToItems();_pageTracks=items;const ids=items.map(x=>x.trackId).join(',');
    html+=`<div class="d-flex align-items-center px-3 pt-4 pb-3"><div class="flex-grow-1 fw-bold">${likes.length} liked song${likes.length!==1?'s':''}</div><button class="pill-btn success" onclick="playIds('${esc(ids)}')"><i class="bi bi-play-fill"></i> Play all</button></div>`;
    html+=items.map(t=>trackRow(t)).join('')}
  MAIN().innerHTML=html}
function viewFollowing(){
  _pageTracks=[];
  const l=getFollowed();
  let html=libraryTabs('following');
  if(!l.length)html+=emptyState('person-check','Not following any artists','Follow artists to see them here.');
  else{
    html+=`<div class="px-3 pt-4 pb-2"><div class="fw-bold">${l.length} artist${l.length!==1?'s':''}</div><div class="text-secondary" style="font-size:.74rem">Artists you follow</div></div>`;
    html+=`<div class="mm-grid mm-grid-narrow">`;
    html+=l.map(a=>{const art=a.artwork||'';return`<a class="artist-item" href="${SCOPE}artist/${esc(a.artistId)}" data-link>${art?`<img class="artist-art" src="${esc(art)}" loading="lazy" decoding="async" alt="">`:`<div class="artist-art artist-art-ph"><i class="bi bi-person-fill"></i></div>`}<div class="card-name text-truncate">${esc(a.artistName||'Artist')}</div><div class="card-sub text-truncate">${esc(a.primaryGenreName||'Artist')}</div></a>`}).join('');
    html+=`</div>`
  }
  MAIN().innerHTML=html
}
function getSmartPlaylists(){
  const s=getStats(),tracks=s.tracks||{},likes=getLikes();
  const most=Object.entries(tracks).map(([id,v])=>({id,...v})).sort((a,b)=>(b.ms||0)-(a.ms||0));
  const now=Date.now();
  const forgot=likes.filter(l=>{const st=tracks[String(l.trackId)];return!st||(now-(st.lastAt||0))>30*86400000});
  const totalInLib=new Set([...Object.keys(tracks),...likes.map(l=>String(l.trackId))]).size;
  const out=[];
  if(totalInLib>0)out.push({key:'shuffle',name:'Shuffle All',count:totalInLib,icon:'shuffle',color:'primary',onclick:'playSmartShuffle()'});
  if(most.length>0)out.push({key:'most',name:'Most Played',count:most.length,icon:'fire',color:'danger'});
  if(likes.length>0)out.push({key:'recent',name:'Recently Added',count:likes.length,icon:'clock',color:'info'});
  if(forgot.length>0)out.push({key:'forgot',name:'Forgotten Favorites',count:forgot.length,icon:'arrow-counterclockwise',color:'warning'});
  return out
}
function smartRowHtml(sp){
  const attrs=sp.onclick?`onclick="${sp.onclick}"`:`onclick="smartOpen('${sp.key}')"`;
  const art=sp.art?`<img class="row-art" src="${esc(sp.art)}" loading="lazy" decoding="async" alt="">`:`<div class="row-art row-art-ph" style="background:rgba(var(--bs-${sp.color}-rgb),.18);color:var(--bs-${sp.color})"><i class="bi bi-${sp.icon}"></i></div>`;
  return`<div class="row-item" ${attrs} style="cursor:pointer">${art}<div class="row-info"><div class="min-w-0 flex-grow-1"><div class="row-title text-truncate">${esc(sp.name)}</div><div class="row-sub text-truncate">${sp.count} track${sp.count!==1?'s':''}</div></div><i class="bi bi-chevron-right text-secondary"></i></div></div>`
}
function viewPlaylists(){
  _pageTracks=[];
  const pls=getPlaylists();
  const smart=getSmartPlaylists();
  let html=libraryTabs('playlists');
  if(smart.length){html+=`<h6 class="sec-title tight">Smart playlists</h6>`;html+=smart.map(smartRowHtml).join('')}
  html+=`<div class="d-flex align-items-center px-3 pt-4 pb-2"><div class="flex-grow-1 fw-bold">Your playlists</div><button class="pill-btn primary" onclick="newPlaylistPrompt()"><i class="bi bi-plus-lg"></i> New</button></div>`;
  if(!pls.length)html+=emptyState('music-note-list','No playlists yet','Create one to organize your music.');
  else html+=pls.map(pl=>{const c=pl.tracks.find(t=>t.artworkUrl)?.artworkUrl||'';const badge=pl.isPublic?'<span class="badge-chip public"><i class="bi bi-globe"></i>Public</span>':'<span class="badge-chip private"><i class="bi bi-lock"></i>Private</span>';return`<a class="row-item" href="${SCOPE}library/playlists/${esc(pl.id)}" data-link>${c?`<img class="row-art" src="${esc(c)}" loading="lazy" decoding="async" alt="">`:`<div class="row-art row-art-ph"><i class="bi bi-music-note-list"></i></div>`}<div class="row-info"><div class="min-w-0 flex-grow-1"><div class="row-title text-truncate">${esc(pl.name)} ${badge}</div><div class="row-sub text-truncate">${pl.tracks.length} track${pl.tracks.length!==1?'s':''}</div></div><i class="bi bi-chevron-right text-secondary"></i></div></a>`}).join('');
  MAIN().innerHTML=html
}
function renderPlaylistDetail(pid){
  const pl=getPlaylists().find(p=>p.id===pid);
  if(!pl){MAIN().innerHTML=emptyState('music-note-list','Playlist not found');return}
  setPageTitle(pl.name);
  const items=playlistToItems(pid);
  _pageTracks=items;
  const ids=items.map(x=>String(x.trackId)).join(',');
  const badge=pl.isPublic?'<span class="badge-chip public" style="vertical-align:middle"><i class="bi bi-globe"></i>Public</span>':'<span class="badge-chip private" style="vertical-align:middle"><i class="bi bi-lock"></i>Private</span>';
  let html=`<div class="pb-4">${backBtn()}<div class="px-3 pt-1"><div class="d-flex align-items-center gap-2"><div style="font-size:1.35rem;font-weight:800;letter-spacing:-.02em" class="text-truncate flex-grow-1">${esc(pl.name)}</div>${badge}</div><div class="text-secondary" style="font-size:.8rem">${pl.tracks.length} track${pl.tracks.length!==1?'s':''}${pl.createdAt?` · ${fmtAgo(pl.createdAt)}`:''}</div></div>`;
  html+=`<div class="action-bar">${items.length?`<button class="pill-btn success" onclick="playIds('${esc(ids)}')"><i class="bi bi-play-fill"></i> Play</button>`:''}${items.length?`<button class="pill-btn" onclick="addAllToQueue(playlistToItems('${esc(pid)}'))"><i class="bi bi-list-ul"></i> Queue</button>`:''}${pl.isPublic?`<button class="pill-btn" onclick="sharePlaylist('${esc(pid)}')"><i class="bi bi-share"></i> Share</button>`:''}<button class="icon-btn" style="background:rgba(var(--bs-body-color-rgb),.09)" onclick="playlistMore('${esc(pid)}')"><i class="bi bi-three-dots"></i></button></div>`;
  html+=items.length?items.map(t=>trackRow(t,{playlistId:pid})).join(''):emptyState('music-note','This playlist is empty','Add songs from the ⋮ menu.');
  html+=`</div>`;MAIN().innerHTML=html
}
function playlistMore(pid){const pl=getPlaylists().find(p=>p.id===pid);if(!pl)return;const isPublic=!!pl.isPublic;
  const r=[`<button class="sheet-item" onclick="closeAllSheets(); renamePlaylist('${esc(pid)}')"><i class="bi bi-pencil"></i><span>Rename playlist</span></button>`,`<button class="sheet-item" onclick="closeAllSheets(); togglePlaylistPublic('${esc(pid)}')"><i class="bi bi-${isPublic?'lock':'globe'}"></i><span>Make ${isPublic?'private':'public'}</span></button>`,isPublic?`<button class="sheet-item" onclick="closeAllSheets(); sharePlaylist('${esc(pid)}')"><i class="bi bi-share"></i><span>Share link</span></button>`:'',isPublic?`<button class="sheet-item" onclick="closeAllSheets(); copyPlaylistLink('${esc(pid)}')"><i class="bi bi-link-45deg"></i><span>Copy public link</span></button>`:'',`<button class="sheet-item danger" onclick="closeAllSheets(); deletePlaylist('${esc(pid)}')"><i class="bi bi-trash3"></i><span>Delete playlist</span></button>`].filter(Boolean);
  $('#sheetTrackBody').innerHTML=`<div class="px-3 pb-2 pt-1"><div class="fw-bold">Playlist actions</div></div><div class="px-2">${r.join('')}</div>`;sheet('#sheetTrack').show()}
function playlistToItems(pid){const pl=getPlaylists().find(p=>p.id===pid);if(!pl)return[];return pl.tracks.map(l=>({wrapperType:'track',trackId:l.trackId,trackName:l.trackName,artistName:l.artistName,artistId:l.artistId,collectionName:l.collectionName,collectionId:l.collectionId,attachments:{artworkUrls:l.artworkUrl?[{url:l.artworkUrl}]:[],audioUrls:isCached(l.trackId)?[{url:'local',quality:'320'}]:[]}}))}
async function renamePlaylist(id){const pl=getPlaylists().find(p=>p.id===id);if(!pl)return;const n=await askText('Rename playlist',pl.name,'Playlist name');if(!n)return;
  const all=getPlaylists(),t=all.find(p=>p.id===id);if(!t)return;t.name=n;savePlaylists(all);if(isPlDet(id))renderPlaylistDetail(id);else viewPlaylists();toast('Renamed')}
async function deletePlaylist(id){const pl=getPlaylists().find(p=>p.id===id);if(!pl)return;const ok=await askConfirm('Delete playlist?',`"${pl.name}" and its contents will be removed.`,'Delete');if(!ok)return;
  savePlaylists(getPlaylists().filter(p=>p.id!==id));toast('Playlist deleted');go('/library/playlists')}
function togglePlaylistPublic(pid){const all=getPlaylists();const pl=all.find(p=>p.id===pid);if(!pl)return;
  pl.isPublic=!pl.isPublic;pl.updatedAt=Date.now();savePlaylists(all);
  if(pl.isPublic){toast('Playlist is now public — share the link','success');if(isPlDet(pid))renderPlaylistDetail(pid)}
  else{toast('Playlist is now private');if(isPlDet(pid))renderPlaylistDetail(pid)}}
function sharePlaylist(pid){const pl=getPlaylists().find(p=>p.id===pid);if(!pl)return;if(!pl.isPublic){toast('Make the playlist public first','warning');return}
  const url=location.origin+(BASE||'')+'/p/'+encodeURIComponent(pid);
  const text=`Check out my playlist "${pl.name}" on MusicMan`;
  if(navigator.share)navigator.share({title:pl.name,text,url}).catch(e=>{if(e.name!=='AbortError')copyText(url)});
  else copyText(url)}
function copyPlaylistLink(pid){const pl=getPlaylists().find(p=>p.id===pid);if(!pl||!pl.isPublic){toast('Make the playlist public first','warning');return}
  copyText(location.origin+(BASE||'')+'/p/'+encodeURIComponent(pid))}
async function viewPublicPlaylist(pid){
  if(!pid){MAIN().innerHTML=emptyState('music-note-list','Playlist not found');return}
  setPageTitle('Playlist');
  const pl=getPlaylists().find(p=>p.id===pid&&p.isPublic);
  if(!pl){MAIN().innerHTML=emptyState('lock','Playlist not found','The playlist may be private or not on this device.');return}
  setPageTitle(pl.name);
  const items=playlistToItems(pid);_pageTracks=items;
  const ids=items.map(x=>String(x.trackId)).join(',');
  let html=`<div class="pb-4">${backBtn()}<div class="px-3 pt-1"><div class="d-flex align-items-center gap-2"><div style="font-size:1.35rem;font-weight:800" class="text-truncate flex-grow-1">${esc(pl.name)}</div><span class="badge-chip public"><i class="bi bi-globe"></i>Public</span></div><div class="text-secondary" style="font-size:.8rem">${items.length} track${items.length!==1?'s':''}</div></div>`;
  html+=`<div class="action-bar scrollable">${items.length?`<button class="pill-btn success" onclick="playIds('${esc(ids)}')"><i class="bi bi-play-fill"></i> Play</button>`:''}<button class="icon-btn" style="background:rgba(var(--bs-body-color-rgb),.09)" onclick="copyText(location.href)"><i class="bi bi-link-45deg"></i></button></div>`;
  html+=items.length?items.map(t=>trackRow(t)).join(''):emptyState('music-note','This playlist is empty');
  html+=`</div>`;MAIN().innerHTML=html;refreshLikes()
}

function viewStats(){
  const s=getStats(),tk=s.tracks||{},days=s.days||{};
  const totalMs=s.totalMs||0,plays=s.plays||0;
  const mins=Math.round(totalMs/60000),hours=Math.floor(mins/60),remMins=mins%60,unique=Object.keys(tk).length;
  const entries=Object.entries(tk).map(([id,v])=>({id,...v}));
  const topTracks=[...entries].sort((a,b)=>(b.ms||0)-(a.ms||0));
  const topArtists=(()=>{const m=new Map();for(const t of entries){if(!t.artist)continue;const k=t.artist;const cur=m.get(k)||{name:k,artistId:t.artistId||'',ms:0,count:0,artwork:t.artwork||''};cur.ms+=t.ms||0;cur.count+=t.count||0;if(!cur.artwork&&t.artwork)cur.artwork=t.artwork;if(!cur.artistId&&t.artistId)cur.artistId=t.artistId;m.set(k,cur)}return[...m.values()].sort((a,b)=>b.ms-a.ms).slice(0,12)})();
  const dayKey=ts=>new Date(ts).toISOString().slice(0,10);
  const daySet=new Set(Object.keys(days));
  for(const t of entries)if(t.lastAt)daySet.add(dayKey(t.lastAt));
  for(const r of getRecentlyPlayed())if(r.at)daySet.add(dayKey(r.at));
  let streak=0;const d=new Date();
  for(;;){if(daySet.has(dayKey(d.getTime()))){streak++;d.setDate(d.getDate()-1)}else break}
  const week=[];const today=new Date();
  for(let i=6;i>=0;i--){const dd=new Date(today);dd.setDate(dd.getDate()-i);const k=dayKey(dd.getTime());week.push({k,label:dd.toLocaleDateString(undefined,{weekday:'short'}).slice(0,2),ms:days[k]||0})}
  const weekMax=Math.max(1,...week.map(w=>w.ms));
  const dlActive=DL.active().length+DL.activeBatches().length;
  const dlReady=DL.ready().length+DL.readyBatches().length;
  const dlFailed=DL.failed().length+DL.failedBatches().length;
  const dlTotal=dlActive+dlReady+dlFailed;
  let h=libraryTabs('stats');
  h+=`<div class="px-3 pt-4 pb-2"><div class="fw-bold">Your listening</div><div class="text-secondary" style="font-size:.8rem">All-time statistics</div></div>`;
  h+=`<div class="stat-grid"><div class="stat-card"><div class="n">${hours?`${hours}<span style="font-size:.6em;opacity:.7">h</span> ${remMins}<span style="font-size:.6em;opacity:.7">m</span>`:`${mins}<span style="font-size:.6em;opacity:.7">m</span>`}</div><div class="l">Total listening</div></div><div class="stat-card"><div class="n">${plays.toLocaleString()}</div><div class="l">Total plays</div></div><div class="stat-card"><div class="n">${unique}</div><div class="l">Unique tracks</div></div><div class="stat-card"><div class="n">${streak>0?`${streak}<span style="font-size:.6em;opacity:.7">🔥</span>`:'—'}</div><div class="l">Day streak</div></div></div>`;
  if(dlTotal>0){h+=secTitle('Downloads');h+=`<div class="stat-grid">`;h+=`<div class="stat-card" style="cursor:pointer" onclick="go('/library/downloads')"><div class="n text-info">${dlActive}</div><div class="l">In progress</div></div>`;h+=`<div class="stat-card" style="cursor:pointer" onclick="go('/library/downloads')"><div class="n text-warning">${dlReady}</div><div class="l">Ready to save</div></div>`;h+=`<div class="stat-card" style="cursor:pointer" onclick="go('/library/downloads')"><div class="n ${dlFailed>0?'text-danger':''}">${dlFailed}</div><div class="l">Failed</div></div>`;h+=`<div class="stat-card" style="cursor:pointer" onclick="go('/library/downloads')"><div class="n">${cachedIds.size}</div><div class="l">Offline files</div></div>`;h+=`</div>`}
  h+=secTitle('Last 7 days');
  h+=`<div class="week-chart">${week.map(w=>{const hh=Math.max(4,Math.round((w.ms/weekMax)*76));return`<div class="week-day"><div class="week-bar" style="height:${hh}px" title="${Math.round(w.ms/60000)}m"></div><div class="week-lbl">${w.label}</div></div>`}).join('')}</div>`;
  if(!entries.length){h+=emptyState('bar-chart','No stats yet','Play some tracks and check back here.');h+=`<div class="px-3 pt-3"><button class="pill-btn w-100 justify-content-center" onclick="go('/')"><i class="bi bi-house-door"></i> Go to Home</button></div>`;MAIN().innerHTML=h;return}
  if(topArtists.length){h+=secTitle(`Top artists · ${topArtists.length}`);h+=`<div class="mm-grid mm-grid-narrow">`+topArtists.map(a=>{const art=a.artwork||'';const minsA=Math.round(a.ms/60000);const dur=minsA<1?'<1m':minsA<60?`${minsA}m`:`${Math.floor(minsA/60)}h ${minsA%60}m`;const href=a.artistId?`${SCOPE}artist/${esc(a.artistId)}`:`${SCOPE}search?q=${encodeURIComponent(a.name)}`;return`<a class="artist-item" href="${href}" data-link>${art?`<img class="artist-art" src="${esc(art)}" loading="lazy" decoding="async" alt="">`:`<div class="artist-art artist-art-ph"><i class="bi bi-person-fill"></i></div>`}<div class="card-name text-truncate">${esc(a.name)}</div><div class="card-sub text-truncate">${dur} · ${a.count}×</div></a>`}).join('')+`</div>`}
  h+=secTitle('Top tracks');
  h+=topTracks.slice(0,30).map((t,i)=>{const minsT=Math.round(t.ms/60000);const dur=minsT<1?'<1m':`${minsT}m`;const trackObj={trackId:t.id,trackName:t.name,artistName:t.artist,artistId:t.artistId||'',artworkUrl:t.artwork||''};return trackRow(trackObj,{extraSub:`${t.count}× · ${dur}`})}).join('');
  h+=`<div class="px-3 pt-4 pb-2 d-flex gap-2"><button class="pill-btn flex-fill justify-content-center" onclick="go('/library/history')"><i class="bi bi-clock-history"></i> History</button><button class="pill-btn danger sm" onclick="resetStats(); viewStats()"><i class="bi bi-trash3"></i> Reset</button></div>`;
  MAIN().innerHTML=h
}
function viewHistory(){
  const r=getRecentlyPlayed(),stats=getStats().tracks||{};
  _pageTracks=r.map(x=>({wrapperType:'track',trackId:x.trackId,trackName:x.trackName,artistName:x.artistName,artistId:x.artistId,collectionName:x.collectionName,collectionId:x.collectionId,attachments:{artworkUrls:x.artworkUrl?[{url:x.artworkUrl}]:[]}}));
  let html=libraryTabs('history');
  if(!r.length)html+=emptyState('clock-history','No history yet','Tracks you play appear here.');
  else{html+=`<div class="d-flex align-items-center px-3 pt-4 pb-2"><div class="flex-grow-1 fw-bold">Recently played</div><button class="pill-btn sm" onclick="clearHistory()">Clear</button></div>`;
    html+=r.map(x=>{const s=stats[String(x.trackId)],pl=s?` · ${s.count}×`:'';const al=artistLink(x,true);return`<div class="hist-row row-item"><button class="row-play" onclick="playById('${esc(x.trackId)}')"><i class="bi bi-play-fill"></i></button><a class="row-info" href="${SCOPE}track/${esc(x.trackId)}" data-link>${x.artworkUrl?`<img class="row-art" src="${esc(x.artworkUrl)}" loading="lazy" decoding="async" alt="">`:`<div class="row-art row-art-ph"><i class="bi bi-music-note"></i></div>`}<div class="min-w-0 flex-grow-1"><div class="row-title text-truncate">${esc(x.trackName||'Track')}</div><div class="row-sub text-truncate">${al} · ${fmtAgo(x.at)}${pl}</div></div></a><button class="icon-btn sm" onclick="event.preventDefault();openTrackMenu('${esc(x.trackId)}')"><i class="bi bi-three-dots-vertical"></i></button></div>`}).join('')}
  MAIN().innerHTML=html
}
function clearHistory(){ls.remove(KEY.plays);toast('History cleared');viewHistory()}
function smartOpen(kind){const s=getStats(),tracks=s.tracks||{},likes=getLikes();let list=[],title='';
  if(kind==='most'){list=Object.entries(tracks).map(([id,v])=>({id,...v})).sort((a,b)=>(b.ms||0)-(a.ms||0)).slice(0,50).map(t=>({wrapperType:'track',trackId:t.id,trackName:t.name,artistName:t.artist,attachments:{artworkUrls:t.artwork?[{url:t.artwork}]:[]}}));title='Most Played'}
  else if(kind==='recent'){list=[...likes].sort((a,b)=>(b.addedAt||0)-(a.addedAt||0)).slice(0,50).map(l=>({wrapperType:'track',trackId:l.trackId,trackName:l.trackName,artistName:l.artistName,artistId:l.artistId,attachments:{artworkUrls:l.artworkUrl?[{url:l.artworkUrl}]:[]}}));title='Recently Added'}
  else{const now=Date.now();list=likes.filter(l=>{const st=tracks[String(l.trackId)];return!st||(now-(st.lastAt||0))>30*86400000}).map(l=>({wrapperType:'track',trackId:l.trackId,trackName:l.trackName,artistName:l.artistName,artistId:l.artistId,attachments:{artworkUrls:l.artworkUrl?[{url:l.artworkUrl}]:[]}}));title='Forgotten Favorites'}
  const ids=list.map(t=>t.trackId).join(',');
  let h=`<div class="pb-4">${backBtn()}<div class="px-3 pt-1"><div style="font-size:1.35rem;font-weight:800">${title}</div><div class="text-secondary" style="font-size:.8rem">${list.length} tracks</div></div>`;
  if(list.length)h+=`<div class="action-bar"><button class="pill-btn success" onclick="playIds('${esc(ids)}')"><i class="bi bi-play-fill"></i> Play all</button><button class="pill-btn" onclick="addAllToQueue(_pageTracks)"><i class="bi bi-list-ul"></i> Queue</button></div>`;
  _pageTracks=list;h+=list.length?list.map(t=>trackRow(t)).join(''):emptyState('music-note','Nothing here yet');h+=`</div>`;MAIN().innerHTML=h;refreshLikes()}

let dlUnsub=null;
function renderDownloads(){MAIN().innerHTML=libraryTabs('downloads')+`<div id="dlContent"><div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div></div>`;
  if(dlUnsub)dlUnsub();dlUnsub=DL.onChange(()=>{if(isLibDl())updateDlLists()});updateDlLists()}
async function updateDlLists(){
  const wrap=$('#dlContent');if(!wrap)return;
  const act=DL.active(),rdy=DL.ready(),fail=DL.failed();
  const actB=DL.activeBatches(),rdyB=DL.readyBatches(),failB=DL.failedBatches();
  const all=await cacheAll(),ts=all.reduce((s,e)=>s+(e.size||0),0);
  let html='';
  const totalAct=act.length+actB.length,totalRdy=rdy.length+rdyB.length,totalFail=fail.length+failB.length;
  const hasAny=all.length||totalAct||totalRdy||totalFail;
  if(hasAny){
    let remaining=act.length;
    for(const b of actB)remaining+=Math.max(0,(b.trackCount||0)-(b.readyCount||0));
    let readyFiles=rdy.length;
    for(const b of rdyB)readyFiles+=(b.trackCount||0);
    let pctSum=0,pctN=0;
    for(const it of act){pctSum+=it.percent||0;pctN++}
    for(const b of actB){pctSum+=b.percent||0;pctN++}
    const avgPct=pctN?Math.round(pctSum/pctN):0;
    html+=`<div class="px-3 pt-4 pb-2"><div class="d-flex align-items-center gap-2"><div class="flex-grow-1 min-w-0"><div class="fw-bold">Offline & Downloads</div><div class="text-secondary" style="font-size:.74rem">${all.length} file${all.length!==1?'s':''} · ${fmtSize(ts)} saved${remaining?' · '+remaining+' pending':''}</div></div>${all.length?`<button class="icon-btn sm" onclick="exportAllCached()" title="Export all"><i class="bi bi-file-earmark-arrow-down"></i></button><button class="icon-btn sm text-danger" onclick="clearAllCache()" title="Clear all"><i class="bi bi-trash3"></i></button>`:''}</div></div>`;
    html+=`<div class="stat-grid" style="padding:0 16px 8px">`;
    html+=`<div class="stat-card"><div class="n">${all.length}</div><div class="l">Saved</div></div>`;
    html+=`<div class="stat-card"><div class="n" style="font-size:1.05rem">${fmtSize(ts)}</div><div class="l">Total size</div></div>`;
    if(totalAct)html+=`<div class="stat-card" style="cursor:pointer" onclick="dlScrollTo('active')"><div class="n text-info">${totalAct}<span style="font-size:.5em;opacity:.75;font-weight:600"> · ${avgPct}%</span></div><div class="l">In progress</div></div>`;
    if(totalRdy)html+=`<div class="stat-card" style="cursor:pointer" onclick="dlScrollTo('ready')"><div class="n text-warning">${readyFiles||totalRdy}</div><div class="l">Ready file${readyFiles!==1?'s':''}</div></div>`;
    if(totalFail)html+=`<div class="stat-card" style="cursor:pointer" onclick="dlScrollTo('failed')"><div class="n text-danger">${totalFail}</div><div class="l">Failed</div></div>`;
    html+=`</div>`;
    if(totalAct){html+=`<div style="padding:2px 16px 12px"><div class="d-flex align-items-center gap-2 mb-1" style="font-size:.72rem"><i class="bi bi-cloud-arrow-down text-info"></i><span class="text-info fw-bold">${remaining} track${remaining!==1?'s':''} remaining</span><span class="flex-grow-1"></span><span class="text-secondary fw-bold">${avgPct}%</span></div>${progressHtml(avgPct,'bg-info')}</div>`}
  }
  if(actB.length||act.length){html+=`<div id="dl-sec-active">`;if(actB.length){html+=`<h6 class="sec-title tight">Albums / Artists · ${actB.length}</h6>`;html+=actB.map(dlBatchRowHtml).join('')}if(act.length){html+=`<h6 class="sec-title tight">Tracks · ${act.length}</h6>`;html+=act.map(it=>dlRowHtml(it)).join('')}html+=`</div>`}
  if(rdyB.length||rdy.length){html+=`<div id="dl-sec-ready">`;if(rdyB.length){html+=`<h6 class="sec-title tight">Ready batches · ${rdyB.length}</h6>`;html+=rdyB.map(dlBatchRowHtml).join('')}if(rdy.length){html+=`<h6 class="sec-title tight">Ready tracks · ${rdy.length}</h6>`;html+=rdy.map(it=>dlRowHtml(it)).join('')}html+=`</div>`}
  if(failB.length||fail.length){html+=`<div id="dl-sec-failed">`;if(failB.length){html+=`<h6 class="sec-title tight">Failed batches · ${failB.length}</h6>`;html+=failB.map(dlBatchRowHtml).join('')}if(fail.length){html+=`<h6 class="sec-title tight">Failed tracks · ${fail.length}</h6>`;html+=fail.map(it=>dlRowHtml(it)).join('')}html+=`</div>`}
  if(!hasAny){html+=emptyState('download','Nothing offline yet','Download a track from any menu.')}
  else if(all.length){
    html+=`<div id="dl-sec-saved">`;
    html+=`<div class="d-flex align-items-center px-3 pt-4 pb-2"><div class="flex-grow-1 min-w-0"><div class="fw-bold">Available offline</div><div class="text-secondary" style="font-size:.74rem">${all.length} track${all.length!==1?'s':''} · ${fmtSize(ts)}</div></div></div>`;
    html+=all.map(e=>{const m=e.meta||{},art=m.artwork||getArtwork({attachments:m.attachments},100)||'',id=String(e.trackId),pl=Player.track&&String(Player.track.trackId)===id;const al=artistLink({artistName:m.artist||m.artistName||'',artistId:m.artistId},true);return`<div class="dl-row row-item"><button class="row-play ${pl&&Player.playing?'playing':''}" onclick="playCachedById('${esc(id)}')"><i class="bi ${pl&&Player.playing?'bi-pause-fill':'bi-play-fill'}"></i></button><a class="row-info" href="${SCOPE}track/${esc(id)}" data-link>${art?`<img class="row-art" src="${esc(art)}" loading="lazy" decoding="async" alt="">`:`<div class="row-art row-art-ph"><i class="bi bi-music-note"></i></div>`}<div class="min-w-0 flex-grow-1"><div class="row-title text-truncate">${esc(m.name||m.trackName||id)}</div><div class="row-sub text-truncate">${al} · ${fmtSize(e.size||0)}</div></div></a><button class="icon-btn sm text-primary" onclick="exportCached('${esc(id)}')"><i class="bi bi-file-earmark-arrow-down"></i></button><button class="icon-btn sm text-danger" onclick="removeCached('${esc(id)}')"><i class="bi bi-trash3"></i></button></div>`}).join('');
    html+=`</div>`
  } else {html+=`<div class="state" style="padding:20px 24px"><p style="font-size:.8rem">Nothing saved yet — start a download above.</p></div>`}
  wrap.innerHTML=html
}
window.dlScrollTo=function(sec){const el=document.getElementById('dl-sec-'+sec);if(el)el.scrollIntoView({behavior:'smooth',block:'start'})};
function dlBatchRowHtml(it){
  const ready=it.readyCount||0,total=it.trackCount||0,pct=it.percent||0;
  const act=['queued','crawling','saving'].includes(it.status);
  const rdy=it.status==='ready',fail=it.status==='failed';
  const lb={queued:'Queued',crawling:`Crawling · ${ready}/${total}`,saving:`Downloading · ${pct}%`,ready:`Ready · ${ready}/${total}`,completed:'Saved',failed:`Failed · ${ready}/${total}`}[it.status]||it.status;
  const lc={queued:'queued',crawling:'crawling',saving:'saving',ready:'ready',failed:'error'}[it.status]||'queued';
  const subTypeLabel=it.subType==='artist'?'Artist':'Album';
  const sub=[subTypeLabel,it.artist||'',`${total} track${total!==1?'s':''}`].filter(Boolean).join(' · ');
  const art=it.artwork||'';
  const viewTarget=it.subType==='artist'?`/artist/${esc(it.sourceId)}`:`/album/${esc(it.sourceId)}`;
  let ra='';
  if(act)ra=`<button class="pill-btn info sm" onclick="event.stopPropagation();go('${viewTarget}')"><i class="bi bi-eye"></i> View</button><button class="icon-btn sm text-danger" onclick="event.stopPropagation();DL.remove('${esc(it.trackId)}')" title="Cancel"><i class="bi bi-x-lg"></i></button>`;
  else if(rdy)ra=`<button class="pill-btn success sm" onclick="event.stopPropagation();saveBatch('${esc(it.trackId)}')"><i class="bi bi-download"></i> Save</button><button class="pill-btn info sm" onclick="event.stopPropagation();go('${viewTarget}')"><i class="bi bi-eye"></i> View</button><button class="icon-btn sm text-danger" onclick="event.stopPropagation();DL.remove('${esc(it.trackId)}')" title="Dismiss"><i class="bi bi-x-lg"></i></button>`;
  else if(fail)ra=`<button class="pill-btn warn sm" onclick="event.stopPropagation();go('${viewTarget}')"><i class="bi bi-eye"></i> View</button><button class="pill-btn danger sm" onclick="event.stopPropagation();DL.retry('${esc(it.trackId)}')"><i class="bi bi-arrow-repeat"></i></button><button class="icon-btn sm" onclick="event.stopPropagation();DL.remove('${esc(it.trackId)}')"><i class="bi bi-x-lg"></i></button>`;
  return`<div class="dl-row">${art?`<img class="row-art" src="${esc(art)}" loading="lazy" decoding="async" alt="">`:`<div class="row-art row-art-ph"><i class="bi bi-disc"></i></div>`}<div class="dl-body"><div class="d-flex align-items-center gap-2 mb-1"><div class="dl-title text-truncate flex-grow-1 min-w-0">${esc(it.name||'Batch')}</div><span class="dl-status ${lc}">${lb}</span></div><div class="dl-sub text-truncate">${esc(sub)}</div>${act||rdy?`<div class="dl-progress ${it.status==='saving'?'saving':(it.status==='ready'?'ready':'')}"><div style="width:${pct}%"></div></div>`:''}${fail&&it.error?`<div class="dl-sub text-danger text-truncate">${esc(it.error)}</div>`:''}</div>${ra}</div>`
}
window.saveBatch=async function(batchId){
  const batch=DL.get(batchId);
  if(!batch){return}
  toast('Saving '+batch.name+'…');
  DL.update(batchId,{status:'saving',percent:0});
  await DL._saveBatch(batch)
};
function dlRowHtml(it){
  const act=['queued','crawling','saving'].includes(it.status),rdy=it.status==='ready',fail=['failed','paused'].includes(it.status);
  const lb={queued:'Queued',crawling:'Crawling',saving:'Downloading',ready:'Ready',failed:'Failed',paused:'Paused'}[it.status]||it.status;
  const lc={queued:'queued',crawling:'crawling',saving:'saving',ready:'ready',failed:'error',paused:'error'}[it.status]||'queued';
  const pct=it.percent||0,pc=fail?'error':it.status==='saving'?'saving':it.status==='ready'?'ready':'';
  let ra='';
  if(act)ra=`<button class="icon-btn sm text-danger" onclick="DL.remove('${esc(it.trackId)}')"><i class="bi bi-x-lg"></i></button>`;
  else if(rdy)ra=`<button class="pill-btn success sm" onclick="DL.retry('${esc(it.trackId)}')"><i class="bi bi-download"></i> Save</button><button class="icon-btn sm text-danger" onclick="DL.remove('${esc(it.trackId)}')"><i class="bi bi-x-lg"></i></button>`;
  else if(fail)ra=`<button class="pill-btn danger sm" onclick="DL.retry('${esc(it.trackId)}')"><i class="bi bi-arrow-repeat"></i> Retry</button><button class="icon-btn sm" onclick="DL.remove('${esc(it.trackId)}')"><i class="bi bi-x-lg"></i></button>`;
  const si=it.status==='saving'&&it.totalBytes?`${fmtSize(it.bytes)} / ${fmtSize(it.totalBytes)}`:'';
  const al=artistLink(it,true);
  return`<div class="dl-row">${it.artwork?`<img class="row-art" src="${esc(it.artwork)}" loading="lazy" decoding="async" alt="">`:`<div class="row-art row-art-ph"><i class="bi bi-music-note"></i></div>`}<div class="dl-body"><div class="d-flex align-items-center gap-2 mb-1"><div class="dl-title text-truncate flex-grow-1 min-w-0">${esc(it.name)}</div><span class="dl-status ${lc}">${lb}</span></div><div class="dl-sub text-truncate">${al}${si?' · '+si:''}</div>${act||rdy?`<div class="dl-progress ${pc}"><div style="width:${pct}%"></div></div>`:''}${fail&&it.error?`<div class="dl-sub text-danger text-truncate">${esc(it.error)}</div>`:''}</div>${ra}</div>`
}

function openSettings(){sheet('#sheetSettings').show();updateSettingsSheet()}
async function updateSettingsSheet(){
  const s=getSettings();
  $$('#setTheme button').forEach(b=>b.classList.toggle('on',b.dataset.val===s.theme));
  const an=$('#setAnimations');if(an)an.checked=!!s.animations;
  const at=$('#setAutoTheme');if(at)at.checked=!!s.autoTheme;
  const abg=$('#setAppBg');if(abg)abg.checked=!!s.appBg;
  const asac=$('#setAutoSaveAfterCrawl');if(asac)asac.checked=!!s.autoSaveAfterCrawl;
  const asap=$('#setAutoSavePlayed');if(asap)asap.checked=!!s.autoSavePlayed;
  const asd=$('#setSaveData');if(asd)asd.checked=!!s.saveData;
  const cf=$('#setCrossfade');if(cf){const c=String(s.crossfade??0);cf.querySelectorAll('button').forEach(b=>b.classList.toggle('on',b.dataset.val===c))}
  const sf=$('#setSleepFade');if(sf)sf.checked=s.sleepFade!==false;
  const as=$('#setAutoScroll');if(as)as.checked=!!s.autoScrollLyrics;
  const ar=$('#setAutoRetry');if(ar)ar.checked=!!s.autoRetry;
  const acc=$('#accentRow');
  if(acc){
    const cur=s.accent||'purple';
    const autoBtn=`<button class="accent-dot auto ${cur==='auto'?'on':''}" data-accent="auto" aria-label="Auto"><i class="bi bi-magic"></i></button>`;
    const rest=Object.entries(ACCENTS).filter(([k,v])=>k!=='auto'&&v).map(([k,[a,b]])=>`<button class="accent-dot ${k===cur?'on':''}" data-accent="${k}" style="background:linear-gradient(135deg,${a},${b})" aria-label="${k}"></button>`).join('');
    acc.innerHTML=autoBtn+rest;
    if(!acc.__w){acc.__w=1;acc.addEventListener('click',e=>{const b=e.target.closest('[data-accent]');if(!b)return;const n=b.dataset.accent;setSetting('accent',n);applySettings();acc.querySelectorAll('.accent-dot').forEach(d=>d.classList.toggle('on',d===b));haptic(8)})}
  }
  const bh=$('#backupHost');
  if(bh&&!bh.__w){bh.__w=1;bh.innerHTML=`<div class="settings-row"><i class="bi bi-box-arrow-up"></i><div class="sr-body"><div class="sr-title">Export backup</div><div class="sr-sub">Download likes, playlists & settings</div></div><div class="sr-control"><button class="pill-btn primary sm" onclick="exportData()"><i class="bi bi-download"></i> Export</button></div></div><div class="settings-row"><i class="bi bi-box-arrow-in-down"></i><div class="sr-body"><div class="sr-title">Import backup</div><div class="sr-sub">Restore from a JSON file</div></div><div class="sr-control"><button class="pill-btn sm" onclick="importData()"><i class="bi bi-upload"></i> Import</button></div></div>`}
  try{const all=await cacheAll(),total=all.reduce((s,e)=>s+(e.size||0),0),ci=$('#setCacheInfo');if(ci)ci.textContent=all.length?`${all.length} tracks · ${fmtSize(total)}`:'Empty'}catch{}
  try{const est=await getStorageEstimate();const qi=$('#setQuotaInfo');if(qi){if(est&&est.quota){const pct=Math.round(est.usage/est.quota*100);qi.textContent=`${fmtSize(est.usage)} of ${fmtSize(est.quota)} (${pct}%)`}else qi.textContent='Not supported'}}catch{}
  const ri=$('#setRecentInfo');if(ri){const n=ls.get(KEY.recent,[]).length;ri.textContent=n?`${n} item${n!==1?'s':''}`:'Empty'}
}
$('#setTheme')?.addEventListener('click',e=>{const b=e.target.closest('button[data-val]');if(!b)return;setSetting('theme',b.dataset.val);updateSettingsSheet()});
$('#setAnimations')?.addEventListener('change',e=>setSetting('animations',e.target.checked));
$('#setAutoTheme')?.addEventListener('change',e=>{setSetting('autoTheme',e.target.checked);autoTheme()});
$('#setAppBg')?.addEventListener('change',e=>setSetting('appBg',e.target.checked));
$('#setAutoSaveAfterCrawl')?.addEventListener('change',e=>setSetting('autoSaveAfterCrawl',e.target.checked));
$('#setAutoSavePlayed')?.addEventListener('change',e=>{setSetting('autoSavePlayed',e.target.checked);toast(e.target.checked?'Auto-save played tracks on':'Auto-save played tracks off')});
$('#setSaveData')?.addEventListener('change',e=>{setSetting('saveData',e.target.checked);toast(e.target.checked?'Save-data mode on':'Save-data mode off')});
$('#setCrossfade')?.addEventListener('click',e=>{const b=e.target.closest('button[data-val]');if(!b)return;setSetting('crossfade',Number(b.dataset.val));e.currentTarget.querySelectorAll('button').forEach(z=>z.classList.toggle('on',z===b))});
$('#setSleepFade')?.addEventListener('change',e=>setSetting('sleepFade',e.target.checked));
$('#setAutoScroll')?.addEventListener('change',e=>setSetting('autoScrollLyrics',e.target.checked));
$('#setAutoRetry')?.addEventListener('change',e=>setSetting('autoRetry',e.target.checked));
async function resetAppData(){const ok=await askConfirm('Reset all data?','Likes, playlists, downloads, settings and recents will be erased.','Reset everything');if(!ok)return;Object.values(KEY).forEach(k=>ls.remove(k));try{await cacheClear()}catch{}cachedIds.clear();toast('All data reset');sheet('#sheetSettings').hide();applySettings();go('/')}
async function checkForUpdate(){try{if('serviceWorker'in navigator){const rs=await navigator.serviceWorker.getRegistrations();for(const r of rs)await r.update();toast('Checking for updates…','success')}else toast('Service worker not supported','warning')}catch{toast('Update check failed','danger')}}
function updateOnlineBanner(){const b=$('#offlineBanner');if(!b)return;b.classList.toggle('on',!navigator.onLine);const p=$('#netPill');if(p)p.classList.toggle('off',!navigator.onLine)}
window.addEventListener('online',updateOnlineBanner);window.addEventListener('offline',updateOnlineBanner);

let deferredInstall=null;
function isStandalone(){return window.matchMedia?.('(display-mode: standalone)').matches||window.matchMedia?.('(display-mode: fullscreen)').matches||window.navigator.standalone===true}
function showInstallButton(){if(!isStandalone()){const b=$('#installBtn');if(b)b.style.display='';const sb=$('#sbInstall');if(sb)sb.style.display=''}}
function hideInstallButton(){const b=$('#installBtn');if(b)b.style.display='none';const sb=$('#sbInstall');if(sb)sb.style.display='none'}
window.addEventListener('beforeinstallprompt',e=>{e.preventDefault();deferredInstall=e;showInstallButton()});
window.addEventListener('appinstalled',()=>{deferredInstall=null;hideInstallButton();toast('MusicMan installed','success')});
async function promptInstall(){if(deferredInstall){try{deferredInstall.prompt();await deferredInstall.userChoice}catch{}deferredInstall=null;hideInstallButton();return}
  const ua=navigator.userAgent||'',isIOS=/iphone|ipad|ipod/i.test(ua)||(navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1),body=$('#installBody');
  if(isIOS)body.innerHTML=`<div class="install-step"><div class="n">1</div><div class="t">Open in <b>Safari</b>.</div></div><div class="install-step"><div class="n">2</div><div class="t">Tap <b>Share</b> <i class="bi bi-box-arrow-up"></i>.</div></div><div class="install-step"><div class="n">3</div><div class="t">Tap <b>"Add to Home Screen"</b>.</div></div><div class="install-step"><div class="n">4</div><div class="t">Confirm and launch from your home screen.</div></div>`;
  else body.innerHTML=`<div class="install-step"><div class="n">1</div><div class="t">Open browser menu (<i class="bi bi-three-dots-vertical"></i>).</div></div><div class="install-step"><div class="n">2</div><div class="t">Choose <b>"Install app"</b> or <b>"Add to Home screen"</b>.</div></div><div class="install-step"><div class="n">3</div><div class="t">Confirm to install.</div></div><p class="text-secondary mt-2" style="font-size:.76rem">If not visible, ensure HTTPS and reload once.</p>`;
  sheet('#sheetInstall').show()}
function initInstallButton(){if(isStandalone())return;const ua=navigator.userAgent||'',isIOS=/iphone|ipad|ipod/i.test(ua)||(navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1);if(isIOS)showInstallButton()}

function openShortcuts(){const grid=$('#shortcutGrid');if(!grid)return;const rows=[['Space','Play / pause'],['→ / ←','Seek ±5s'],['N','Next track'],['P','Previous track'],['S','Toggle shuffle'],['R','Cycle repeat'],['L','Like current'],['A','A-B repeat'],['J','Toggle full player (mobile)'],['/','Focus search'],['?','This help'],['Esc','Close overlays']];grid.innerHTML=rows.map(([k,d])=>`<div class="shortcut-row"><kbd>${k}</kbd><span>${d}</span></div>`).join('');$('#shortcutOverlay').classList.add('on')}
function closeShortcuts(){const el=$('#shortcutOverlay');if(el)el.classList.remove('on')}
document.addEventListener('keydown',e=>{
  if(e.ctrlKey||e.metaKey||e.altKey)return;
  const t=e.target.tagName;
  if(t==='INPUT'||t==='TEXTAREA'){if(e.key==='Escape')e.target.blur();return}
  if(e.key==='?'){openShortcuts();return}
  if(e.code==='Space'){e.preventDefault();togglePlay();return}
  if(e.key==='ArrowRight'&&audio.duration&&isFinite(audio.duration))audio.currentTime=Math.min(audio.currentTime+5,audio.duration);
  if(e.key==='ArrowLeft'&&audio.duration&&isFinite(audio.duration))audio.currentTime=Math.max(audio.currentTime-5,0);
  if(e.key==='n'||e.key==='N')nextTrack();
  if(e.key==='p'||e.key==='P')prevTrack();
  if(e.key==='s'||e.key==='S')toggleShuffle();
  if(e.key==='r'||e.key==='R')cycleRepeat();
  if(e.key==='l'||e.key==='L')toggleLikeCurrent();
  if(e.key==='a'||e.key==='A')abToggle();
  if(e.key==='j'||e.key==='J'){if(IS_DESKTOP())return;if($('#full')?.classList.contains('show'))closeFullPlayer();else openFullPlayer()}
  if(e.key==='/'){e.preventDefault();$('#searchInput')?.focus()}
  if(e.key==='Escape'){closeShortcuts();if($('#full')?.classList.contains('show'))closeFullPlayer()}
});

if('serviceWorker' in navigator){
  window.addEventListener('load',()=>{
    const hadController=!!navigator.serviceWorker.controller;
    navigator.serviceWorker.register(SCOPE+'sw.js',{scope:SCOPE}).then(reg=>{
      const checkUpdate=()=>{try{reg.update().catch(()=>{})}catch{}};
      setTimeout(checkUpdate,3000);setInterval(checkUpdate,60*60*1000)
    }).catch(()=>{});
    let refreshing=false;
    navigator.serviceWorker.addEventListener('controllerchange',()=>{if(refreshing)return;refreshing=true;if(hadController){try{toast('Updating to latest version…','info')}catch{};setTimeout(()=>{try{location.reload()}catch{}},700)}})
  })
}

function syncSidebarNav(){const path=currentPath();document.querySelectorAll('.sb-link').forEach(el=>el.classList.remove('active'));const setA=sel=>{const el=document.querySelector(sel);if(el)el.classList.add('active')};
  if(path==='/')setA('.sb-link[data-nav="home"]');
  else if(path.startsWith('/search'))setA('.sb-link[data-nav="search"]');
  else if(path==='/library/likes')setA('.sb-link[data-lib="likes"]');
  else if(path==='/library/following')setA('.sb-link[data-lib="following"]');
  else if(path.startsWith('/library/playlists'))setA('.sb-link[data-lib="playlists"]');
  else if(path==='/library/downloads')setA('.sb-link[data-lib="downloads"]');
  else if(path==='/library/stats')setA('.sb-link[data-lib="stats"]');
  else if(path==='/library/history')setA('.sb-link[data-lib="history"]')}
function renderSidebarPlaylists(){const el=document.getElementById('sbPlaylists');if(!el)return;const pls=getPlaylists();
  if(!pls.length){el.innerHTML='<div style="padding:6px 14px;font-size:.76rem;color:var(--bs-secondary-color)">No playlists yet</div>';return}
  const cur=currentPath();
  el.innerHTML=pls.map(p=>{const active=cur==='/library/playlists/'+p.id?' active':'';const ic=p.isPublic?'bi-globe':'bi-music-note-list';return`<a href="${SCOPE}library/playlists/${esc(p.id)}" data-link class="sb-link${active}"><i class="bi ${ic}"></i><span>${esc(p.name)}</span></a>`}).join('')}
function syncPlayerBar(){if(!IS_DESKTOP())return;const t=Player.track;
  const pbArt=document.getElementById('pbArt'),pbTitle=document.getElementById('pbTitle'),pbArtist=document.getElementById('pbArtist'),pbLikeBtn=document.getElementById('pbLikeBtn'),pbLike=document.getElementById('pbLike'),pbPlayIcon=document.getElementById('pbPlayIcon'),pbShuffleBtn=document.getElementById('pbShuffleBtn'),pbRepeatBtn=document.getElementById('pbRepeatBtn'),pbDur=document.getElementById('pbDur');
  if(!t){if(pbArt){pbArt.removeAttribute('src');pbArt.style.opacity='.25'}if(pbTitle)pbTitle.textContent='—';if(pbArtist)pbArtist.textContent='—';if(pbLike)pbLike.className='bi bi-heart';if(pbLikeBtn)pbLikeBtn.classList.remove('liked');if(pbPlayIcon)pbPlayIcon.className='bi bi-play-fill';return}
  const art=getArtwork(t,300)||t.artworkUrl||'';
  if(pbArt){if(art){pbArt.src=art;pbArt.style.opacity='1'}else{pbArt.removeAttribute('src');pbArt.style.opacity='.25'}}
  if(pbTitle)pbTitle.innerHTML=`<a class="mm-link" href="${SCOPE}track/${esc(t.trackId)}" data-link style="color:inherit">${esc(t.trackName||'')}</a>`;
  if(pbArtist){if(t.artistId)pbArtist.innerHTML=`<a class="mm-link" href="${SCOPE}artist/${esc(t.artistId)}" data-link style="color:inherit">${esc(t.artistName||'')}</a>`;else pbArtist.textContent=t.artistName||''}
  const liked=isLiked(t.trackId);if(pbLike)pbLike.className=`bi ${liked?'bi-heart-fill':'bi-heart'}`;if(pbLikeBtn)pbLikeBtn.classList.toggle('liked',liked);
  if(pbPlayIcon)pbPlayIcon.className=`bi ${Player.playing?'bi-pause-fill':'bi-play-fill'}`;
  if(pbShuffleBtn)pbShuffleBtn.classList.toggle('on',Player.shuffle);
  if(pbRepeatBtn){pbRepeatBtn.classList.toggle('on',Player.repeat!=='off');const i=pbRepeatBtn.querySelector('i');if(i)i.className=`bi ${Player.repeat==='one'?'bi-repeat-1':'bi-repeat'}`}
  if(pbDur&&audio.duration&&isFinite(audio.duration))pbDur.textContent=fmtTime(audio.duration)}
function updateDesktopSeek(){if(!IS_DESKTOP())return;const seek=document.getElementById('pbSeek');if(!seek||Player.seeking)return;if(!audio.duration||!isFinite(audio.duration))return;const pct=(audio.currentTime/audio.duration)*100;seek.value=Math.round(pct*10);seek.style.setProperty('--p',pct+'%');const cur=document.getElementById('pbCur');if(cur)cur.textContent=fmtTime(audio.currentTime);const dur=document.getElementById('pbDur');if(dur)dur.textContent=fmtTime(audio.duration)}
function setRpMode(mode){rpMode=mode;$$('#rpTabs .rp-tab').forEach(b=>b.classList.toggle('active',b.dataset.rpTab===mode));updateRightPanel()}
(function initDesktopPlayerBar(){
  const seek=document.getElementById('pbSeek'),wrap=document.getElementById('pbSeekWrap'),tip=document.getElementById('pbTip');
  if(seek){seek.addEventListener('pointerdown',()=>{Player.seeking=true});seek.addEventListener('pointerup',()=>{Player.seeking=false});seek.addEventListener('input',e=>{const p=Number(e.target.value)/10;e.target.style.setProperty('--p',p+'%');if(audio.duration&&isFinite(audio.duration)){const c=document.getElementById('pbCur');if(c)c.textContent=fmtTime((p/100)*audio.duration)}});seek.addEventListener('change',e=>{if(audio.duration&&isFinite(audio.duration))audio.currentTime=(Number(e.target.value)/1000)*audio.duration;Player.seeking=false})}
  if(wrap&&tip){wrap.addEventListener('pointermove',e=>{if(!audio.duration||!isFinite(audio.duration))return;const r=wrap.getBoundingClientRect();const x=Math.max(0,Math.min(1,(e.clientX-r.left)/r.width));tip.textContent=fmtTime(x*audio.duration);tip.style.left=(x*100)+'%';tip.classList.add('on')});wrap.addEventListener('pointerleave',()=>tip.classList.remove('on'))}
  const vol=document.getElementById('pbVol');
  if(vol){vol.value=Math.round(audio.volume*100);vol.style.setProperty('--p',vol.value+'%');vol.addEventListener('input',e=>{const v=Number(e.target.value)/100;audio.volume=v;ls.set(KEY.vol,v);e.target.style.setProperty('--p',e.target.value+'%')})}
  $$('#rpTabs .rp-tab').forEach(b=>b.addEventListener('click',()=>setRpMode(b.dataset.rpTab)));
  setRpMode('now');renderSidebarPlaylists();syncPlayerBar();syncSidebarNav()
})();
window.addEventListener('resize',()=>{syncPlayerBar()});

(async function boot(){
  applySettings();updateOnlineBanner();initInstallButton();autoTheme();
  if(window.__INITIAL_DATA__&&itemId(window.__INITIAL_DATA__))cacheItems([window.__INITIAL_DATA__]);
  DL.load();resumeDownloadPolling();
  DL.onChange(()=>{updateDownloadBadges();syncSaveButton();if(isTrackRoute()){const id=currentPath().split('/')[2],dl=DL.get(id),it=getCached('track',id);if(it&&dl)renderCrawlCard(it,null)}if(isLibDl())updateDlLists()});
  updateDownloadBadges();
  try{await refreshCacheIndex()}catch{}
  try{await restoreQueue()}catch{}
  if(!location.pathname||location.pathname===BASE){try{history.replaceState(null,'',SCOPE)}catch{}}
  await route();refreshLikes();refreshFollowButtons();updateMediaSession();renderSidebarPlaylists();
  setInterval(()=>{if(!document.hidden&&Player.track)updateMediaSession()},5000);
})();
window.addEventListener('error',e=>console.error('[MusicMan]',e.error||e.message));

(function(){
  const THRESH=80,VEL_THRESH=.5;
  function attach(el){
    if(!el||el.__sd)return;el.__sd=1;
    let sy=0,dy=0,st=0,active=false;
    el.addEventListener('touchstart',e=>{if(e.touches.length!==1)return;if(e.target.closest('input,textarea,select,[data-no-swipe]'))return;sy=e.touches[0].clientY;dy=0;st=performance.now();active=true;el.style.transition='none'},{passive:true});
    el.addEventListener('touchmove',e=>{if(!active)return;const delta=e.touches[0].clientY-sy;if(delta<=0){if(dy!==0){dy=0;el.style.transform='';el.style.opacity=''}return}if(delta<6)return;const scroller=e.target.closest('[data-scroll],.sheet-scroll,div[style*="overflow-y"]');if(scroller){const atTop=scroller.scrollTop<=0;if(!atTop){active=false;el.style.transition='';el.style.transform='';el.style.opacity='';dy=0;return}}dy=delta;const eff=dy<=THRESH?dy:THRESH+(dy-THRESH)*.35;el.style.transform=`translateY(${eff}px)`;el.style.opacity=String(Math.max(.35,1-eff/520))},{passive:true});
    el.addEventListener('touchend',()=>{if(!active)return;active=false;const dt=Math.max(1,performance.now()-st);const vel=dy/dt;const shouldClose=dy>THRESH||vel>VEL_THRESH;el.style.transition='transform .25s var(--mm-ease),opacity .25s';el.style.transform='';el.style.opacity='';if(shouldClose){try{bootstrap.Offcanvas.getInstance(el)?.hide()}catch{}haptic(8)}setTimeout(()=>{el.style.transition='';dy=0},280)},{passive:true});
    el.addEventListener('touchcancel',()=>{active=false;el.style.transition='transform .2s';el.style.transform='';el.style.opacity='';dy=0;setTimeout(()=>{el.style.transition=''},220)},{passive:true})
  }
  const scan=()=>document.querySelectorAll('.offcanvas.mm-sheet').forEach(attach);
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',scan);else scan();
  document.addEventListener('show.bs.offcanvas',e=>{if(e.target.classList&&e.target.classList.contains('mm-sheet'))setTimeout(()=>attach(e.target),20)})
})();
</script>
</body>
</html>
