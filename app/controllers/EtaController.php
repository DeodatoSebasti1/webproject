<?php

require_once '../app/models/EtaModel.php';

class EtaController {

    private $model;

    public function __construct() {
        $this->model = new EtaModel();
    }

    public function get() {

        header("Content-Type: application/json");

        $stopId = $_GET['stop_id'] ?? null;

        if (!$stopId) {
            http_response_code(400);
            echo json_encode(["error" => "stop_id obrigatório"]);
            return;
        }

        try {
            echo json_encode([
                "status" => "success",
                "arrivals" => $this->model->getEtas($stopId)
            ]);

        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
    }
}