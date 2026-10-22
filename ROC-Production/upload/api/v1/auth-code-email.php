<?php
/**
 * Run On Console — the sign-up email with a 6-digit code (no link).
 * Used by rocIssueChallenge() in account-runtime.php for purpose 'verify' (hook added by
 * api/v1/cron/cli-auth-code-email.php).
 *
 * Plain and light on purpose, like the big sites' code emails: the code is one line of text
 * after "use the following code", so Gmail and phones recognise it and offer "Copy code".
 */

function rocCodeEmailSubject(string $code): string {
    return $code . ' is your Run On Console code';
}

function rocCodeEmail(string $code, array $u): string {
    $esc = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    $code = (string)preg_replace('/\D/', '', $code);
    $name = trim((string)($u['username'] ?? '')) ?: trim((string)($u['name'] ?? ''));
    $base = defined('ROC_SITE_URL') ? rtrim(ROC_SITE_URL, '/') : 'https://runonconsole.com';
    $p = 'margin:0 0 16px;font-size:15px;line-height:24px;color:#262626';
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Verify your account</title></head>'
        . '<body style="margin:0;background:#ffffff;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif">'
        . '<div style="display:none;max-height:0;overflow:hidden">Your Run On Console code is ' . $esc($code) . '</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#ffffff"><tr><td align="center" style="padding:32px 16px">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:460px">'
        // logo | name, like the header of the big sites' emails
        . '<tr><td style="padding:0 0 28px"><table role="presentation" cellpadding="0" cellspacing="0"><tr>'
        . '<td><img src="' . $esc($base) . '/images/logo-512.png" width="36" height="36" alt="" style="display:block;border:0;border-radius:9px"></td>'
        . '<td style="padding:0 12px;color:#dbdbdb;font-size:22px">|</td>'
        . '<td style="font-size:20px;font-weight:700;color:#047857;letter-spacing:-.2px">Run On Console</td>'
        . '</tr></table></td></tr>'
        . '<tr><td>'
        . '<p style="' . $p . '">Hi ' . ($name !== '' ? $esc($name) : 'there') . ',</p>'
        . '<p style="' . $p . '">Someone used this email to create an account on Run On Console.</p>'
        . '<p style="' . $p . '">If this was you, please use the following code to confirm your email:</p>'
        . '<p style="margin:0 0 20px;font-size:30px;line-height:36px;font-weight:700;letter-spacing:4px;color:#262626">' . $esc($code) . '</p>'
        . '<p style="' . $p . '">This code works for 10 minutes. Only the newest code works.</p>'
        . '<p style="' . $p . '">If this wasn\'t you, you can ignore this email. No account is made without the code, and we will never ask you for it by phone or chat.</p>'
        . '<p style="margin:0;font-size:15px;line-height:24px;color:#262626">Thanks,<br>Run On Console</p>'
        . '</td></tr>'
        . '<tr><td style="height:32px;line-height:32px;font-size:0">&nbsp;</td></tr>'
        . '<tr><td style="border-top:1px solid #efefef;padding-top:18px;font-size:12px;line-height:18px;color:#8e8e8e;text-align:center">This message was sent to you by Run On Console<br>'
        . '<a href="' . $esc($base) . '/" style="color:#8e8e8e">runonconsole.com</a> · Lahore, Pakistan</td></tr>'
        . '</table></td></tr></table></body></html>';
}
