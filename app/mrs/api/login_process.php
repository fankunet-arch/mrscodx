<?php
/**
 * MRS 物料收发管理系统 - 登录处理
 * 文件路径: app/mrs/api/login_process.php
 * 说明: 处理用户登录请求
 */

// 防止直接访问
if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 加载配置
require_once __DIR__ . '/../config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';

try {
    // 只接受POST请求
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: login.php?error=invalid');
        exit;
    }

    // 获取表单数据
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) && $_POST['remember'] === '1';

    // 验证输入
    if (empty($username) || empty($password)) {
        header('Location: login.php?error=invalid');
        exit;
    }

    // 防止暴力破解:检查登录尝试次数
    start_secure_session();
    $loginAttempts = $_SESSION['login_attempts'] ?? 0;
    $lastAttemptTime = $_SESSION['last_attempt_time'] ?? 0;

    // 如果5分钟内尝试次数超过5次,拒绝登录
    if ($loginAttempts >= 5 && (time() - $lastAttemptTime) < 300) {
        mrs_log("登录失败: 尝试次数过多 - {$username}", 'WARNING', [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        header('Location: login.php?error=too_many_attempts');
        exit;
    }

    // 验证用户
    $user = authenticate_user($username, $password);

    if ($user === false) {
        // 登录失败,记录尝试次数
        $_SESSION['login_attempts'] = $loginAttempts + 1;
        $_SESSION['last_attempt_time'] = time();

        mrs_log("登录失败: 用户名或密码错误 - {$username}", 'WARNING', [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'attempts' => $_SESSION['login_attempts']
        ]);

        header('Location: login.php?error=invalid');
        exit;
    }

    // 登录成功,创建会话
    create_user_session($user);

    // 清除登录尝试计数
    unset($_SESSION['login_attempts']);
    unset($_SESSION['last_attempt_time']);

    // 如果选择了"记住我",设置长期cookie(30天)
    if ($remember) {
        $rememberToken = bin2hex(random_bytes(32));
        // 这里可以将token存储到数据库,实现真正的"记住我"功能
        // 为简化,这里只是设置一个标记
        setcookie('remember_me', $rememberToken, time() + (86400 * 30), '/');
    }

    mrs_log("登录成功: {$username}", 'INFO', [
        'user_id' => $user['user_id'],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);

    // 跳转到后台首页
    header('Location: backend.php');
    exit;

} catch (Exception $e) {
    mrs_log('登录处理异常: ' . $e->getMessage(), 'ERROR');
    header('Location: login.php?error=system');
    exit;
}
