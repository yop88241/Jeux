<?php
declare(strict_types=1);

/* ============ Configuration ============ */
$siteTitle   = 'Mes projets';
$hidden      = ['index.php', '.htaccess', '.htpasswd', '.git', '.gitignore', '.env',
                'cgi-bin', 'node_modules', 'error_log', 'vendor'];
$showDotFiles = false;  // afficher les fichiers qui commencent par un point
$openIfIndex  = true;   // un dossier qui contient index.html / index.php s'ouvre directement

/* ============ Résolution du dossier courant ============ */
$base = realpath(__DIR__);
$rel  = isset($_GET['d']) ? trim(str_replace('\\', '/', (string)$_GET['d']), '/') : '';
$cur  = $base;

if ($rel !== '' && strpos($rel, "\0") === false) {
    $ok = true;
    foreach (explode('/', $rel) as $seg) {
        if ($seg === '' || $seg === '.' || $seg === '..' || in_array($seg, $hidden, true)) { $ok = false; break; }
    }
    $real = $ok ? realpath($base . '/' . $rel) : false;
    if ($real !== false && is_dir($real) && strpos($real . DIRECTORY_SEPARATOR, $base . DIRECTORY_SEPARATOR) === 0) {
        $cur = $real;
    } else {
        $rel = '';
    }
} else {
    $rel = '';
}

/* ============ Fonctions utilitaires ============ */
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function pathUrl(string $path): string {
    return implode('/', array_map('rawurlencode', explode('/', $path)));
}

function humanSize(int $b): string {
    $u = ['o', 'Ko', 'Mo', 'Go', 'To'];
    $i = 0;
    while ($b >= 1024 && $i < count($u) - 1) { $b /= 1024; $i++; }
    return ($i === 0 ? (string)$b : number_format($b, 1, ',', ' ')) . ' ' . $u[$i];
}

function category(string $ext): string {
    static $map = [
        'img'  => ['jpg','jpeg','png','gif','webp','svg','avif','bmp','ico'],
        'doc'  => ['pdf','doc','docx','odt','txt','md','rtf'],
        'data' => ['xls','xlsx','csv','ods','json','xml','sql'],
        'zip'  => ['zip','rar','7z','tar','gz'],
        'code' => ['html','htm','css','js','php','py','ts','java','c','cpp'],
        'audio'=> ['mp3','wav','ogg','flac','m4a'],
        'video'=> ['mp4','webm','mov','avi','mkv'],
    ];
    foreach ($map as $cat => $exts) { if (in_array($ext, $exts, true)) return $cat; }
    return 'other';
}

/* ============ Lecture du dossier ============ */
$items = [];
foreach (scandir($cur) ?: [] as $name) {
    if ($name === '.' || $name === '..' || in_array($name, $hidden, true)) continue;
    if (!$showDotFiles && $name[0] === '.') continue;

    $full  = $cur . '/' . $name;
    $path  = ($rel === '' ? '' : $rel . '/') . $name;
    $isDir = is_dir($full);
    $item  = ['name' => $name, 'isDir' => $isDir, 'mtime' => (int)@filemtime($full)];

    if ($isDir) {
        $direct = $openIfIndex && (is_file($full . '/index.html') || is_file($full . '/index.php'));
        $item['href']   = $direct ? pathUrl($path) . '/' : '?d=' . rawurlencode($path);
        $item['direct'] = $direct;
        $item['count']  = count(array_filter(scandir($full) ?: [], fn($n) => $n !== '.' && $n !== '..'
                                  && !in_array($n, $hidden, true) && ($showDotFiles || $n[0] !== '.')));
    } else {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $item += ['href' => pathUrl($path), 'ext' => $ext, 'cat' => category($ext),
                  'size' => (int)@filesize($full)];
    }
    $items[] = $item;
}
usort($items, fn($a, $b) => ($b['isDir'] <=> $a['isDir']) ?: strnatcasecmp($a['name'], $b['name']));

