<?php
require_once __DIR__ . '/config.php';
header('Location: ' . (isLoggedIn() ? '/dashboard' : '/login'));
exit;
