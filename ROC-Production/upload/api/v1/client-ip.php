<?php
/**
 * Run On Console — the visitor's IP address, also behind Cloudflare (Deploy 76).
 *
 * With Cloudflare in front, REMOTE_ADDR is a Cloudflare server, so every visitor would share the
 * limits of the contact form and ROC. The CF-Connecting-IP header is only believed when the request
 * really comes from a Cloudflare address (www.cloudflare.com/ips); anyone else could fake it.
 * Without Cloudflare nothing changes.
 */
const ROC_CF_RANGES = [
    '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18', '108.162.192.0/18',
    '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
    '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
    '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
];

function rocIpInRange(string $ip, string $cidr): bool {
    [$net, $bits] = explode('/', $cidr);
    $a = @inet_pton($ip); $b = @inet_pton($net);
    if ($a === false || $b === false || strlen($a) !== strlen($b)) return false;
    $bits = (int)$bits; $bytes = intdiv($bits, 8);
    if (substr($a, 0, $bytes) !== substr($b, 0, $bytes)) return false;
    if ($bits % 8 === 0) return true;
    $mask = (0xff << (8 - $bits % 8)) & 0xff;
    return (ord($a[$bytes]) & $mask) === (ord($b[$bytes]) & $mask);
}

function rocClientIp(): string {
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $cf = trim((string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
    if ($cf === '' || !filter_var($cf, FILTER_VALIDATE_IP)) return $ip;
    foreach (ROC_CF_RANGES as $r) if (rocIpInRange($ip, $r)) return $cf;
    return $ip;
}
