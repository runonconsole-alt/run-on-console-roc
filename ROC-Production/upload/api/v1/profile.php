<?php
/**
 * Run On Console (ROC) - User Profile & Preference REST API (v1)
 * Handles: Profile Update, User Preferences, Email Change, Password Change, Wishlist, Price Alerts, Comments, Data Export, Account Deletion
 */

require_once __DIR__ . '/account-runtime.php';
require_once __DIR__ . '/gaming-profile.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$pdo = getDBConnection();
if (!$pdo) {
    handleDatabaseUnavailable();
}

// DATABASE SESSION VERIFICATION
if (!validateDatabaseSession()) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "UNAUTHORIZED", "message" => "Valid authentication session required."]);
    exit();
}

$userId = (int) $_SESSION['user_id'];

// Fetch Current Authenticated User & Profile
$stmt = $pdo->prepare("SELECT u.*, p.country, p.currency, p.avatar_icon, p.avatar_bg, p.bio FROM users u LEFT JOIN user_profiles p ON u.id = p.user_id WHERE u.id = ? LIMIT 1");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();

if (!$currentUser || $currentUser['status'] === 'suspended') {
    unset($_SESSION['user_id']);
    unset($_SESSION['db_session_id']);
    unset($_SESSION['db_session_token']);
    session_destroy();
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Account invalid or suspended."]);
    exit();
}

// ----------------------------------------------------
// 1. GET PROFILE DATA (GET)
// ----------------------------------------------------
if ($action === 'get-profile') {
    $stmt = $pdo->prepare("SELECT product_id, created_at FROM saved_products WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $savedProducts = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT id, product_id, target_price, status, created_at FROM price_alerts WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $priceAlerts = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT id, article_id, product_id, comment_text, status, created_at FROM user_comments WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $comments = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT theme, email_notifications, price_alert_thresholds FROM user_preferences WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $preferences = $stmt->fetch() ?: ["theme" => "dark", "email_notifications" => 1, "price_alert_thresholds" => null];

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "profile" => [
            "id" => $currentUser['uuid'] ?: "usr-" . $currentUser['id'],
            "name" => $currentUser['name'],
            "username" => $currentUser['username'],
            "gaming" => rocGamingProfile($pdo, $userId),
            "email" => $currentUser['email'],
            "role" => $currentUser['role'],
            "country" => $currentUser['country'] ?: '',
            "currency" => $currentUser['currency'] ?: 'USD',
            "avatarIcon" => (in_array($currentUser['avatar_icon'] ?? '', ROC_VALID_AVATARS, true)) ? $currentUser['avatar_icon'] : 'avatar_01',
            "avatarBg" => $currentUser['avatar_bg'] ?: 'from-emerald-600 to-teal-500',
            "bio" => $currentUser['bio'] ?: '',
            "isVerified" => (bool) $currentUser['is_verified'],
            "status" => $currentUser['status'],
            "createdAt" => $currentUser['created_at'],
            "savedProducts" => array_column($savedProducts, 'product_id'),
            "priceAlerts" => $priceAlerts,
            "comments" => $comments,
            "preferences" => $preferences
        ]
    ]);
    exit();
}

// ----------------------------------------------------
// ENFORCE POST METHOD FOR ALL STATE-CHANGING PROFILE ENDPOINTS
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed. POST required."]);
    exit();
}

// ----------------------------------------------------
// ENFORCE CSRF VALIDATION ON ALL STATE-CHANGING POST REQUESTS
// ----------------------------------------------------
if (!validateCsrfToken()) {
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "Invalid or missing CSRF token."]);
    exit();
}

// ----------------------------------------------------
// SERVER-SIDE VERIFICATION REQUIREMENT FOR PROTECTED ACTIONS
// ----------------------------------------------------
if (!$currentUser['is_verified']) {
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "error" => "UNVERIFIED",
        "message" => "Your email address must be verified before performing this action."
    ]);
    exit();
}

