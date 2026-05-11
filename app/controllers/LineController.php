<?php

require_once __DIR__ . '/../models/LineModel.php';

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
            echo json_encode([
                "status" => "error",
                "message" => $e->getMessage()
            ]);
        }
    }

    public function stops() {

        header("Content-Type: application/json");

        $patternId = $_GET['pattern_id'] ?? null;
        $routeId = $_GET['route_id'] ?? null;

        if (!$patternId && !$routeId) {
            http_response_code(400);
            echo json_encode(["error" => "pattern_id ou route_id obrigatório"]);
            return;
        }

        try {
            if ($routeId) {
                $details = $this->model->getLineStopsByRoute($routeId);
                echo json_encode([
                    "status" => "success",
                    "stops" => $details['stops'],
                    "coordinates" => $details['coordinates'],
                    "shape_id" => $details['shape_id'] ?? null,
                    "geometry_source" => $details['geometry_source'] ?? 'stops'
                ]);
                return;
            }

            echo json_encode([
                "status" => "success",
                "stops" => $this->model->getLineStops($patternId)
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => $e->getMessage()
            ]);
        }
    }
}
