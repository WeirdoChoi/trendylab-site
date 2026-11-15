<?php
// Shared helpers for the inquiry endpoints. No secrets live in this repository:
// the recipient address and other settings are kept in _tl/config.php on the server only.
function tl_dir() {
    $d = dirname(__DIR__) . '/_tl';
    if (!is_dir($d)) { @mkdir($d, 0755); }
    $h = $d . '/.htaccess';
    if (!is_file($h)) {
        @file_put_contents($h, "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n");
    }
    return $d;
}
function tl_cfg() {
    $f = tl_dir() . '/config.php';
    $c = is_file($f) ? include $f : array();
    return is_array($c) ? $c : array();
}
function tl_save_cfg($c) {
    return file_put_contents(tl_dir() . '/config.php', "<?php\nreturn " . var_export($c, true) . ";\n", LOCK_EX) !== false;
}
function tl_out($code, $a) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($a, JSON_UNESCAPED_UNICODE);
    exit;
}
function tl_in() {
    $in = json_decode(file_get_contents('php://input'), true);
    return is_array($in) ? $in : array();
}
function tl_str($in, $k, $max, $multi = false) {
    $v = (isset($in[$k]) && is_string($in[$k])) ? $in[$k] : '';
    $v = str_replace(array("\r", "\0"), '', $v);
    if (!$multi) { $v = str_replace("\n", ' ', $v); }
    return mb_substr(trim($v), 0, $max, 'UTF-8');
}
