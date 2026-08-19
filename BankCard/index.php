<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once MODULE_ROOT . '/Controllers/CardController.php';

$username = (string) ($_SESSION['username'] ?? 'admin');
$controller = new CardController($conn, $username);
$controller->handle();
