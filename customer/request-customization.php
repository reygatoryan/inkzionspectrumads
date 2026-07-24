<?php
require_once __DIR__ . '/../includes/session-helper.php';
secureSessionStart();
header('Location: request-form.php');
exit;