/* Fil d'Ariane */
$crumbs = [['Accueil', '?']];
if ($rel !== '') {
    $acc = '';
    foreach (explode('/', $rel) as $seg) {
        $acc .= ($acc === '' ? '' : '/') . $seg;
        $crumbs[] = [$seg, '?d=' . rawurlencode($acc)];
    }
}
$pageTitle = $rel === '' ? $siteTitle : basename($rel) . ' – ' . $siteTitle;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle) ?></title>
<style>
:root {
  --bg: #eef1f4; --surface: #ffffff; --ink: #1d2733; --muted: #66727f; --line: #d9dfe5;
  --accent: #0f766e;
  --f-back: #e9a825; --f-front: #fbc94a; --f-edge: #c98a0f;
  --c-img: #2f9e6b; --c-doc: #3b6fd4; --c-data: #1f8a4c; --c-zip: #d9772b;
  --c-code: #7a5ad6; --c-audio: #cc4b8a; --c-video: #cf4343; --c-other: #7b8794;
}
@media (prefers-color-scheme: dark) {
  :root {
    --bg: #14181d; --surface: #1d232a; --ink: #e8edf2; --muted: #93a0ad; --line: #2e3640;
    --accent: #4fd1c1;
    --f-back: #c58a14; --f-front: #e9b83c; --f-edge: #9a6a0a;
  }
}
* { box-sizing: border-box; }
html { background: var(--bg); }
body {
  margin: 0; color: var(--ink);
  font-family: ui-rounded, "SF Pro Rounded", "Segoe UI", system-ui, sans-serif;
  line-height: 1.5;
}
main { max-width: 1080px; margin: 0 auto; padding: 2.5rem 1.25rem 4rem; }
h1 { font-size: clamp(1.6rem, 4vw, 2.2rem); margin: 0 0 .4rem; letter-spacing: -.01em; }
.crumbs { display: flex; flex-wrap: wrap; gap: .35rem; margin: 0 0 2rem; font-size: .95rem; color: var(--muted); }
.crumbs a { color: var(--accent); text-decoration: none; }
.crumbs a:hover { text-decoration: underline; }
.crumbs span.sep { opacity: .6; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 1rem; }
.tile {
  display: flex; flex-direction: column; align-items: center; text-align: center;
  padding: 1.1rem .75rem .9rem; background: var(--surface); border: 1px solid var(--line);
  border-radius: 14px; color: inherit; text-decoration: none; min-width: 0;
}
.tile:hover { border-color: var(--accent); }
.tile:focus-visible { outline: 3px solid var(--accent); outline-offset: 2px; }
.icon { width: 84px; height: 84px; margin-bottom: .6rem; display: grid; place-items: center; }
.icon svg, .icon img { width: 100%; height: 100%; }
.icon img { object-fit: cover; border-radius: 8px; }
.name { font-weight: 600; font-size: .95rem; max-width: 100%; overflow-wrap: anywhere;
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.meta { font-size: .8rem; color: var(--muted); margin-top: .15rem; }

/* Dossier : le rabat avant s'ouvre au survol */
.folder .flap { transform-origin: 6px 70px; transition: transform .18s ease-out; }
.tile:hover .flap, .tile:focus-visible .flap { transform: skewX(-6deg) scaleY(.94); }

.empty { padding: 3rem 1rem; text-align: center; color: var(--muted);
         border: 2px dashed var(--line); border-radius: 14px; }
footer { margin-top: 2.5rem; font-size: .85rem; color: var(--muted); }
@media (prefers-reduced-motion: reduce) { .folder .flap { transition: none; } }
</style>
</head>
<body>
<main>
  <h1><?= h($rel === '' ? $siteTitle : basename($rel)) ?></h1>
  <nav class="crumbs" aria-label="Fil d'Ariane">
    <?php foreach ($crumbs as $i => [$label, $url]): ?>
      <?php if ($i > 0): ?><span class="sep">/</span><?php endif; ?>
      <?php if ($i === count($crumbs) - 1): ?>
        <span aria-current="page"><?= h($label) ?></span>
      <?php else: ?>
        <a href="<?= h($url) ?>"><?= h($label) ?></a>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>

  <?php if (!$items): ?>
    <div class="empty">Ce dossier est vide.</div>
  <?php else: ?>
  <div class="grid">
    <?php foreach ($items as $it): ?>
      <a class="tile<?= $it['isDir'] ? ' folder' : '' ?>" href="<?= h($it['href']) ?>">
        <div class="icon">
        <?php if ($it['isDir']): ?>
          <svg viewBox="0 0 96 96" aria-hidden="true">
            <path d="M6 22a6 6 0 0 1 6-6h20l8 9h44a6 6 0 0 1 6 6v43a6 6 0 0 1-6 6H12a6 6 0 0 1-6-6z" fill="var(--f-back)"/>
            <rect x="12" y="30" width="72" height="14" rx="3" fill="#fff" opacity=".85"/>
            <g class="flap">
              <path d="M6 40a6 6 0 0 1 6-6h72a6 6 0 0 1 6 6v36a6 6 0 0 1-6 6H12a6 6 0 0 1-6-6z" fill="var(--f-front)"/>
              <path d="M6 76a6 6 0 0 0 6 6h72a6 6 0 0 0 6-6v-3H6z" fill="var(--f-edge)" opacity=".35"/>
            </g>
          </svg>
        <?php elseif ($it['cat'] === 'img' && $it['ext'] !== 'ico'): ?>
          <img src="<?= h($it['href']) ?>" alt="" loading="lazy">
        <?php else: ?>
          <svg viewBox="0 0 96 96" aria-hidden="true">
            <path d="M22 8h36l20 20v52a8 8 0 0 1-8 8H22a8 8 0 0 1-8-8V16a8 8 0 0 1 8-8z" fill="var(--c-<?= h($it['cat']) ?>)"/>
            <path d="M58 8l20 20H66a8 8 0 0 1-8-8z" fill="#fff" opacity=".45"/>
            <text x="46" y="66" text-anchor="middle" font-size="<?= strlen($it['ext']) > 3 ? 15 : 19 ?>"
                  font-weight="700" fill="#fff" font-family="system-ui, sans-serif"><?= h(strtolower(substr($it['ext'], 0, 4)) ?: 'fic') ?></text>
          </svg>
        <?php endif; ?>
        </div>
        <span class="name"><?= h($it['name']) ?></span>
        <span class="meta">
          <?php if ($it['isDir']): ?>
            <?= $it['direct'] ? 'Ouvrir' : $it['count'] . ($it['count'] > 1 ? ' éléments' : ' élément') ?>
          <?php else: ?>
            <?= h(humanSize($it['size'])) ?>
          <?php endif; ?>
        </span>
      </a>
    <?php endforeach; ?>
  </div>
  <footer><?= count($items) ?> <?= count($items) > 1 ? 'éléments' : 'élément' ?></footer>
  <?php endif; ?>
</main>
</body>
</html>