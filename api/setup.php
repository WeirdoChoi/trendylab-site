<?php
// One-time setup: stores the address that inquiry notices are mailed to.
// It only works while no address has been set; after that it refuses.
require __DIR__ . '/_lib.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { tl_out(405, array('ok' => false)); }
$cfg = tl_cfg();
if (!empty($cfg['mail_to'])) { tl_out(409, array('ok' => false, 'error' => 'already_set')); }
$in = tl_in();
$to = tl_str($in, 'mail_to', 120);
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { tl_out(422, array('ok' => false, 'error' => 'email')); }
$cfg['mail_to'] = $to;
tl_out(tl_save_cfg($cfg) ? 200 : 500, array('ok' => true));