// ----------------------------------------------------
// 2. UPDATE PROFILE DETAILS
// ----------------------------------------------------
if ($action === 'update-gaming-profile') {
    try {
        rocSaveGaming($pdo,$userId,$input);
        rocRespond(200,['success'=>true,'message'=>'Gaming profile saved.']);
    } catch (InvalidArgumentException $e) {
        rocRespond(422,['success'=>false,'error'=>$e->getMessage()]);
    } catch (Throwable $e) {
        error_log('[ROC gaming profile] code='.$e->getCode());
        rocRespond(500,['success'=>false,'error'=>'Unable to save profile. Please try again.']);
    }
}

if ($action === 'update-profile') {
    $name = trim($input['name'] ?? '');
    $country = trim($input['country'] ?? 'United States');
    $currency = trim($input['currency'] ?? 'USD');
    $rawAvatar = trim($input['avatarIcon'] ?? 'avatar_01');
    if (!in_array($rawAvatar, ROC_VALID_AVATARS, true)) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Invalid esports avatar selection. Please choose a valid avatar from the gallery."]);
        exit();
    }
    $avatarIcon = $rawAvatar;
    $bio = trim($input['bio'] ?? '');

    if (empty($name)) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Name cannot be empty."]);
        exit();
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
        $stmt->execute([$name, $userId]);

        $stmt = $pdo->prepare("INSERT INTO user_profiles (user_id, country, currency, avatar_icon, bio) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE country = VALUES(country), currency = VALUES(currency), avatar_icon = VALUES(avatar_icon), bio = VALUES(bio)");
        $stmt->execute([$userId, $country, $currency, $avatarIcon, $bio]);

        $pdo->commit();

        http_response_code(200);
        echo json_encode(["success" => true, "message" => "Profile updated successfully!"]);
        exit();

    } catch (\Throwable $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "Failed to update profile."]);
        exit();
    }
}

// ----------------------------------------------------
// 3. GET / UPDATE USER PREFERENCES
// ----------------------------------------------------
if ($action === 'update-preferences') {
    $theme = trim($input['theme'] ?? 'dark');
    $emailNotifications = !empty($input['emailNotifications']) ? 1 : 0;

    $stmt = $pdo->prepare("INSERT INTO user_preferences (user_id, theme, email_notifications) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE theme = VALUES(theme), email_notifications = VALUES(email_notifications)");
    $stmt->execute([$userId, $theme, $emailNotifications]);

    http_response_code(200);
    echo json_encode(["success" => true, "message" => "Preferences saved."]);
    exit();
}

// ----------------------------------------------------
// 4. CHANGE EMAIL WITH RE-VERIFICATION
// ----------------------------------------------------
if ($action === 'change-email') {
    $newEmail = strtolower(trim($input['newEmail'] ?? ''));
    $currentPassword = $input['currentPassword'] ?? '';

    if (empty($newEmail) || empty($currentPassword)) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "New email and current password are required."]);
        exit();
    }

    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        http_response_code(422);
        echo json_encode(["success" => false, "error" => "Invalid email address format."]);
        exit();
    }

    if (!password_verify($currentPassword, $currentUser['password_hash'])) {
        http_response_code(401);
        echo json_encode(["success" => false, "error" => "Incorrect current password."]);
        exit();
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$newEmail, $userId]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(["success" => false, "error" => "This email address is already in use."]);
        exit();
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE users SET email=?,email_verified=0,is_verified=0,status='pending_verification' WHERE id=?")->execute([$newEmail,$userId]);
        $u=rocUserById($pdo,$userId);
        $queueId=rocIssueChallenge($pdo,$u);
        $pdo->prepare("DELETE FROM sessions WHERE user_id=?")->execute([$userId]);
        // An OAuth identity bound to the old email must be explicitly linked again.
        $pdo->prepare("DELETE FROM oauth_accounts WHERE user_id=?")->execute([$userId]);
        $pdo->commit();
        $_SESSION=[]; session_regenerate_id(true);
        rocRespond(200,['success'=>true,'message'=>'Email updated. Check your new inbox, then verify before signing in.'],$queueId);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        rocRespond(500,['success'=>false,'error'=>'Email change failed.']);
    }
}

