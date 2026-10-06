<?php
/**
 * Run On Console — environment settings.
 *
 * Copy this to `site-settings.php` in the same folder and fill it in.
 * This file holds the database password, so:
 *
 *   - never commit it
 *   - chmod 600 site-settings.php
 *   - it must sit in public/api/v1/, next to config.php
 *
 * The existing `.htaccess` already blocks direct access to it, but the settings
 * file being readable by the web server is normal and expected; the protection
 * is that Apache executes .php rather than serving it.
 */

return [

    /* -------------------------------------------------------------- database */

    // The CMS, the router and every API endpoint read from this database.
    // config.php connects only to this name and verifies after connecting.
    'DB_HOST'     => 'localhost',
    'DB_NAME'     => 'CHANGE_ME_production_db_name',
    'DB_USER'     => 'CHANGE_ME_db_user',
    'DB_PASSWORD' => 'CHANGE_ME_db_password',

    /* ------------------------------------------------------------------ site */

    // Used for canonical URLs, CORS, Open Graph and schema. No trailing slash.
    // Note the .htaccess redirects runonconsole.com -> www.runonconsole.com,
    // so this should be the www form or those redirects will fight the canonicals.
    'SITE_URL' => 'https://www.runonconsole.com',

    /* ------------------------------------------------------------------ mail */

    // Leave SMTP_HOST empty to keep mail switched off, which is how staging ran.
    // Account signup, email verification and password reset need this to work.
    'SMTP_HOST'         => '',
    'SMTP_PORT'         => 587,
    'SMTP_ENCRYPTION'   => 'tls',
    'SMTP_USERNAME'     => '',
    'SMTP_PASSWORD'     => '',
    'MAIL_FROM_ADDRESS' => 'noreply@runonconsole.com',
    'MAIL_FROM_NAME'    => 'Run On Console',
    'MAIL_REPLY_TO'     => 'support@runonconsole.com',

    /* ---------------------------------------------------------------- Google */

    // Leave empty to keep "Sign in with Google" off.
    // The redirect URI must match exactly what is registered in Google Console.
    'GOOGLE_CLIENT_ID'     => '',
    'GOOGLE_CLIENT_SECRET' => '',
    'GOOGLE_REDIRECT_URI'  => 'https://www.runonconsole.com/api/v1/oauth/google-callback.php',

    /* --------------------------------------------------------------- CAPTCHA */

    // 'disabled', or the provider your account code expects.
    'CAPTCHA_PROVIDER'   => 'disabled',
    'CAPTCHA_SITE_KEY'   => '',
    'CAPTCHA_SECRET_KEY' => '',
];
