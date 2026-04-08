<?php

require_once '../app/services/CarrisApiService.php';

class LineModel {

    private $carris;

    public function __construct() {
        $this->carris = new CarrisApiService();
    }

    public function getLines() {
        return $this->carris->getLines();
    }

    public function getLineStops($patternId) {
        $pattern = $this->carris->getPattern($patternId);
        return $pattern['path'] ?? [];
    }
}