// ----------------------------------------------------
// 5. CHANGE PASSWORD
// ----------------------------------------------------
if ($action === 'change-password') {
    $currentPassword = $input['currentPassword'] ?? '';
    $newPassword = $input['newPassword'] ?? '';
    $confirmPassword = $input['confirmPassword'] ?? '';

    if (empty($currentPassword) || empty($newPassword)) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Current password and new password are required."]);
        exit();
    }

    if (strlen($newPassword) < 8 || strlen($newPassword) > 72) {
        http_response_code(422);
        echo json_encode(["success" => false, "error" => "New password must be at least 8 characters long."]);
        exit();
    }

    if ($newPassword !== $confirmPassword) {
        http_response_code(422);
        echo json_encode(["success" => false, "error" => "New passwords do not match."]);
        exit();
    }

    if (!password_verify($currentPassword, $currentUser['password_hash'])) {
        http_response_code(401);
        echo json_encode(["success" => false, "error" => "Incorrect current password."]);
        exit();
    }

    $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    $newHash = password_hash($newPassword, $algo);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$newHash, $userId]);
        $pdo->prepare("UPDATE roc_auth_challenges SET consumed_at=NOW() WHERE user_id=? AND purpose='reset' AND consumed_at IS NULL")->execute([$userId]);

        $stmt = $pdo->prepare("DELETE FROM sessions WHERE user_id = ?");
        $stmt->execute([$userId]);

        $pdo->commit();

        unset($_SESSION['user_id']);
        unset($_SESSION['db_session_id']);
        unset($_SESSION['db_session_token']);
        $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        setcookie(session_name(), '', time() - 3600, '/', '', $isSecure, true);
        session_destroy();

        http_response_code(200);
        echo json_encode(["success" => true, "message" => "Password changed successfully! Please log in with your new password."]);
        exit();

    } catch (\Throwable $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "Password change failed."]);
        exit();
    }
}

// ----------------------------------------------------
// 6. TOGGLE SAVED PRODUCT (Wishlist)
// ----------------------------------------------------
if ($action === 'toggle-save-product') {
    $productId = trim($input['productId'] ?? '');

    if (empty($productId)) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Product ID is required."]);
        exit();
    }

    $stmt = $pdo->prepare("SELECT id FROM saved_products WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$userId, $productId]);
    $exists = $stmt->fetch();

    if ($exists) {
        $stmt = $pdo->prepare("DELETE FROM saved_products WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$userId, $productId]);
        $saved = false;
    } else {
        $stmt = $pdo->prepare("INSERT INTO saved_products (user_id, product_id) VALUES (?, ?)");
        $stmt->execute([$userId, $productId]);
        $saved = true;
    }

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "saved" => $saved,
        "message" => $saved ? "Product saved to your wishlist." : "Product removed from your wishlist."
    ]);
    exit();
}

// ----------------------------------------------------
// 7. PRICE ALERTS MANAGEMENT
// ----------------------------------------------------
if ($action === 'create-price-alert') {
    $productId = trim($input['productId'] ?? '');
    $targetPrice = (float) ($input['targetPrice'] ?? 0);

    if (empty($productId) || $targetPrice <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Valid product ID and target price are required."]);
        exit();
    }

    $stmt = $pdo->prepare("INSERT INTO price_alerts (user_id, product_id, target_price, status) VALUES (?, ?, ?, 'active')");
    $stmt->execute([$userId, $productId, $targetPrice]);

    http_response_code(201);
    echo json_encode(["success" => true, "message" => "Price alert created successfully!"]);
    exit();
}

