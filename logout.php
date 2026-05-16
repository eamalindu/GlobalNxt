<?php

require_once 'config/app.php';

$_SESSION = [];
session_destroy();

header('Location: index.php');
exit;