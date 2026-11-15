<?php
// Temporary: sends three fixed test notices to the configured address to find a delivery method that arrives.
require __DIR__ . '/_lib.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { tl_out(405, array('ok' => false)); }
$cfg = tl_cfg();
if (empty($cfg['mail_to'])) { tl_out(409, array('ok' => false)); }
$f = tl_dir() . '/mailtest.txt';
if (is_file($f) && time() - filemtime($f) < 120) { tl_out(429, array('ok' => false)); }
@file_put_contents($f, '1');
$to = $cfg['mail_to'];
$body = chunk_split(base64_encode("홈페이지 문의 알림 테스트입니다. 제목 끝의 글자(A, B, C)를 알려 주세요.\n"));
$base = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n";
$sub = function ($x) { return '=?UTF-8?B?' . base64_encode('[트랜디랩 홈페이지] 테스트 ' . $x) . '?='; };
$r = array();
$r['A'] = @mail($to, $sub('A'), $body, $base . "From: no-reply@trendylab.co.kr\r\n", '-fno-reply@trendylab.co.kr');
$r['B'] = @mail($to, $sub('B'), $body, $base);
$r['C'] = @mail($to, $sub('C'), $body, $base . "From: " . $to . "\r\n", '-f' . $to);
$r['host'] = gethostname();
$r['sendmail_from'] = ini_get('sendmail_from');
$r['sendmail_path'] = ini_get('sendmail_path');
tl_out(200, $r);
