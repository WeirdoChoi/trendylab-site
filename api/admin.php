<?php
// Admin API for the inquiry list. Password hash lives in _tl/config.php on the server only.
require __DIR__ . '/_lib.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { tl_out(405, array('ok' => false)); }
if (!isset($_SERVER['HTTP_X_TL']) || stripos(isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '', 'application/json') !== 0) { tl_out(400, array('ok' => false)); }
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_name('tlad');
session_set_cookie_params(array('lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict'));
session_start();
$in = tl_in();
$a = isset($in['a']) && is_string($in['a']) ? $in['a'] : '';
$cfg = tl_cfg();
$has = !empty($cfg['admin_hash']);
$authed = $has && !empty($_SESSION['ok']) && isset($_SESSION['h']) && hash_equals(md5($cfg['admin_hash']), $_SESSION['h']);
$dir = tl_dir();
$ip = md5(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');

function tl_fails($dir, $ip, $add) {
    $f = $dir . '/lock.json';
    $d = is_file($f) ? (json_decode(file_get_contents($f), true) ?: array()) : array();
    $now = time();
    foreach ($d as $k => $ts) { $d[$k] = array_values(array_filter($ts, function ($x) use ($now) { return $now - $x < 600; })); if (!$d[$k]) { unset($d[$k]); } }
    if ($add === 1) { $d[$ip][] = $now; }
    if ($add === -1) { unset($d[$ip]); }
    if ($add) { @file_put_contents($f, json_encode($d), LOCK_EX); }
    return isset($d[$ip]) ? count($d[$ip]) : 0;
}
function tl_login($cfg) { session_regenerate_id(true); $_SESSION['ok'] = 1; $_SESSION['h'] = md5($cfg['admin_hash']); }
function tl_rows($fp) {
    rewind($fp); $rows = array(); $first = true;
    while (($l = fgets($fp)) !== false) { if ($first) { $first = false; continue; } $r = json_decode($l, true); if (is_array($r) && isset($r['id'])) { $rows[] = $r; } }
    return $rows;
}

if ($a === 'status') { tl_out(200, array('ok' => true, 'set' => $has, 'in' => $authed)); }
if ($a === 'setpw') { // first time only
    if ($has) { tl_out(409, array('ok' => false, 'error' => 'already_set')); }
    $p = isset($in['p']) && is_string($in['p']) ? $in['p'] : '';
    if (strlen($p) < 8 || strlen($p) > 200) { tl_out(422, array('ok' => false, 'error' => 'short')); }
    $cfg['admin_hash'] = password_hash($p, PASSWORD_DEFAULT);
    if (!tl_save_cfg($cfg)) { tl_out(500, array('ok' => false)); }
    tl_login($cfg);
    tl_out(200, array('ok' => true));
}
if ($a === 'login') {
    if (!$has) { tl_out(409, array('ok' => false, 'error' => 'not_set')); }
    if (tl_fails($dir, $ip, 0) >= 5) { tl_out(429, array('ok' => false, 'error' => 'locked')); }
    $p = isset($in['p']) && is_string($in['p']) ? $in['p'] : '';
    if (!password_verify($p, $cfg['admin_hash'])) { $n = tl_fails($dir, $ip, 1); tl_out(401, array('ok' => false, 'error' => $n >= 5 ? 'locked' : 'wrong')); }
    tl_fails($dir, $ip, -1);
    tl_login($cfg);
    tl_out(200, array('ok' => true));
}
if ($a === 'logout') { $_SESSION = array(); session_destroy(); tl_out(200, array('ok' => true)); }
if (!$authed) { tl_out(401, array('ok' => false, 'error' => 'auth')); }

$store = $dir . '/inquiries.php';
if (!is_file($store)) { file_put_contents($store, "<?php exit; ?>\n", LOCK_EX); }
$fp = fopen($store, 'c+');
if (!$fp || !flock($fp, LOCK_EX)) { tl_out(500, array('ok' => false)); }
$rows = tl_rows($fp);
if ($a === 'set' || $a === 'del') {
    $id = isset($in['id']) && is_string($in['id']) ? $in['id'] : '';
    $st = isset($in['st']) && in_array($in['st'], array('new', 'ing', 'done'), true) ? $in['st'] : '';
    $out = array(); $hit = false;
    foreach ($rows as $r) {
        if ($r['id'] === $id) { $hit = true; if ($a === 'del') { continue; } if ($st !== '') { $r['st'] = $st; } }
        $out[] = $r;
    }
    if (!$hit || ($a === 'set' && $st === '')) { flock($fp, LOCK_UN); tl_out(404, array('ok' => false)); }
    $buf = "<?php exit; ?>\n";
    foreach ($out as $r) { $buf .= json_encode($r, JSON_UNESCAPED_UNICODE) . "\n"; }
    ftruncate($fp, 0); rewind($fp); fwrite($fp, $buf); fflush($fp);
    $rows = $out;
} elseif ($a !== 'list') { flock($fp, LOCK_UN); tl_out(400, array('ok' => false)); }
flock($fp, LOCK_UN); fclose($fp);
$n = 0; $list = array();
foreach ($rows as $r) { $r['no'] = ++$n; unset($r['ip']); $list[] = $r; }
tl_out(200, array('ok' => true, 'rows' => array_reverse($list)));
