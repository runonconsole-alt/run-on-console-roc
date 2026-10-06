<?php
/**
 * Run On Console (ROC) - ROC Agent Verified Hardware Compatibility Engine
 * POST /api/v1/agent/check-compatibility
 *
 * Uses database-backed hardware mapping table `roc_hardware_mappings` (with provenance).
 * Returns "Unable to Determine" if required OS version, architecture, or hardware model is unknown.
 */

require_once __DIR__ . '/config-agent.php';
require_once __DIR__ . '/games.php';

function rocAgentLookupHardwareTier(string $name, string $type): ?array {
    $pdo = getDBConnection();
    if (!$pdo) return null;

    $cleanName = strtolower(trim($name));
    if ($cleanName === '') return null;

    // 1. Handle compound requirement strings with " or " / " / " alternatives
    if (preg_match('/\s+(?:or|\/)\s+/i', $cleanName)) {
        $tokens = preg_split('/\s+(?:or|\/)\s+/i', $cleanName);
        $resolvedTiers = [];
        $normalizedNames = [];
        $archs = [];
        $provenances = [];

        foreach ($tokens as $token) {
            $sub = rocAgentLookupHardwareTier($token, $type);
            if (!$sub) {
                return null; // If ANY alternative fails to resolve, return null!
            }
            $resolvedTiers[] = $sub['tier'];
            $normalizedNames[] = $sub['normalizedName'];
            $archs[] = $sub['architecture'];
            $provenances[] = $sub['provenance'];
        }

        return [
            'tier' => min($resolvedTiers),
            'normalizedName' => implode(' or ', $normalizedNames),
            'architecture' => $archs[0] ?? 'Standard',
            'provenance' => $provenances[0] ?? 'Database Verified'
        ];
    }

    try {
        // 2. Direct exact match on raw_name or normalized_name
        $stmt = $pdo->prepare("SELECT tier, normalized_name, architecture, provenance FROM roc_hardware_mappings WHERE component_type = ? AND (LOWER(raw_name) = ? OR LOWER(normalized_name) = ?) LIMIT 1");
        $stmt->execute([$type, $cleanName, $cleanName]);
        $row = $stmt->fetch();
        if ($row) {
            return [
                'tier' => (int)$row['tier'],
                'normalizedName' => $row['normalized_name'],
                'architecture' => $row['architecture'] ?? 'Standard',
                'provenance' => $row['provenance'] ?? 'Database Verified'
            ];
        }

        // 3. Secondary match stripping trailing VRAM / RAM suffixes (e.g. "gtx 1060 6gb" -> "gtx 1060")
        $cleanBase = trim(preg_replace('/\s+\d+gb$/i', '', $cleanName));
        if ($cleanBase !== '' && $cleanBase !== $cleanName) {
            $stmt->execute([$type, $cleanBase, $cleanBase]);
            $row = $stmt->fetch();
            if ($row) {
                return [
                    'tier' => (int)$row['tier'],
                    'normalizedName' => $row['normalized_name'],
                    'architecture' => $row['architecture'] ?? 'Standard',
                    'provenance' => $row['provenance'] ?? 'Database Verified'
                ];
            }
        }
    } catch (\Throwable $e) {}

    return null;
}

/**
 * Strict OS Compatibility Evaluator
 * Checks OS family, architecture (64-bit vs 32-bit), and version numbers.
 *
 * RULES:
 * - If required architecture is known (64-bit) but user architecture is missing ("Windows 10" alone), return NULL ("Unable to Determine").
 * - If required OS version is known but user OS version is missing, return NULL ("Unable to Determine").
 * - 32-bit user OS fails a 64-bit requirement (returns FALSE).
 * - Lower OS version (Windows 7) fails higher required OS version (Windows 11) (returns FALSE).
 */
