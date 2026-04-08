<?php

require_once '../app/models/LineModel.php';

class LineController {

    private $model;

    public function __construct() {
        $this->model = new LineModel();
    }

    public function index() {

        header("Content-Type: application/json");

        try {
            echo json_encode([
                "status" => "success",
                "lines" => $this->model->getLines()
            ]);

        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    public function stops() {

        header("Content-Type: application/json");

        $patternId = $_GET['pattern_id'] ?? null;

        if (!$patternId) {
            http_response_code(400);
            echo json_encode(["error" => "pattern_id obrigatório"]);
            return;
        }

        echo json_encode([
            "status" => "success",
            "stops" => $this->model->getLineStops($patternId)
        ]);
    }
}