<?php
require_once __DIR__ . '/includes/auth.php';
header('Location: ' . url(Auth::check() ? 'dashboard.php' : 'login.php'));
exit;
