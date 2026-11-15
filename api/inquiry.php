<?php
// Receives an inquiry from the site forms, stores it on the server and e-mails a notice.
require __DIR__ . '/_lib.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { tl_out(405, array('ok' => false)); }
$in = tl_in();
if (!$in) { tl_out(400, array('ok' => false, 'error' => 'bad_request')); }
if (!empty($in['hp'])) { tl_out(200, array('ok' => true)); } // hidden field filled in: a bot

$SV = array('cafe' => '카페 침투 / 커뮤니티', 'seo' => '네이버 SEO', 'review' => '체험단', 'instagram' => '인스타그램', 'youtube' => '유튜브', 'x' => 'X (트위터)', 'tiktok' => '틱톡', 'threads' => '스레드', 'imc' => '종합 마케팅');
$r = array(
    'mode' => (isset($in['mode']) && $in['mode'] === 'profile') ? 'profile' : 'project',
    'b' => tl_str($in, 'b', 100), 'n' => tl_str($in, 'n', 60), 'r' => tl_str($in, 'r', 30),
    't' => tl_str($in, 't', 40), 'e' => tl_str($in, 'e', 120), 'm' => tl_str($in, 'm', 4000, true),
    's' => array(),
);
if (isset($in['s']) && is_array($in['s'])) {
    foreach ($in['s'] as $k) { if (is_string($k) && isset($SV[$k]) && !in_array($SV[$k], $r['s'], true)) { $r['s'][] = $SV[$k]; } }
}
if ($r['e'] !== '' && !filter_var($r['e'], FILTER_VALIDATE_EMAIL)) { tl_out(422, array('ok' => false, 'error' => 'email')); }
if ($r['t'] === '' && $r['e'] === '') { tl_out(422, array('ok' => false, 'error' => 'contact')); }

$dir = tl_dir();
$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
// at most 5 inquiries per address in 10 minutes
$rlf = $dir . '/rate.json';
$rl = is_file($rlf) ? (json_decode(file_get_contents($rlf), true) ?: array()) : array();
$now = time();
foreach ($rl as $k => $ts) { $rl[$k] = array_values(array_filter($ts, function ($x) use ($now) { return $now - $x < 600; })); if (!$rl[$k]) { unset($rl[$k]); } }
$key = md5($ip);
if (isset($rl[$key]) && count($rl[$key]) >= 5) { tl_out(429, array('ok' => false, 'error' => 'too_many')); }
$rl[$key][] = $now;
@file_put_contents($rlf, json_encode($rl), LOCK_EX);

date_default_timezone_set('Asia/Seoul');
$r['id'] = date('ymdHis') . '-' . substr(md5(uniqid('', true)), 0, 6);
$r['at'] = date('Y-m-d H:i:s');
$r['ip'] = $ip;
$r['st'] = 'new';
$store = $dir . '/inquiries.php';
if (!is_file($store)) { file_put_contents($store, "<?php exit; ?>\n", LOCK_EX); }
if (file_put_contents($store, json_encode($r, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX) === false) {
    tl_out(500, array('ok' => false, 'error' => 'store'));
}

$cfg = tl_cfg();
$sent = false;
if (!empty($cfg['mail_to'])) {
    $kind = $r['mode'] === 'profile' ? '회사소개서 요청' : '문의';
    $subject = '[트랜디랩 홈페이지] ' . $kind . ': ' . ($r['b'] !== '' ? $r['b'] : '(브랜드명 없음)') . ($r['n'] !== '' ? ' / ' . $r['n'] : '');
    $body = "홈페이지에서 " . $kind . "이 접수되었습니다.\n\n"
        . "접수 시각: " . $r['at'] . "\n"
        . "브랜드/회사: " . $r['b'] . "\n"
        . "담당자: " . $r['n'] . ($r['r'] !== '' ? ' (' . $r['r'] . ')' : '') . "\n"
        . "연락처: " . $r['t'] . "\n"
        . "이메일: " . $r['e'] . "\n"
        . "관심 서비스: " . implode(', ', $r['s']) . "\n\n"
        . "문의 내용:\n" . $r['m'] . "\n";
    $from = 'no-reply@trendylab.co.kr';
    $h = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n"
        . "From: =?UTF-8?B?" . base64_encode('트랜디랩 홈페이지') . "?= <" . $from . ">\r\n";
    if ($r['e'] !== '') { $h .= "Reply-To: " . $r['e'] . "\r\n"; }
    $sent = @mail($cfg['mail_to'], '=?UTF-8?B?' . base64_encode($subject) . '?=', chunk_split(base64_encode($body)), $h);
}
tl_out(200, array('ok' => true, 'mail' => $sent));
