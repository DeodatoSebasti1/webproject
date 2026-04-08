<?php

require_once '../app/models/VehicleModel.php';

class VehicleController {

    private $model;

    public function __construct() {
        $this->model = new VehicleModel();
    }

    public function index() {

        header("Content-Type: application/json");

        try {
            echo json_encode([
                "status" => "success",
                "buses" => $this->model->getVehicles(),
                "timestamp" => date('c')
            ]);

        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
    }
}