if ($action === 'delete-price-alert') {
    $alertId = (int) ($input['alertId'] ?? 0);

    $stmt = $pdo->prepare("DELETE FROM price_alerts WHERE id = ? AND user_id = ?");
    $stmt->execute([$alertId, $userId]);

    http_response_code(200);
    echo json_encode(["success" => true, "message" => "Price alert deleted."]);
    exit();
}

// ----------------------------------------------------
// 8. SUBMIT COMMENT
// ----------------------------------------------------
if ($action === 'add-comment') {
    $articleId = trim($input['articleId'] ?? '');
    $productId = trim($input['productId'] ?? '');
    $commentText = trim($input['commentText'] ?? '');

    if (empty($commentText)) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Comment text cannot be empty."]);
        exit();
    }

    $stmt = $pdo->prepare("INSERT INTO user_comments (user_id, article_id, product_id, comment_text, status) VALUES (?, ?, ?, ?, 'pending_moderation')");
    $stmt->execute([$userId, $articleId ?: null, $productId ?: null, $commentText]);

    http_response_code(201);
    echo json_encode([
        "success" => true,
        "message" => "Comment submitted! It will appear after editorial moderation."
    ]);
    exit();
}

// ----------------------------------------------------
// 9. EXPORT DATA (POST)
// ----------------------------------------------------
if ($action === 'export-data') {
    $stmt = $pdo->prepare("SELECT product_id, created_at FROM saved_products WHERE user_id = ?");
    $stmt->execute([$userId]);
    $savedProducts = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT product_id, target_price, status, created_at FROM price_alerts WHERE user_id = ?");
    $stmt->execute([$userId]);
    $priceAlerts = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT article_id, product_id, comment_text, status, created_at FROM user_comments WHERE user_id = ?");
    $stmt->execute([$userId]);
    $comments = $stmt->fetchAll();

    $exportPayload = [
        "export_date" => date('Y-m-d H:i:s'),
        "user_account" => [
            "uuid" => $currentUser['uuid'],
            "name" => $currentUser['name'],
            "email" => $currentUser['email'],
            "role" => $currentUser['role'],
            "status" => $currentUser['status'],
            "created_at" => $currentUser['created_at'],
            "country" => $currentUser['country'],
            "currency" => $currentUser['currency'],
            "bio" => $currentUser['bio']
        ],
        "gaming_profile" => rocGamingProfile($pdo, $userId),
        "saved_products" => $savedProducts,
        "price_alerts" => $priceAlerts,
        "comments" => $comments
    ];

    header("Content-Disposition: attachment; filename=runonconsole-data-export.json");
    http_response_code(200);
    echo json_encode($exportPayload, JSON_PRETTY_PRINT);
    exit();
}

// ----------------------------------------------------
// 10. DELETE ACCOUNT (POST - Password Verified)
// ----------------------------------------------------
if ($action === 'delete-account') {
    $currentPassword = $input['currentPassword'] ?? '';

    if (empty($currentPassword)) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Current password is required to confirm account deletion."]);
        exit();
    }

    if (!password_verify($currentPassword, $currentUser['password_hash'])) {
        http_response_code(401);
        echo json_encode(["success" => false, "error" => "Incorrect current password."]);
        exit();
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $pdo->prepare('DELETE FROM roc_auth_challenges WHERE user_id=?')->execute([$userId]);
        $pdo->prepare('DELETE FROM roc_gaming_profiles WHERE user_id=?')->execute([$userId]);
        $stmt->execute([$userId]);
        $pdo->commit();

        unset($_SESSION['user_id']);
        unset($_SESSION['db_session_id']);
        unset($_SESSION['db_session_token']);
        $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        setcookie(session_name(), '', time() - 3600, '/', '', $isSecure, true);
        session_destroy();

        http_response_code(200);
        echo json_encode(["success" => true, "message" => "Your account and all associated data have been permanently deleted."]);
        exit();

    } catch (\Throwable $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "Account deletion failed."]);
        exit();
    }
}

http_response_code(400);
echo json_encode(["success" => false, "error" => "Invalid profile action."]);
exit();
