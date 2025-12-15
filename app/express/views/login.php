<?php
/**
 * Backend Login Page (SaaS style)
 * 文件路径: app/express/views/login.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$message = '';
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'invalid':
            $message = '用户名或密码错误';
            break;
        case 'too_many_attempts':
            $message = '尝试次数过多，请稍后再试';
            break;
        case 'system':
            $message = '系统错误，请稍后再试';
            break;
        case 'logout':
            $message = '已安全注销';
            break;
        default:
            $message = '登录失败，请重试';
    }
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SaaS 登录 - EXPRESS 系统</title>
    <link rel="stylesheet" href="/express/css/login-v2.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="split-container">
        <div class="marketing-panel">
            <div class="marketing-content">
                <h1>提升您的工作效率</h1>
                <p>一个平台，管理您的所有数据和协作流程。安全、快速、可靠。</p>
                <div class="feature-list">
                    <div><i class="fas fa-chart-line"></i> 实时数据分析</div>
                    <div><i class="fas fa-users"></i> 无缝团队协作</div>
                    <div><i class="fas fa-lock"></i> 顶级安全保障</div>
                </div>
            </div>
        </div>

        <div class="login-panel">
            <div class="login-card">
                <div class="logo-top">EXPRESS</div>
                <h2>欢迎登录</h2>
                <p class="subtitle">使用您的账户访问系统。</p>

                <?php if (!empty($message)): ?>
                    <div class="alert"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>

                <form action="/express/exp/index.php?action=do_login" method="post">
                    <div class="input-group">
                        <input type="text" id="username" name="username" placeholder=" " required autofocus>
                        <label for="username">登录账号</label>
                    </div>

                    <div class="input-group">
                        <input type="password" id="password" name="password" placeholder=" " required>
                        <label for="password">密码</label>
                    </div>

                    <div class="options">
                        <!-- 保留布局，未来可接入“忘记密码”链接 -->
                    </div>

                    <button type="submit" class="login-button">
                        登录系统
                    </button>

                    <div class="divider">
                        <span>或使用</span>
                    </div>

                    <button type="button" class="social-button google-btn" disabled>
                        <i class="fa-solid fa-circle-info"></i> 没其他办法了
                    </button>
                </form>

                <div class="register-link"></div>
            </div>
        </div>
    </div>
</body>
</html>
