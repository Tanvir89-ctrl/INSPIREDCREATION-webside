<?php
require __DIR__ . '/_inc.php';

require_post_csrf();
$_SESSION = [];
session_destroy();
redirect('login.php');
