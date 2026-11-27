<?php
// Read-only visitor summary from the cafe24 web logs (/cafe24_log in the hosting home folder).
// Visible only after the admin login (same session as the inquiry list at /#admin).
require __DIR__ . '/_lib.php';
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_name('tlad');
session_set_cookie_params(array('lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict'));
session_start();
$cfg = tl_cfg();
$authed = !empty($cfg['admin_hash']) && !empty($_SESSION['ok']) && isset($_SESSION['h']) && hash_equals(md5($cfg['admin_hash']), $_SESSION['h']);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$css = '<style>body{font-family:Pretendard,-apple-system,"Malgun Gothic",sans-serif;margin:0;padding:32px 20px;color:#111;background:#fff}main{max-width:880px;margin:0 auto}h1{font-size:24px;margin:0 0 6px}p.s{color:#666;margin:0 0 24px;font-size:14px;line-height:1.6}table{width:100%;border-collapse:collapse;margin:0 0 32px;font-size:14px}th,td{padding:10px 12px;border-bottom:1px solid #e5e5e5;text-align:right}th:first-child,td:first-child{text-align:left}th{background:#f6f6f6;font-weight:600}h2{font-size:17px;margin:0 0 10px}td.l{text-align:left;word-break:break-all}tfoot td{font-weight:700}</style>';
echo '<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>방문 기록</title>' . $css . '</head><body><main>';
if (!$authed) {
    echo '<h1>방문 기록</h1><p class="s">먼저 <a href="/#admin">관리자 화면</a>에서 로그인한 뒤 이 주소를 다시 열어 주세요.</p></main></body></html>';
    exit;
}

$www = dirname(__DIR__);
$cands = array(dirname($www) . '/cafe24_log', $www . '/cafe24_log');
if (!empty($_SERVER['DOCUMENT_ROOT'])) { $cands[] = dirname(rtrim($_SERVER['DOCUMENT_ROOT'], '/')) . '/cafe24_log'; }
$files = array();
foreach (array_unique($cands) as $d) {
    if (!is_dir($d)) { continue; }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) { if ($f->isFile() && stripos($f->getFilename(), 'ftp') === false) { $files[$f->getPathname()] = $f->getSize(); } }
}
ksort($files);

$bot = '/bot|crawl|spider|slurp|yeti|daum|facebookexternalhit|kakaotalk-scrap|preview|curl|wget|python|go-http|java\/|okhttp|axios|node-fetch|headless|scan|monitor|httpclient|libwww|zgrab|masscan|semrush|ahrefs|bytespider|gptbot|claude|petalbot|^-?$/i';
$asset = '/\.(css|js|mjs|png|jpe?g|gif|svg|ico|webp|avif|mp4|webm|mov|woff2?|ttf|otf|eot|json|txt|xml|map|pdf|zip)$/i';
$skip = '/^\/(api\/|_tl\/|tl-sync\.php|adm\/|wp-|xmlrpc|\.env|\.git|cgi-bin)/i';
$mon = array('Jan'=>1,'Feb'=>2,'Mar'=>3,'Apr'=>4,'May'=>5,'Jun'=>6,'Jul'=>7,'Aug'=>8,'Sep'=>9,'Oct'=>10,'Nov'=>11,'Dec'=>12);
$days = array(); $pages = array(); $refs = array(); $botHits = 0; $lines = 0;
$re = '/^(\S+) \S+ \S+ \[(\d{2})\/(\w{3})\/(\d{4}):[^\]]+\] "(\w+) ([^ "]*)[^"]*" (\d{3}) \S+(?: "([^"]*)" "([^"]*)")?/';
foreach ($files as $path => $size) {
    $gz = substr($path, -3) === '.gz';
    $fp = $gz ? @gzopen($path, 'rb') : @fopen($path, 'rb');
    if (!$fp) { continue; }
    while (($l = ($gz ? gzgets($fp, 8192) : fgets($fp, 8192))) !== false) {
        $lines++;
        if (!preg_match($re, $l, $m)) { continue; }
        $ua = isset($m[9]) ? $m[9] : '';
        $u = parse_url($m[6], PHP_URL_PATH);
        if (!is_string($u) || $u === '') { $u = '/'; }
        if ($m[5] !== 'GET' || (int)$m[7] >= 400 || preg_match($asset, $u) || preg_match($skip, $u)) { continue; }
        if (preg_match($bot, $ua)) { $botHits++; continue; }
        $day = sprintf('%04d-%02d-%02d', $m[4], isset($mon[$m[3]]) ? $mon[$m[3]] : 0, $m[2]);
        if (!isset($days[$day])) { $days[$day] = array('pv' => 0, 'v' => array()); }
        $days[$day]['pv']++;
        $days[$day]['v'][md5($m[1] . '|' . $ua)] = 1;
        $pages[$u] = (isset($pages[$u]) ? $pages[$u] : 0) + 1;
        $r = isset($m[8]) ? $m[8] : '';
        $rh = ($r !== '' && $r !== '-') ? parse_url($r, PHP_URL_HOST) : '';
        if ($rh && stripos($rh, 'trendylab.co.kr') === false) { $refs[$rh] = (isset($refs[$rh]) ? $refs[$rh] : 0) + 1; }
    }
    $gz ? gzclose($fp) : fclose($fp);
}
ksort($days); arsort($pages); arsort($refs);