function rocAgentEvaluateOs(?string $userOs, ?string $reqOs): ?bool {
    if ($userOs === null || $reqOs === null) return null;
    $u = strtolower(trim($userOs));
    $r = strtolower(trim($reqOs));
    if ($u === '' || $r === '') return null;

    // 1. Family Check
    $uIsWin = str_contains($u, 'windows') || str_contains($u, 'win');
    $rIsWin = str_contains($r, 'windows') || str_contains($r, 'win') || str_contains($r, 'pc');

    if ($rIsWin && !$uIsWin) return false;

    // 2. Architecture Check
    $rIs64 = str_contains($r, '64') || str_contains($r, 'x64');
    $rIs32 = str_contains($r, '32') || str_contains($r, 'x86');
    $uIs64 = str_contains($u, '64') || str_contains($u, 'x64');
    $uIs32 = str_contains($u, '32') || str_contains($u, 'x86');

    // If requirement requires 64-bit but user OS architecture is missing/unspecified (e.g. "Windows 10"), return NULL!
    if ($rIs64 && !$uIs64 && !$uIs32) {
        return null; // Missing user architecture -> Unable to Determine!
    }

    if ($rIs64 && $uIs32) {
        return false; // 32-bit user OS fails 64-bit requirement
    }

    // 3. Version Number Check
    $parseWinVer = function(string $str): ?float {
        if (str_contains($str, '11')) return 11.0;
        if (str_contains($str, '10')) return 10.0;
        if (str_contains($str, '8.1')) return 8.1;
        if (str_contains($str, '8')) return 8.0;
        if (str_contains($str, '7')) return 7.0;
        if (str_contains($str, 'vista')) return 6.0;
        if (str_contains($str, 'xp')) return 5.0;
        return null;
    };

    $uVer = $parseWinVer($u);
    $rVer = $parseWinVer($r);

    // If required version is known but user version is missing, return NULL!
    if ($rVer !== null && $uVer === null) {
        return null; // Missing user version -> Unable to Determine!
    }

    if ($uVer !== null && $rVer !== null) {
        if ($uVer < $rVer) {
            return false; // Windows 7 (7.0) < Windows 11 (11.0) -> FAILS!
        }
    }

    return true;
}

