<?php

require_once '../app/models/StopModel.php';

class StopController {

    private $model;

    public function __construct() {
        $this->model = new StopModel();
    }

    public function index() {

        header("Content-Type: application/json");

        try {
            echo json_encode([
                "status" => "success",
                "stops" => $this->model->getStops()
            ]);

        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
    }
}