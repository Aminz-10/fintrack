<?php
require_once __DIR__ . '/includes/auth.php';
Auth::logout();
header('Location: ' . url('login.php'));
exit;
