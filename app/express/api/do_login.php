<?php
/**
 * API: Process Login
 * 文件路径: app/express/api/do_login.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    express_json_response(false, null, '非法请求方式');
}

// 判断请求是否期望JSON响应
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
$isJsonRequest = stripos($contentType, 'application/json') !== false || stripos($acceptHeader, 'application/json') !== false;

$input = express_get_json_input();
if (!$input) {
    $input = $_POST;
}

$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';
$remember = !empty($input['remember']);

if ($username === '' || $password === '') {
    if ($isJsonRequest) {
        express_json_response(false, null, '用户名或密码不能为空');
    }
    header('Location: /express/exp/index.php?action=login&error=invalid');
    exit;
}

express_start_secure_session();

$loginAttempts = $_SESSION['login_attempts'] ?? 0;
$lastAttemptTime = $_SESSION['last_attempt_time'] ?? 0;

if ($loginAttempts >= 5 && (time() - $lastAttemptTime) < 300) {
    express_log("登录失败: 尝试次数过多 - {$username}", 'WARNING', [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    if ($isJsonRequest) {
        express_json_response(false, null, '尝试次数过多，请稍后再试');
    }
    header('Location: /express/exp/index.php?action=login&error=too_many_attempts');
    exit;
}

$user = express_authenticate_user($pdo, $username, $password);

if ($user === false) {
    $_SESSION['login_attempts'] = $loginAttempts + 1;
    $_SESSION['last_attempt_time'] = time();

    express_log("登录失败: 用户名或密码错误 - {$username}", 'WARNING', [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'attempts' => $_SESSION['login_attempts']
    ]);

    if ($isJsonRequest) {
        express_json_response(false, null, '用户名或密码错误');
    }
    header('Location: /express/exp/index.php?action=login&error=invalid');
    exit;
}

express_create_user_session($user);

unset($_SESSION['login_attempts']);
unset($_SESSION['last_attempt_time']);

if ($remember) {
    $rememberToken = bin2hex(random_bytes(32));
    setcookie('express_remember_me', $rememberToken, time() + (86400 * 30), '/');
}

express_log("登录成功: {$username}", 'INFO', [
    'user_id' => $user['user_id'],
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
]);

if ($isJsonRequest) {
    express_json_response(true, ['redirect' => '/express/exp/index.php?action=batch_list'], '登录成功');
}

header('Location: /express/exp/index.php?action=batch_list');
exit;
