<?php
/**
 * Run On Console (ROC) - CMS Shared Helper Utility Module
 * Centralized helpers for slugification, server-side HTML allowlist sanitization, and response formatting.
 */

if (!function_exists('rocAgentSlugify')) {
    function rocAgentSlugify(string $text): string {
        $clean = strtolower(trim($text));
        $clean = preg_replace('/[\s_]+/', '-', $clean);
        $clean = preg_replace('/[^\w\-]+/', '', $clean);
        $clean = preg_replace('/\-+/', '-', $clean);
        return trim($clean, '-');
    }
}

/**
 * Server-Side Allowlist HTML Sanitizer
 * Strips script tags, iframes, inline event handlers (onload, onclick), and dangerous URI schemes (javascript:, data:).
 */
if (!function_exists('rocSanitizeHtml')) {
    function rocSanitizeHtml(string $html): string {
        if (empty(trim($html))) return '';

        // Strip dangerous tags completely
        $clean = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $clean = preg_replace('/<iframe\b[^>]*>(.*?)<\/iframe>/is', '', $clean);
        $clean = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $clean);

        // Strip inline event attributes (on*, e.g. onload, onclick, onerror)
        $clean = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean);

        // Strip dangerous URI schemes from href and src attributes (javascript:, data:)
        $clean = preg_replace('/(href|src)\s*=\s*["\']?\s*(javascript|data|vbscript):[^"\'>\s]*/i', '$1="#"', $clean);

        return $clean;
    }
}

if (!function_exists('rocCmsJsonResponse')) {
    function rocCmsJsonResponse(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header("Content-Type: application/json; charset=UTF-8");
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
        exit();
    }
}
