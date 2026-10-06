<?php
/**
 * Run On Console (ROC) - ROC Agent Saved User Devices Endpoint
 * GET  /api/v1/agent/devices
 * POST /api/v1/agent/devices (action: add | edit | delete | set_default)
 */

require_once __DIR__ . '/config-agent.php';

function rocAgentHandleDevices(): array {
    $userId = rocAgentGetUserId();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $pdo = getDBConnection();

    if (!$pdo) {
        rocAgentJsonOutput(['success' => false, 'error' => 'Database connection unavailable.'], 503);
    }

    if ($method === 'GET') {
        if (rocAgentCheckAndLogRateLimit('read_devices', 60, 60)) {
            rocAgentJsonOutput(['success' => false, 'error' => 'Rate limit exceeded. Please try again later.'], 429);
        }

        if ($userId === null) {
            return [
                'success' => true,
                'isGuest' => true,
                'devices' => [],
                'message' => 'Sign in to save and manage your gaming hardware devices permanently.'
            ];
        }

        $stmt = $pdo->prepare("SELECT id, device_name, device_type, cpu, gpu, ram_gb, storage, operating_system, resolution, is_default FROM roc_user_devices WHERE user_id = ? ORDER BY is_default DESC, id DESC");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll() ?: [];

        $devices = array_map(function($d) {
            return [
                'id' => (string)$d['id'],
                'device_name' => $d['device_name'],
                'device_type' => $d['device_type'],
                'cpu' => $d['cpu'],
                'gpu' => $d['gpu'],
                'ram_gb' => (int)$d['ram_gb'],
                'storage' => $d['storage'],
                'operating_system' => $d['operating_system'],
                'resolution' => $d['resolution'],
                'is_default' => (int)$d['is_default']
            ];
        }, $rows);

        return [
            'success' => true,
            'isGuest' => false,
            'devices' => $devices
        ];
    }

    if ($method === 'POST') {
        if ($userId === null) {
            rocAgentJsonOutput(['success' => false, 'error' => 'Authentication required to save devices.'], 401);
        }

        if (!rocAgentValidateCsrf()) {
            rocAgentJsonOutput(['success' => false, 'error' => 'Invalid or missing CSRF token.'], 403);
        }

        if (rocAgentCheckAndLogRateLimit('devices_post', 20, 60)) {
            rocAgentJsonOutput(['success' => false, 'error' => 'Too many device requests. Try again later.'], 429);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $action = strtolower(trim((string)($input['action'] ?? 'add')));

        if ($action === 'add') {
            $name = rocAgentSanitizeString($input['deviceName'] ?? 'Gaming PC', 120);
            $type = rocAgentSanitizeString($input['deviceType'] ?? 'Desktop PC', 60);
            $cpu = rocAgentSanitizeString($input['cpu'] ?? '', 150);
            $gpu = rocAgentSanitizeString($input['gpu'] ?? '', 150);
            $ramGb = max(2, (int)($input['ramGb'] ?? 8));
            $storage = rocAgentSanitizeString($input['storage'] ?? '512GB SSD', 100);
            $os = rocAgentSanitizeString($input['operatingSystem'] ?? 'Windows 11', 100);
            $res = rocAgentSanitizeString($input['resolution'] ?? '1080p', 50);
            $isDefault = !empty($input['isDefault']) ? 1 : 0;

            if ($name === '' || $cpu === '' || $gpu === '') {
                rocAgentJsonOutput(['success' => false, 'error' => 'Device name, CPU, and GPU are required.'], 400);
            }

            $pdo->beginTransaction();
            try {
                if ($isDefault) {
                    $pdo->prepare("UPDATE roc_user_devices SET is_default = 0 WHERE user_id = ?")->execute([$userId]);
                }

                $stmt = $pdo->prepare("INSERT INTO roc_user_devices (user_id, device_name, device_type, cpu, gpu, ram_gb, storage, operating_system, resolution, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $name, $type, $cpu, $gpu, $ramGb, $storage, $os, $res, $isDefault]);
                $deviceId = (string)$pdo->lastInsertId();
                $pdo->commit();

                return ['success' => true, 'message' => 'Device saved successfully.', 'deviceId' => $deviceId];
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                rocAgentJsonOutput(['success' => false, 'error' => 'Failed to save device.'], 500);
            }
        }

        if ($action === 'edit') {
            $deviceId = (string)($input['deviceId'] ?? '');
            $name = rocAgentSanitizeString($input['deviceName'] ?? 'Gaming PC', 120);
            $type = rocAgentSanitizeString($input['deviceType'] ?? 'Desktop PC', 60);
            $cpu = rocAgentSanitizeString($input['cpu'] ?? '', 150);
            $gpu = rocAgentSanitizeString($input['gpu'] ?? '', 150);
            $ramGb = max(2, (int)($input['ramGb'] ?? 8));
            $storage = rocAgentSanitizeString($input['storage'] ?? '512GB SSD', 100);
            $os = rocAgentSanitizeString($input['operatingSystem'] ?? 'Windows 11', 100);

            $chk = $pdo->prepare("SELECT id FROM roc_user_devices WHERE id = ? AND user_id = ? LIMIT 1");
            $chk->execute([$deviceId, $userId]);
            if (!$chk->fetch()) {
                rocAgentJsonOutput(['success' => false, 'error' => 'Forbidden: Device not found or access denied.'], 403);
            }

            $stmt = $pdo->prepare("UPDATE roc_user_devices SET device_name = ?, device_type = ?, cpu = ?, gpu = ?, ram_gb = ?, storage = ?, operating_system = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$name, $type, $cpu, $gpu, $ramGb, $storage, $os, $deviceId, $userId]);

            return ['success' => true, 'message' => 'Device updated successfully.'];
        }

        if ($action === 'delete') {
            $deviceId = (string)($input['deviceId'] ?? '');

            $chk = $pdo->prepare("SELECT id FROM roc_user_devices WHERE id = ? AND user_id = ? LIMIT 1");
            $chk->execute([$deviceId, $userId]);
            if (!$chk->fetch()) {
                rocAgentJsonOutput(['success' => false, 'error' => 'Forbidden: Device not found or access denied.'], 403);
            }

            $stmt = $pdo->prepare("DELETE FROM roc_user_devices WHERE id = ? AND user_id = ?");
            $stmt->execute([$deviceId, $userId]);

            return ['success' => true, 'message' => 'Device deleted successfully.'];
        }

        if ($action === 'set_default') {
            $deviceId = (string)($input['deviceId'] ?? '');

            $chk = $pdo->prepare("SELECT id FROM roc_user_devices WHERE id = ? AND user_id = ? LIMIT 1");
            $chk->execute([$deviceId, $userId]);
            if (!$chk->fetch()) {
                rocAgentJsonOutput(['success' => false, 'error' => 'Forbidden: Device not found or access denied.'], 403);
            }

            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE roc_user_devices SET is_default = 0 WHERE user_id = ?")->execute([$userId]);
                $pdo->prepare("UPDATE roc_user_devices SET is_default = 1 WHERE id = ? AND user_id = ?")->execute([$deviceId, $userId]);
                $pdo->commit();

                return ['success' => true, 'message' => 'Default device updated.'];
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                rocAgentJsonOutput(['success' => false, 'error' => 'Failed to set default device.'], 500);
            }
        }
    }

    rocAgentJsonOutput(['success' => false, 'error' => 'Method not allowed.'], 405);
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    rocAgentJsonOutput(rocAgentHandleDevices());
}
