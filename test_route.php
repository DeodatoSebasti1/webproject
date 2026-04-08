<?php

require_once "config/database.php";
require_once "services/routeService.php";

$service = new RouteService($conn);

$result = $service->findShortestPath(1, 5);

echo "<pre>";
print_r($result);


echo "hello";