<?php
/**
 * Run On Console — alert emails (the weekly email, the indexing email) come from
 * "Run On Console Updates" <alerts@runonconsole.com> instead of noreply@ (Deploy 120).
 *
 *   php cli-alerts-sender.php                 dry run: shows what would change
 *   php cli-alerts-sender.php --apply         makes the change, with a backup
 *   php cli-alerts-sender.php --rollback=DIR  undoes an --apply using its backup
 *
 * --apply changes one line of rocDispatch() in api/v1/account-runtime.php: an email whose body carries
 * <!--roc-from:alerts--> (member-lib.php, gsc-lib.php) gets the alerts@ sender; replies go to contact@.
 * The mail is still sent through the noreply@ login (envelope sender noreply@). If the mail server refuses the
 * alerts@ sender twice, the third try goes from noreply@ as before, so no alert is lost.
 * Every other email (codes, password resets, replies, form copies) is not touched.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);
$RT = $ROOT . '/api/v1/account-runtime.php';
const MARK = '// ROC alerts sender (cli-alerts-sender.php)';
const OLD_LINE = '$mail->setFrom(MAIL_FROM_ADDRESS,MAIL_FROM_NAME); $mail->addReplyTo(MAIL_REPLY_TO,MAIL_FROM_NAME);';
const NEW_LINE = "\$alerts=strpos((string)\$job['html_body'],'<!--roc-from:alerts-->')!==false && (int)\$job['attempts']<3;   " . MARK . "\n"
          . "        if (\$alerts) { \$mail->setFrom('alerts@runonconsole.com','Run On Console Updates'); \$mail->Sender=MAIL_FROM_ADDRESS; \$mail->addReplyTo('contact@runonconsole.com','Run On Console'); }\n"
          . "        else { \$mail->setFrom(MAIL_FROM_ADDRESS,MAIL_FROM_NAME); \$mail->addReplyTo(MAIL_REPLY_TO,MAIL_FROM_NAME); }";

echo "Run On Console - alerts@ sender for alert emails\n\n";

if ($rollback !== null) {
    $dir = rtrim($rollback, '/');
    if (!is_file($dir . '/account-runtime.php')) exit("No backup found in {$dir}\n");
    copy($dir . '/account-runtime.php', $RT);
    echo "Restored api/v1/account-runtime.php.\n\nDone.\n";
    exit(0);
}

$rt = (string)@file_get_contents($RT);
if ($rt === '') exit("STOP: api/v1/account-runtime.php not found.\n");
if (strpos($rt, MARK) !== false) exit("account-runtime.php: alert emails already come from alerts@. Nothing to do.\n");
if (substr_count($rt, OLD_LINE) !== 1) exit("STOP: the sender line in rocDispatch() does not look as expected. Nothing was changed.\n");
$new = str_replace(OLD_LINE, NEW_LINE, $rt);
echo "account-runtime.php: the weekly email and the indexing email come from \"Run On Console Updates\" <alerts@runonconsole.com>\n";
if (!$apply) exit("\nDRY RUN - nothing changed. Run again with --apply.\n");

$dir = $HOME . '/roc-alerts-sender-' . date('Ymd-His');
if (!@mkdir($dir, 0700, true)) exit("STOP: could not make the backup folder {$dir}\n");
copy($RT, $dir . '/account-runtime.php');
file_put_contents($RT, $new);

// The changed file must still load: if PHP reports an error, put the old one back.
$php = PHP_BINARY ?: 'php';
$out = []; $code = 0;
@exec(escapeshellarg($php) . ' -l ' . escapeshellarg($RT) . ' 2>&1', $out, $code);
if ($code !== 0 && $out) {
    copy($dir . '/account-runtime.php', $RT);
    exit("STOP: account-runtime.php did not pass the PHP check, the old file is back. Nothing changed.\n");
}
echo "\nBackup: {$dir}\nUndo:   php cli-alerts-sender.php --rollback={$dir}\n\nDone. Send a test from CMS > Weekly email to check it.\n";
