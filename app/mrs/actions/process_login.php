<?php
// Action: process_login.php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /mrs/be/index.php?action=login');
    exit;
}

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    header('Location: /mrs/be/index.php?action=login&error=1');
    exit;
}

$user = authenticate_user($username, $password);

if ($user) {
    session_regenerate_id(true);
    create_user_session($user);
    header('Location: /mrs/be/index.php?action=dashboard');
    exit;
} else {
    header('Location: /mrs/be/index.php?action=login&error=1');
    exit;
}
