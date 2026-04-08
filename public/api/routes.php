<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../app/controllers/RouteController.php';
require_once __DIR__ . '/../../app/models/StopModel.php';

header('Content-Type: application/json');

$controller = new RouteController();
$controller->search();