<?php

declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: *");
    exit(0);
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-type");
header("Access-Control-Allow-Headers: *");

require_once __DIR__ . '/vendor/autoload.php';

use App\Controllers\MoveLeads;

$controller = new MoveLeads();
$controller->handle();