echo '<h1>방문 기록 (카페24 서버 로그)</h1>';
echo '<p class="s">카페24 웹로그에서 검색 로봇, 이미지·영상 같은 부속 파일, 관리용 주소는 빼고 센 값이에요. 방문자 수는 같은 날 같은 IP·같은 브라우저를 한 명으로 셌어요(추정치). 카페24는 최대 7일치만 만들어 줘요.<br>읽은 로그 파일 ' . count($files) . '개, 전체 ' . number_format($lines) . '줄, 제외한 로봇 요청 ' . number_format($botHits) . '건.</p>';
if (!$files) {
    echo '<p class="s"><b>아직 로그 파일이 없어요.</b> 카페24 호스팅 관리 → 웹/FTP 로그에서 날짜별로 "실행"을 눌러 주세요. 파일이 만들어지기까지 시간이 걸릴 수 있어요.</p></main></body></html>';
    exit;
}
$wd = array('일','월','화','수','목','금','토');
echo '<h2>날짜별</h2><table><thead><tr><th>날짜</th><th>방문자 수(추정)</th><th>페이지 조회</th></tr></thead><tbody>';
$tv = 0; $tp = 0;
foreach ($days as $d => $x) { $n = count($x['v']); $tv += $n; $tp += $x['pv']; echo '<tr><td>' . h($d) . ' (' . $wd[(int)date('w', strtotime($d))] . ')</td><td>' . number_format($n) . '</td><td>' . number_format($x['pv']) . '</td></tr>'; }
echo '</tbody><tfoot><tr><td>합계</td><td>' . number_format($tv) . '</td><td>' . number_format($tp) . '</td></tr></tfoot></table>';
echo '<h2>어디서 들어왔나 (상위 15)</h2><table><thead><tr><th>들어온 곳</th><th>조회</th></tr></thead><tbody>';
if (!$refs) { echo '<tr><td class="l">기록 없음(주소를 직접 입력했거나 즐겨찾기로 들어온 경우)</td><td>-</td></tr>'; }
foreach (array_slice($refs, 0, 15, true) as $k => $v) { echo '<tr><td class="l">' . h($k) . '</td><td>' . number_format($v) . '</td></tr>'; }
echo '</tbody></table><h2>많이 본 주소 (상위 15)</h2><table><thead><tr><th>주소</th><th>조회</th></tr></thead><tbody>';
foreach (array_slice($pages, 0, 15, true) as $k => $v) { echo '<tr><td class="l">' . h($k) . '</td><td>' . number_format($v) . '</td></tr>'; }
echo '</tbody></table></main></body></html>';
