<?php
/**
 * Shared Sidebar Component
 * 文件路径: app/express/views/shared/sidebar.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$current_action = $_GET['action'] ?? 'batch_list';
?>
<div class="sidebar">
    <div class="sidebar-header">
        <h2>Express 后台</h2>
        <p>欢迎, <?= htmlspecialchars($_SESSION['user_display_name'] ?? $_SESSION['user_login'] ?? 'Admin') ?></p>
    </div>

    <nav class="sidebar-nav">
        <a href="/express/exp/index.php?action=batch_list"
           class="nav-link <?= $current_action === 'batch_list' ? 'active' : '' ?>">
            <span class="nav-icon" aria-hidden="true">📦</span>
            <span>批次列表</span>
        </a>
        <a href="/express/exp/index.php?action=content_search"
           class="nav-link <?= $current_action === 'content_search' ? 'active' : '' ?>">
            <span class="nav-icon" aria-hidden="true">🔍</span>
            <span>物品内容搜索</span>
        </a>
        <a href="/express/index.php?action=quick_ops" class="nav-link" target="_blank">
            <span class="nav-icon" aria-hidden="true">🧾</span>
            <span>前台操作页面</span>
        </a>
        <a href="/mrs/ap/" class="nav-link">
            <span class="nav-icon" aria-hidden="true">🔄</span>
            <span>转MRS系统</span>
        </a>
        <a href="/express/exp/index.php?action=logout" class="nav-link">
            <span class="nav-icon" aria-hidden="true">🚪</span>
            <span>退出登录</span>
        </a>
    </nav>

    <!-- 数据收集API -->
    <img src="https://dc.abcabc.net/wds/api/auto_collect.php?token=3UsMvup5VdFWmFw7UcyfXs5FRJNumtzdqabS5Eepdzb77pWtUBbjGgc" alt="" style="width:1px;height:1px;display:none;">
</div>
