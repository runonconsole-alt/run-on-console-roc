<?php
/**
 * Run On Console — the sign-up email with a 6-digit code (no link), in the site's colours.
 * Used by rocIssueChallenge() in account-runtime.php for purpose 'verify' (hook added by
 * api/v1/cron/cli-auth-code-email.php). The code is shown as six boxes, like the code screen
 * on /auth/verify-email/.
 */

function rocCodeEmailSubject(string $code): string {
    return $code . ' is your Run On Console code';
}

function rocCodeEmail(string $code, array $u): string {
    $esc = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    $name = trim((string)($u['name'] ?? '')) ?: trim((string)($u['username'] ?? ''));
    $base = defined('ROC_SITE_URL') ? rtrim(ROC_SITE_URL, '/') : 'https://runonconsole.com';
    $boxes = '';
    foreach (str_split(preg_replace('/\D/', '', $code)) as $d) {
        $boxes .= '<td style="padding:0 4px"><div style="width:44px;height:54px;line-height:54px;border-radius:12px;background:#0f172a;border:2px solid #14b8a6;'
            . 'color:#ffffff;font:bold 26px/54px \'Courier New\',monospace;text-align:center">' . $esc($d) . '</div></td>';
    }
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Your code</title></head>'
        . '<body style="margin:0;background:#020617;font-family:Arial,Helvetica,sans-serif;padding:24px 12px">'
        . '<div style="display:none;max-height:0;overflow:hidden">Your Run On Console code is ' . $esc($code) . '. It works for 10 minutes.</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#0f172a;border:1px solid #134e4a;border-radius:20px">'
        . '<tr><td style="height:6px;background:#14b8a6;background-image:linear-gradient(90deg,#14b8a6,#10b981,#059669);border-radius:20px 20px 0 0"></td></tr>'
        . '<tr><td align="center" style="padding:32px 28px 8px">'
        . '<img src="' . $esc($base) . '/images/logo-512.png" width="56" height="56" alt="Run On Console" style="display:block;border:0;border-radius:14px">'
        . '<div style="margin-top:14px;font-size:13px;letter-spacing:2px;color:#5eead4;font-weight:bold">RUN ON CONSOLE</div>'
        . '<h1 style="margin:18px 0 6px;font-size:24px;color:#ffffff">Verify it’s you</h1>'
        . '<p style="margin:0;font-size:14px;line-height:22px;color:#94a3b8">' . ($name !== '' ? 'Hi ' . $esc($name) . ', enter' : 'Enter') . ' this code on the verification screen to finish signing up.</p>'
        . '</td></tr>'
        . '<tr><td align="center" style="padding:22px 12px 6px"><table role="presentation" cellpadding="0" cellspacing="0"><tr>' . $boxes . '</tr></table></td></tr>'
        . '<tr><td align="center" style="padding:10px 28px 28px">'
        . '<p style="margin:0;font-size:13px;color:#cbd5e1">The code works for <b style="color:#5eead4">10 minutes</b>. Only the newest code works.</p>'
        . '<p style="margin:14px 0 0;font-size:12px;line-height:19px;color:#64748b">We will never ask you for this code by phone, chat or email. '
        . 'If you did not sign up at runonconsole.com, ignore this email: no account is made without the code.</p>'
        . '</td></tr></table>'
        . '<p style="margin:16px 0 0;font-size:11px;color:#475569">Run On Console · <a href="' . $esc($base) . '/" style="color:#5eead4;text-decoration:none">runonconsole.com</a></p>'
        . '</td></tr></table></body></html>';
}
