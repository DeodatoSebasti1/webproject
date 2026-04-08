<?php

require_once '../app/services/CarrisApiService.php';

class VehicleModel {

    private $carris;

    public function __construct() {
        $this->carris = new CarrisApiService();
    }

    public function getVehicles() {
        return $this->carris->getVehicles();
    }
}