<?php
// Action: login.php

if (!empty($_SESSION['user_id']) && !empty($_SESSION['logged_in'])) {
    header('Location: /mrs/be/index.php?action=dashboard');
    exit;
}

require_once MRS_VIEW_PATH . '/login.php';
