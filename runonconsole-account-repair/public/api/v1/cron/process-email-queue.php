<?php
if (PHP_SAPI!=='cli') { http_response_code(403); exit; }
require_once dirname(__DIR__).'/account-runtime.php';
session_write_close(); $db=getDBConnection();
if (!$db) { fwrite(STDERR,"Database unavailable\n"); exit(1); }
$db->exec("UPDATE email_queue SET status=CASE WHEN attempts>=5 THEN 'failed' ELSE 'pending' END, locked_by=NULL,locked_at=NULL WHERE status='processing' AND locked_at<DATE_SUB(NOW(),INTERVAL 15 MINUTE)");
$deadline=microtime(true)+45; $count=0;
while ($count<20 && microtime(true)<$deadline && rocDispatch($db)) $count++;
fwrite(STDOUT,gmdate('c')." Processed {$count} jobs.\n");