function rocAgentCheckCompatibility(): array {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        rocAgentJsonOutput(['success' => false, 'error' => 'POST method required.'], 400);
    }

    if (rocAgentCheckAndLogRateLimit('compat_check', 20, 60)) {
        rocAgentJsonOutput(['success' => false, 'error' => 'Too many requests. Please try again later.'], 429);
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    $gameId = rocAgentSanitizeString($input['gameId'] ?? $input['gameSlug'] ?? '');
    $userCpu = rocAgentSanitizeString($input['cpu'] ?? '');
    $userGpu = rocAgentSanitizeString($input['gpu'] ?? '');
    $userRamGb = (int)($input['ramGb'] ?? $input['ram'] ?? 0);
    $userOs = rocAgentSanitizeString($input['operatingSystem'] ?? $input['os'] ?? '');

    if ($gameId === '' || $userCpu === '' || $userGpu === '' || $userRamGb <= 0 || $userOs === '') {
        rocAgentJsonOutput(['success' => false, 'error' => 'Please select a game and provide valid CPU, GPU, RAM (GB), and Operating System.'], 400);
    }

    $gameRes = rocAgentGetGames($gameId);
    if (!$gameRes['success'] || empty($gameRes['game'])) {
        rocAgentJsonOutput(['success' => false, 'error' => 'Selected game not found in verified database.'], 404);
    }

    $game = $gameRes['game'];
    $minReq = $game['requirements']['minimum'] ?? null;
    $recReq = $game['requirements']['recommended'] ?? null;

    if (!$minReq) {
        return [
            'success' => true,
            'game' => $game,
            'overallResult' => 'Unable to Determine',
            'summaryText' => 'Unable to Determine: System requirements for this game are not configured in the database.'
        ];
    }

    // Exact Database Hardware Mapping Lookups
    $userGpuInfo = rocAgentLookupHardwareTier($userGpu, 'gpu');
    $userCpuInfo = rocAgentLookupHardwareTier($userCpu, 'cpu');
    $minGpuInfo = rocAgentLookupHardwareTier($minReq['gpu'], 'gpu');
    $minCpuInfo = rocAgentLookupHardwareTier($minReq['cpu'], 'cpu');
    $recGpuInfo = $recReq ? rocAgentLookupHardwareTier($recReq['gpu'], 'gpu') : null;
    $recCpuInfo = $recReq ? rocAgentLookupHardwareTier($recReq['cpu'], 'cpu') : null;

    // Strict Rule: If exact hardware mapping is missing from database roc_hardware_mappings, return Unable to Determine!
    if ($userGpuInfo === null || $userCpuInfo === null || $minGpuInfo === null || $minCpuInfo === null) {
        return [
            'success' => true,
            'game' => [
                'id' => $game['id'],
                'name' => $game['name'],
                'slug' => $game['slug']
            ],
            'overallResult' => 'Unable to Determine',
            'summaryText' => "Compatibility Result.\n\nHardware Verification: Unable to Determine.\nNote: Hardware models \"{$userGpu}\" or \"{$userCpu}\" lack exact database mappings in roc_hardware_mappings."
        ];
    }

    // Evaluate Minimum and Recommended OS Requirements Independently
    $minOsPass = rocAgentEvaluateOs($userOs, $minReq['os'] ?? '');
    $recOsPass = $recReq ? rocAgentEvaluateOs($userOs, $recReq['os'] ?? '') : null;

    if ($minOsPass === null) {
        return [
            'success' => true,
            'game' => [
                'id' => $game['id'],
                'name' => $game['name'],
                'slug' => $game['slug']
            ],
            'overallResult' => 'Unable to Determine',
            'summaryText' => "Compatibility Result.\n\nOS Verification: Unable to Determine.\nNote: Could not parse operating system version or architecture for \"{$userOs}\" vs game requirement."
        ];
    }

    // RAM Status
    $minRamGb = (int)($minReq['ramGb'] ?? 8);
    $recRamGb = $recReq ? (int)($recReq['ramGb'] ?? 16) : null;

    if ($recRamGb !== null && $userRamGb >= $recRamGb) {
        $ramVerdict = 'Pass.';
        $ramLevel = 2;
    } elseif ($userRamGb >= $minRamGb) {
        $ramVerdict = 'Minimum requirement met.';
        $ramLevel = 1;
    } else {
        $ramVerdict = "Below minimum (Requires {$minRamGb}GB RAM).";
        $ramLevel = 0;
    }

    // GPU Status
    if ($recGpuInfo !== null && $userGpuInfo['tier'] >= $recGpuInfo['tier']) {
        $gpuVerdict = 'Pass.';
        $gpuLevel = 2;
    } elseif ($userGpuInfo['tier'] >= $minGpuInfo['tier']) {
        $gpuVerdict = 'Minimum requirement met.';
        $gpuLevel = 1;
    } else {
        $gpuVerdict = "Below minimum requirement.";
        $gpuLevel = 0;
    }

    // CPU Status
    if ($recCpuInfo !== null && $userCpuInfo['tier'] >= $recCpuInfo['tier']) {
        $cpuVerdict = 'Pass.';
        $cpuLevel = 2;
    } elseif ($userCpuInfo['tier'] >= $minCpuInfo['tier']) {
        $cpuVerdict = 'Minimum requirement met.';
        $cpuLevel = 1;
    } else {
        $cpuVerdict = "Below minimum requirement.";
        $cpuLevel = 0;
    }

    // OS Status
    $osVerdict = $minOsPass ? 'Pass.' : "Below minimum (Requires {$minReq['os']}).";

    // Overall Verdict
    if ($ramLevel >= 1 && $gpuLevel >= 1 && $cpuLevel >= 1 && $minOsPass) {
        if ($recReq && $ramLevel === 2 && $gpuLevel === 2 && $cpuLevel === 2 && ($recOsPass ?? true)) {
            $overall = 'Recommended Requirements Met';
        } else {
            $overall = 'Minimum Requirements Met';
        }
    } else {
        $overall = 'Below Minimum Requirements';
    }

    $summaryText = "Compatibility Result.\n\n" .
        "GPU: " . ($gpuLevel === 2 ? 'Pass.' : ($gpuLevel === 1 ? 'Minimum requirement met.' : 'Fail.')) . "\n" .
        "RAM: " . ($ramLevel === 2 ? 'Pass.' : ($ramLevel === 1 ? 'Minimum requirement met.' : 'Fail.')) . "\n" .
        "CPU: " . ($cpuLevel === 2 ? 'Pass.' : ($cpuLevel === 1 ? 'Minimum requirement met.' : 'Fail.')) . "\n" .
        "OS: " . ($minOsPass ? 'Pass.' : 'Fail.') . "\n\n" .
        "Overall: " . $overall . ".";

    return [
        'success' => true,
        'game' => [
            'id' => $game['id'],
            'name' => $game['name'],
            'slug' => $game['slug']
        ],
        'userSpecs' => [
            'cpu' => $userCpuInfo['normalizedName'],
            'gpu' => $userGpuInfo['normalizedName'],
            'ramGb' => $userRamGb,
            'operatingSystem' => $userOs
        ],
        'provenance' => [
            'cpuProvenance' => $userCpuInfo['provenance'],
            'gpuProvenance' => $userGpuInfo['provenance']
        ],
        'componentResults' => [
            'gpu' => ['status' => $gpuVerdict, 'level' => $gpuLevel],
            'ram' => ['status' => $ramVerdict, 'level' => $ramLevel],
            'cpu' => ['status' => $cpuVerdict, 'level' => $cpuLevel],
            'os' => ['status' => $osVerdict, 'pass' => $minOsPass]
        ],
        'overallResult' => $overall,
        'summaryText' => $summaryText
    ];
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    rocAgentJsonOutput(rocAgentCheckCompatibility());
}
