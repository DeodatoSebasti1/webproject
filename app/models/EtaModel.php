<?php

require_once '../app/services/CarrisApiService.php';

class EtaModel {

    private $carris;

    public function __construct() {
        $this->carris = new CarrisApiService();
    }

    public function getEtas($stopId) {
        return $this->carris->getStopEtas($stopId);
    }
}