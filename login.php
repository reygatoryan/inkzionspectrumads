<?php
require_once __DIR__ . '/includes/session-helper.php';
secureSessionStart();
$_SESSION['flash'] = ['type' => 'info', 'message' => 'Please sign in with Google to continue.'];
header('Location: index.php');
exit;
