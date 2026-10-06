import fs from 'fs';
import path from 'path';

const ROOT_DIR = process.cwd();

const seedGamesPhpContent = `<?php
/**
 * Run On Console (ROC) - Standalone Optional Game & Hardware Seeding Script
 * CLI execution only. Populates verified games, requirements, and hardware tier mappings.
 *
 * Safety & Verification Rules:
 * 1. Aborts unless SELECT DATABASE() returns 'runoncon_rocstage'.
 * 2. Preview mode by default (php api/v1/cron/seed-roc-agent-games.php).
 * 3. Database writes strictly require the literal CLI --confirm flag.
 * 4. Pre-write resolution uses exact production lookup normalization rules (no fuzzy matching).
 * 5. Dynamic requirement count validation (sum of actual minimum/recommended rows).
 * 6. All writes wrapped in PDO transaction with rollback on Throwable.
 */

if (php_sapi_name() !== 'cli' && empty($_SERVER['ROC_CLI_RUN'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden. CLI execution required.']);
    exit(1);
}

require_once dirname(__DIR__) . '/config.php';

function rocAgentValidateGameRequirementsResolution(array $gamesSeed, array $hardwareSeed): array {
    $map = [];
    foreach ($hardwareSeed as $h) {
        $type = strtolower(trim($h[0]));
        $raw = strtolower(trim($h[1]));
        $norm = strtolower(trim($h[2]));
        $tier = (int)$h[3];
        $map[$type][$raw] = $tier;
        $map[$type][$norm] = $tier;
    }

    $unresolved = [];

    // Exact 3-step lookup rule matching production check-compatibility.php (NO fuzzy str_contains)
    $resolveSingleToken = function(string $token, string $type) use ($map): bool {
        $clean = strtolower(trim($token));
        if ($clean === '') return false;

        // Step 1: Direct exact match on raw_name or normalized_name
        if (isset($map[$type][$clean])) return true;

        // Step 2: Secondary match stripping trailing VRAM / RAM suffixes (e.g. "gtx 1060 6gb" -> "gtx 1060")
        $cleanBase = trim(preg_replace('/\\s+\\d+gb$/i', '', $clean));
        if ($cleanBase !== '' && $cleanBase !== $clean && isset($map[$type][$cleanBase])) {
            return true;
        }

        return false;
    };

    $resolveValue = function(string $val, string $type) use ($resolveSingleToken): bool {
        $clean = strtolower(trim($val));
        if ($clean === '') return false;

        // Step 1: Compound "or" or "/" split
        if (preg_match('/\\s+(?:or|\\/)\\s+/i', $clean)) {
            $tokens = preg_split('/\\s+(?:or|\\/)\\s+/i', $clean);
            foreach ($tokens as $t) {
                if (!$resolveSingleToken($t, $type)) return false;
            }
            return true;
        }

        return $resolveSingleToken($clean, $type);
    };

    foreach ($gamesSeed as $g) {
        $gameName = $g['name'];
        foreach (['minimum', 'recommended'] as $reqType) {
            $reqs = $g['requirements'][$reqType] ?? null;
            if (!$reqs) continue;

            foreach (['cpu', 'gpu'] as $compType) {
                $val = $reqs[$compType] ?? '';
                if ($val === '') continue;

                if (!$resolveValue($val, $compType)) {
                    $unresolved[] = "Game '{$gameName}' ({$reqType} {$compType}): Value '{$val}' UNRESOLVED under exact 3-step production lookup rules!";
                }
            }
        }
    }

    return $unresolved;
}

function rocAgentSeedGamesAndHardware(): void {
    $args = $_SERVER['argv'] ?? [];
    $isConfirmed = in_array('--confirm', $args, true);

    echo "============================================================\\n";
    echo "RUN ON CONSOLE (ROC) - GAMES & HARDWARE SEEDER\\n";
    echo "============================================================\\n\\n";

    $pdo = getDBConnection();
    if (!$pdo) {
        echo "❌ Error: Database connection unavailable.\\n";
        exit(1);
    }

    // 1. Strict Database Guard: Must be runoncon_rocstage
    $dbStmt = $pdo->query("SELECT DATABASE()");
    $currentDb = $dbStmt ? $dbStmt->fetchColumn() : null;

    if ($currentDb !== 'runoncon_rocstage') {
        echo "❌ ABORTED: Script is strictly restricted to staging database 'runoncon_rocstage'.\\n";
        echo "   Current database is: '" . ($currentDb ?: 'NONE') . "'\\n";
        echo "   This script will NEVER write to production or any other database.\\n";
        exit(1);
    }
    echo "✓ Verified Database Isolation: Connected to '{$currentDb}'\\n";

    if (!$isConfirmed) {
        echo "⚠️ PREVIEW MODE ONLY (No database writes performed).\\n";
        echo "   To apply seeding, run with explicit approval flag:\\n";
        echo "   php api/v1/cron/seed-roc-agent-games.php --confirm\\n\\n";
    } else {
        echo "🚀 CONFIRMED WRITE MODE ENABLED (--confirm flag provided).\\n\\n";
    }

    // 2. Hardware Tiers Seed (with Exact Normalized & Raw Alias Mappings)
    $hardwareSeed = [
        // GPUs
        ['gpu', 'rtx 4090', 'NVIDIA GeForce RTX 4090', 4, 'Ada Lovelace', 'Official NVIDIA Specs / TechPowerUp GPU DB'],
        ['gpu', 'rtx 4080', 'NVIDIA GeForce RTX 4080', 4, 'Ada Lovelace', 'Official NVIDIA Specs / TechPowerUp GPU DB'],
        ['gpu', 'rtx 4070', 'NVIDIA GeForce RTX 4070', 4, 'Ada Lovelace', 'Official NVIDIA Specs / TechPowerUp GPU DB'],
        ['gpu', 'rtx 3080', 'NVIDIA GeForce RTX 3080', 4, 'Ampere', 'Official NVIDIA Specs / TechPowerUp GPU DB'],
        ['gpu', 'rtx 3070', 'NVIDIA GeForce RTX 3070', 3, 'Ampere', 'Official NVIDIA Specs / TechPowerUp GPU DB'],
        ['gpu', 'rtx 3060', 'NVIDIA GeForce RTX 3060', 3, 'Ampere', 'Official NVIDIA Specs / TechPowerUp GPU DB'],
        ['gpu', 'rtx 2060 super', 'NVIDIA GeForce RTX 2060 Super', 3, 'Turing', 'Official NVIDIA Specs / TechPowerUp GPU DB'],
        ['gpu', 'rtx 2060', 'NVIDIA GeForce RTX 2060', 3, 'Turing', 'Official NVIDIA Specs / TechPowerUp GPU DB'],
        ['gpu', 'rx 5700 xt', 'AMD Radeon RX 5700 XT', 3, 'RDNA 1.0', 'Official AMD Specs / TechPowerUp GPU DB'],
        ['gpu', 'rx 5700', 'AMD Radeon RX 5700', 3, 'RDNA 1.0', 'Official AMD Specs / TechPowerUp GPU DB'],
        ['gpu', 'rx vega 56', 'AMD Radeon RX Vega 56', 3, 'GCN 5.0', 'Official AMD Specs / TechPowerUp GPU DB'],
        ['gpu', 'gtx 1070', 'NVIDIA GeForce GTX 1070', 3, 'Pascal', 'Official NVIDIA Specs / TechPowerUp GPU DB'],
        ['gpu', 'gtx 1060', 'NVIDIA GeForce GTX 1060', 2, 'Pascal', 'Official NVIDIA Specs / TechPowerUp GPU DB'],
        ['gpu', 'rx 580', 'AMD Radeon RX 580', 2, 'Polaris', 'Official AMD Specs / TechPowerUp GPU DB'],
        ['gpu', 'rx 480', 'AMD Radeon RX 480', 2, 'Polaris', 'Official AMD Specs / TechPowerUp GPU DB'],
        ['gpu', 'gtx 1660', 'NVIDIA GeForce GTX 1660', 2, 'Turing', 'Official NVIDIA Specs / TechPowerUp GPU DB'],
        ['gpu', 'gtx 770', 'NVIDIA GeForce GTX 770', 2, 'Kepler', 'Official NVIDIA Specs / TechPowerUp GPU DB'],
        ['gpu', 'r9 290', 'AMD Radeon R9 290', 2, 'GCN 2.0', 'Official AMD Specs / TechPowerUp GPU DB'],
        ['gpu', 'gtx 1050 ti', 'NVIDIA GeForce GTX 1050 Ti', 1, 'Pascal', 'Official NVIDIA Specs / TechPowerUp GPU DB'],
        ['gpu', 'gtx 960', 'NVIDIA GeForce GTX 960', 1, 'Maxwell', 'Official NVIDIA Specs / TechPowerUp GPU DB'],
        ['gpu', 'r9 280', 'AMD Radeon R9 280', 1, 'GCN 1.0', 'Official AMD Specs / TechPowerUp GPU DB'],
        ['gpu', 'r7 370', 'AMD Radeon R7 370', 1, 'GCN 1.0', 'Official AMD Specs / TechPowerUp GPU DB'],
        ['gpu', 'gtx 660', 'NVIDIA GeForce GTX 660', 1, 'Kepler', 'Official NVIDIA Specs / TechPowerUp GPU DB'],
        ['gpu', 'hd 7870', 'AMD Radeon HD 7870', 1, 'GCN 1.0', 'Official AMD Specs / TechPowerUp GPU DB'],
        ['gpu', '9800 gt', 'NVIDIA GeForce 9800 GT', 0, 'Tesla', 'Official NVIDIA Specs'],
        ['gpu', 'hd 4870', 'AMD Radeon HD 4870', 0, 'TeraScale', 'Official AMD Specs'],
        ['gpu', 'r5 200', 'AMD Radeon R5 200', 0, 'GCN', 'Official AMD Specs'],
        ['gpu', 'intel hd 4000', 'Intel HD Graphics 4000', 0, 'Ivy Bridge', 'Intel Ark Specs'],

        // CPUs
        ['cpu', 'i7-12700', 'Intel Core i7-12700', 4, 'Alder Lake', 'Intel Ark Specs'],
        ['cpu', '7800x3d', 'AMD Ryzen 7 7800X3D', 4, 'Zen 4', 'AMD Official Specs'],
        ['cpu', 'i7-9700k', 'Intel Core i7-9700K', 3, 'Coffee Lake', 'Intel Ark Specs'],
        ['cpu', 'ryzen 7 3700x', 'AMD Ryzen 7 3700X', 3, 'Zen 2', 'AMD Official Specs'],
        ['cpu', 'i7-8700k', 'Intel Core i7-8700K', 2, 'Coffee Lake', 'Intel Ark Specs'],
        ['cpu', 'ryzen 5 3600x', 'AMD Ryzen 5 3600X', 3, 'Zen 2', 'AMD Official Specs'],
        ['cpu', 'i5-9400f', 'Intel Core i5-9400F', 2, 'Coffee Lake', 'Intel Ark Specs'],
        ['cpu', 'ryzen 5 2600x', 'AMD Ryzen 5 2600X', 2, 'Zen+', 'AMD Official Specs'],
        ['cpu', 'i7-6700', 'Intel Core i7-6700', 2, 'Skylake', 'Intel Ark Specs'],
        ['cpu', 'ryzen 5 1600', 'AMD Ryzen 5 1600', 2, 'Zen', 'AMD Official Specs'],
        ['cpu', 'i7-4770k', 'Intel Core i7-4770K', 2, 'Haswell', 'Intel Ark Specs'],
        ['cpu', 'ryzen 5 1500x', 'AMD Ryzen 5 1500X', 2, 'Zen', 'AMD Official Specs'],
        ['cpu', 'i5-8400', 'Intel Core i5-8400', 2, 'Coffee Lake', 'Intel Ark Specs'],
        ['cpu', 'ryzen 3 3300x', 'AMD Ryzen 3 3300X', 2, 'Zen 2', 'AMD Official Specs'],
        ['cpu', 'i5-6600', 'Intel Core i5-6600', 2, 'Skylake', 'Intel Ark Specs'],
        ['cpu', 'i7-3770', 'Intel Core i7-3770', 2, 'Ivy Bridge', 'Intel Ark Specs'],
        ['cpu', 'fx-8350', 'AMD FX-8350', 1, 'Bulldozer', 'AMD Official Specs'],
        ['cpu', 'i5-3470', 'Intel Core i5-3470', 1, 'Ivy Bridge', 'Intel Ark Specs'],
        ['cpu', 'fx-6300', 'AMD FX-6300', 1, 'Bulldozer', 'AMD Official Specs'],
        ['cpu', 'i5-2500k', 'Intel Core i5-2500K', 1, 'Sandy Bridge', 'Intel Ark Specs'],
        ['cpu', 'phenom ii x4 940', 'AMD Phenom II X4 940', 1, 'K10', 'AMD Official Specs'],
        ['cpu', 'i5-750', 'Intel Core i5-750', 1, 'Nehalem', 'Intel Ark Specs'],
        ['cpu', 'q6600', 'Intel Core 2 Quad Q6600', 0, 'Kentsfield', 'Intel Ark Specs'],
        ['cpu', 'phenom 9850', 'AMD Phenom 9850', 0, 'K10', 'AMD Official Specs'],
        ['cpu', 'e8400', 'Intel Core 2 Duo E8400', 0, 'Wolfdale', 'Intel Ark Specs'],
        ['cpu', 'athlon 200ge', 'AMD Athlon 200GE', 0, 'Zen', 'AMD Official Specs'],
        ['cpu', 'i3-3225', 'Intel Core i3-3225', 1, 'Ivy Bridge', 'Intel Ark Specs']
    ];

    // Games & Requirements Dataset with Direct Official Source URLs
    $gamesSeed = [
        [
            'name' => 'Cyberpunk 2077',
            'slug' => 'cyberpunk-2077',
            'platform' => 'PC',
            'genre' => 'Action RPG',
            'image' => '/images/battlestation_pc.jpg',
            'description' => 'An open-world, action-adventure RPG set in the megalopolis of Night City.',
            'requirements' => [
                'minimum' => [
                    'cpu' => 'Intel Core i7-6700 or AMD Ryzen 5 1600',
                    'gpu' => 'NVIDIA GeForce GTX 1060 6GB or AMD Radeon RX 580 8GB',
                    'ram_gb' => 12,
                    'storage_gb' => 70,
                    'operating_system' => 'Windows 10 64-bit',
                    'notes' => 'Verified Official CD PROJEKT RED / Steam Store Specs (https://store.steampowered.com/app/1091500/Cyberpunk_2077/)'
                ],
                'recommended' => [
                    'cpu' => 'Intel Core i7-12700 or AMD Ryzen 7 7800X3D',
                    'gpu' => 'NVIDIA GeForce RTX 2060 Super or AMD Radeon RX 5700 XT',
                    'ram_gb' => 16,
                    'storage_gb' => 70,
                    'operating_system' => 'Windows 10/11 64-bit',
                    'notes' => 'Verified Official CD PROJEKT RED / Steam Store Specs (https://store.steampowered.com/app/1091500/Cyberpunk_2077/)'
                ]
            ]
        ],
        [
            'name' => 'Grand Theft Auto V',
            'slug' => 'grand-theft-auto-v',
            'platform' => 'PC',
            'genre' => 'Action / Open World',
            'image' => '/images/battlestation_pc.jpg',
            'description' => 'Los Santos: a sprawling sun-soaked metropolis full of self-help gurus, starlets, and fading celebrities.',
            'requirements' => [
                'minimum' => [
                    'cpu' => 'Intel Core 2 Quad Q6600 or AMD Phenom 9850',
                    'gpu' => 'NVIDIA GeForce 9800 GT 1GB or AMD Radeon HD 4870 1GB',
                    'ram_gb' => 4,
                    'storage_gb' => 110,
                    'operating_system' => 'Windows 10 64-bit',
                    'notes' => 'Verified Official Rockstar Games / Steam Store Specs (https://store.steampowered.com/app/271590/Grand_Theft_Auto_V/)'
                ],
                'recommended' => [
                    'cpu' => 'Intel Core i5-3470 or AMD FX-8350',
                    'gpu' => 'NVIDIA GeForce GTX 660 2GB or AMD Radeon HD 7870 2GB',
                    'ram_gb' => 8,
                    'storage_gb' => 110,
                    'operating_system' => 'Windows 10 64-bit',
                    'notes' => 'Verified Official Rockstar Games / Steam Store Specs (https://store.steampowered.com/app/271590/Grand_Theft_Auto_V/)'
                ]
            ]
        ],
        [
            'name' => 'The Witcher 3: Wild Hunt',
            'slug' => 'the-witcher-3-wild-hunt',
            'platform' => 'PC',
            'genre' => 'Action RPG',
            'image' => '/images/trending_elden.jpg',
            'description' => 'You are Geralt of Rivia, mercenary monster slayer.',
            'requirements' => [
                'minimum' => [
                    'cpu' => 'Intel Core i5-2500K or AMD Phenom II X4 940',
                    'gpu' => 'NVIDIA GeForce GTX 660 or AMD Radeon HD 7870',
                    'ram_gb' => 6,
                    'storage_gb' => 50,
                    'operating_system' => 'Windows 7/8/10 64-bit',
                    'notes' => 'Verified Official CD PROJEKT RED / Steam Store Specs (https://store.steampowered.com/app/292030/The_Witcher_3_Wild_Hunt/)'
                ],
                'recommended' => [
                    'cpu' => 'Intel Core i7-3770 or AMD FX-8350',
                    'gpu' => 'NVIDIA GeForce GTX 770 or AMD Radeon R9 290',
                    'ram_gb' => 8,
                    'storage_gb' => 50,
                    'operating_system' => 'Windows 10 64-bit',
                    'notes' => 'Verified Official CD PROJEKT RED / Steam Store Specs (https://store.steampowered.com/app/292030/The_Witcher_3_Wild_Hunt/)'
                ]
            ]
        ],
        [
            'name' => 'Red Dead Redemption 2',
            'slug' => 'red-dead-redemption-2',
            'platform' => 'PC',
            'genre' => 'Action / Open World',
            'image' => '/images/battlestation_pc.jpg',
            'description' => 'America, 1899. Arthur Morgan and the Van der Linde gang are outlaws on the run.',
            'requirements' => [
                'minimum' => [
                    'cpu' => 'Intel Core i5-2500K or AMD FX-6300',
                    'gpu' => 'NVIDIA GeForce GTX 770 2GB or AMD Radeon R9 280 3GB',
                    'ram_gb' => 8,
                    'storage_gb' => 150,
                    'operating_system' => 'Windows 10 64-bit',
                    'notes' => 'Verified Official Rockstar Games / Steam Store Specs (https://store.steampowered.com/app/1174180/Red_Dead_Redemption_2/)'
                ],
                'recommended' => [
                    'cpu' => 'Intel Core i7-4770K or AMD Ryzen 5 1500X',
                    'gpu' => 'NVIDIA GeForce GTX 1060 6GB or AMD Radeon RX 480 4GB',
                    'ram_gb' => 12,
                    'storage_gb' => 150,
                    'operating_system' => 'Windows 10 64-bit',
                    'notes' => 'Verified Official Rockstar Games / Steam Store Specs (https://store.steampowered.com/app/1174180/Red_Dead_Redemption_2/)'
                ]
            ]
        ],
        [
            'name' => 'Elden Ring',
            'slug' => 'elden-ring',
            'platform' => 'PC',
            'genre' => 'Action RPG',
            'image' => '/images/trending_elden.jpg',
            'description' => 'Rise, Tarnished, and be guided by grace to brandish the power of the Elden Ring.',
            'requirements' => [
                'minimum' => [
                    'cpu' => 'Intel Core i5-8400 or AMD Ryzen 3 3300X',
                    'gpu' => 'NVIDIA GeForce GTX 1060 3GB or AMD Radeon RX 580 4GB',
                    'ram_gb' => 12,
                    'storage_gb' => 60,
                    'operating_system' => 'Windows 10 64-bit',
                    'notes' => 'Verified Official FromSoftware / Steam Store Specs (https://store.steampowered.com/app/1245620/ELDEN_RING/)'
                ],
                'recommended' => [
                    'cpu' => 'Intel Core i7-8700K or AMD Ryzen 5 3600X',
                    'gpu' => 'NVIDIA GeForce GTX 1070 8GB or AMD Radeon RX Vega 56 8GB',
                    'ram_gb' => 16,
                    'storage_gb' => 60,
                    'operating_system' => 'Windows 10/11 64-bit',
                    'notes' => 'Verified Official FromSoftware / Steam Store Specs (https://store.steampowered.com/app/1245620/ELDEN_RING/)'
                ]
            ]
        ],
        [
            'name' => 'Valorant',
            'slug' => 'valorant',
            'platform' => 'PC',
            'genre' => 'Tactical Shooter',
            'image' => '/images/trending_esports.jpg',
            'description' => 'VALORANT is a character-based 5v5 tactical shooter set on the global stage.',
            'requirements' => [
                'minimum' => [
                    'cpu' => 'Intel Core 2 Duo E8400 or AMD Athlon 200GE',
                    'gpu' => 'Intel HD Graphics 4000 or AMD Radeon R5 200',
                    'ram_gb' => 4,
                    'storage_gb' => 30,
                    'operating_system' => 'Windows 10 64-bit',
                    'notes' => 'Verified Official Riot Games Specs (https://playvalorant.com/en-us/news/game-updates/valorant-system-requirements/)'
                ],
                'recommended' => [
                    'cpu' => 'Intel Core i5-9400F or AMD Ryzen 5 2600X',
                    'gpu' => 'NVIDIA GeForce GTX 1050 Ti or AMD Radeon R7 370',
                    'ram_gb' => 8,
                    'storage_gb' => 30,
                    'operating_system' => 'Windows 10/11 64-bit',
                    'notes' => 'Verified Official Riot Games Specs (https://playvalorant.com/en-us/news/game-updates/valorant-system-requirements/)'
                ]
            ]
        ],
        [
            'name' => 'Black Myth: Wukong',
            'slug' => 'black-myth-wukong',
            'platform' => 'PC',
            'genre' => 'Action RPG',
            'image' => '/images/battlestation_pc.jpg',
            'description' => 'Black Myth: Wukong is an action RPG rooted in Chinese mythology.',
            'requirements' => [
                'minimum' => [
                    'cpu' => 'Intel Core i5-8400 or AMD Ryzen 5 1600',
                    'gpu' => 'NVIDIA GeForce GTX 1060 6GB or AMD Radeon RX 580 8GB',
                    'ram_gb' => 12,
                    'storage_gb' => 130,
                    'operating_system' => 'Windows 10 64-bit',
                    'notes' => 'Verified Official Game Science / Steam Store Specs (https://store.steampowered.com/app/2358720/Black_Myth_Wukong/)'
                ],
                'recommended' => [
                    'cpu' => 'Intel Core i7-8700K or AMD Ryzen 5 3600X',
                    'gpu' => 'NVIDIA GeForce RTX 2060 or AMD Radeon RX 5700 XT',
                    'ram_gb' => 16,
                    'storage_gb' => 130,
                    'operating_system' => 'Windows 10/11 64-bit',
                    'notes' => 'Verified Official Game Science / Steam Store Specs (https://store.steampowered.com/app/2358720/Black_Myth_Wukong/)'
                ]
            ]
        ]
    ];

    // Dynamic Requirement Count Calculation (Sum of minimum/recommended rows in $gamesSeed)
    $expectedRequirementCount = 0;
    foreach ($gamesSeed as $g) {
        if (isset($g['requirements']['minimum'])) $expectedRequirementCount++;
        if (isset($g['requirements']['recommended'])) $expectedRequirementCount++;
    }

    // 4. Pre-Write Requirements Resolution Validation
    echo "🔍 PERFORMING PRE-WRITE HARDWARE RESOLUTION VALIDATION (Production Lookup Rules):\\n";
    echo "------------------------------------------------------------\\n";
    $unresolved = rocAgentValidateGameRequirementsResolution($gamesSeed, $hardwareSeed);
    if (!empty($unresolved)) {
        echo "❌ PRE-WRITE VALIDATION FAILED: Found unresolved hardware components!\\n";
        foreach ($unresolved as $err) {
            echo "  - {$err}\\n";
        }
        echo "\\nAborting execution before write.\\n";
        exit(1);
    }
    echo "✓ All game requirement hardware components resolve successfully under production lookup rules.\\n------------------------------------------------------------\\n\\n";

    // 5. Preview Output
    echo "📊 PREVIEW OF GAMES & REQUIREMENTS DATA TO SEED:\\n";
    echo "------------------------------------------------------------\\n";
    foreach ($gamesSeed as $g) {
        echo "🎮 Game: {$g['name']} (Slug: {$g['slug']}, Genre: {$g['genre']})\\n";
        $min = $g['requirements']['minimum'] ?? null;
        $rec = $g['requirements']['recommended'] ?? null;
        if ($min) {
            echo "   MINIMUM REQUIREMENTS:\\n";
            echo "     CPU: {$min['cpu']}\\n";
            echo "     GPU: {$min['gpu']}\\n";
            echo "     RAM: {$min['ram_gb']}GB | Storage: {$min['storage_gb']}GB | OS: {$min['operating_system']}\\n";
            echo "     Notes / Source URL: {$min['notes']}\\n";
        }
        if ($rec) {
            echo "   RECOMMENDED REQUIREMENTS:\\n";
            echo "     CPU: {$rec['cpu']}\\n";
            echo "     GPU: {$rec['gpu']}\\n";
            echo "     RAM: {$rec['ram_gb']}GB | Storage: {$rec['storage_gb']}GB | OS: {$rec['operating_system']}\\n";
            echo "     Notes / Source URL: {$rec['notes']}\\n";
        } else {
            echo "   RECOMMENDED REQUIREMENTS: None (Official source provides minimum specs only)\\n";
        }
        echo "\\n";
    }

    if (!$isConfirmed) {
        echo "✓ Preview completed successfully. No changes made to database.\\n";
        return;
    }

    // 6. Confirmed Database Write inside PDO Transaction
    try {
        $pdo->beginTransaction();

        // Upsert Hardware Mappings
        $hStmt = $pdo->prepare("
            INSERT INTO roc_hardware_mappings (component_type, raw_name, normalized_name, tier, architecture, provenance, created_at)
            VALUES (:type, :raw, :norm, :tier, :arch, :prov, NOW())
            ON DUPLICATE KEY UPDATE
                normalized_name = VALUES(normalized_name),
                tier = VALUES(tier),
                architecture = VALUES(architecture),
                provenance = VALUES(provenance)
        ");

        $hCount = 0;
        foreach ($hardwareSeed as $h) {
            $hStmt->execute([
                ':type' => $h[0],
                ':raw' => $h[1],
                ':norm' => $h[2],
                ':tier' => $h[3],
                ':arch' => $h[4],
                ':prov' => $h[5]
            ]);
            $hCount++;
        }

        // Upsert Games and Requirements
        $gStmt = $pdo->prepare("
            INSERT INTO games (name, slug, platform, genre, image, description, created_at)
            VALUES (:name, :slug, :platform, :genre, :image, :desc, NOW())
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                platform = VALUES(platform),
                genre = VALUES(genre),
                image = VALUES(image),
                description = VALUES(description)
        ");

        $reqStmt = $pdo->prepare("
            INSERT INTO game_requirements (game_id, requirement_type, cpu, gpu, ram_gb, storage_gb, operating_system, notes, created_at)
            VALUES (:game_id, :req_type, :cpu, :gpu, :ram, :storage, :os, :notes, NOW())
            ON DUPLICATE KEY UPDATE
                cpu = VALUES(cpu),
                gpu = VALUES(gpu),
                ram_gb = VALUES(ram_gb),
                storage_gb = VALUES(storage_gb),
                operating_system = VALUES(operating_system),
                notes = VALUES(notes)
        ");

        $gCount = 0;
        $rCount = 0;
        foreach ($gamesSeed as $g) {
            $gStmt->execute([
                ':name' => $g['name'],
                ':slug' => $g['slug'],
                ':platform' => $g['platform'],
                ':genre' => $g['genre'],
                ':image' => $g['image'],
                ':desc' => $g['description']
            ]);
            $gCount++;

            // Lookup generated or existing game_id
            $findG = $pdo->prepare("SELECT id FROM games WHERE slug = ?");
            $findG->execute([$g['slug']]);
            $gameId = $findG->fetchColumn();

            if ($gameId) {
                foreach (['minimum', 'recommended'] as $reqType) {
                    $r = $g['requirements'][$reqType] ?? null;
                    if (!$r) continue;

                    $reqStmt->execute([
                        ':game_id' => $gameId,
                        ':req_type' => $reqType,
                        ':cpu' => $r['cpu'],
                        ':gpu' => $r['gpu'],
                        ':ram' => $r['ram_gb'],
                        ':storage' => $r['storage_gb'],
                        ':os' => $r['operating_system'],
                        ':notes' => $r['notes']
                    ]);
                    $rCount++;
                }
            }
        }

        // Post-Insert Verification Queries using dynamic expected requirement count
        $totalH = (int)$pdo->query("SELECT COUNT(*) FROM roc_hardware_mappings")->fetchColumn();
        $totalG = (int)$pdo->query("SELECT COUNT(*) FROM games")->fetchColumn();
        $totalR = (int)$pdo->query("SELECT COUNT(*) FROM game_requirements")->fetchColumn();

        if ($totalH < count($hardwareSeed) || $totalG < count($gamesSeed) || $totalR < $expectedRequirementCount) {
            $pdo->rollBack();
            echo "❌ Verification Error: Database record count below expected thresholds after insert.\\n";
            echo "   Total Games: {$totalG} (Expected >= " . count($gamesSeed) . ")\\n";
            echo "   Total Requirements: {$totalR} (Expected >= {$expectedRequirementCount})\\n";
            exit(1);
        }

        $pdo->commit();
        echo "============================================================\\n";
        echo "✅ SUCCESS: Games & Hardware Seeder Completed Successfully.\\n";
        echo "   - Hardware Mappings Seeded: {$hCount} (Total in DB: {$totalH})\\n";
        echo "   - Games Seeded: {$gCount} (Total in DB: {$totalG})\\n";
        echo "   - Requirements Seeded: {$rCount} (Total in DB: {$totalR})\\n";
        echo "============================================================\\n";

    } catch (\\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "❌ CRITICAL FAILURE during database write: " . $e->getMessage() . "\\n";
        exit(1);
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    rocAgentSeedGamesAndHardware();
}
`;

const pathsToUpdate = [
  { file: 'public/api/v1/cron/seed-roc-agent-games.php', content: seedGamesPhpContent },
  { file: 'dist/api/v1/cron/seed-roc-agent-games.php', content: seedGamesPhpContent }
];

for (const item of pathsToUpdate) {
  const fullPath = path.join(ROOT_DIR, item.file);
  fs.mkdirSync(path.dirname(fullPath), { recursive: true });
  fs.writeFileSync(fullPath, item.content, 'utf-8');
  console.log(`Updated: ${item.file}`);
}
