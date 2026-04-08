<?php
// app/services/RaptorRouter.php

class RaptorRouter {
    private $pdo;
    private $stopsCache = [];

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Encontra itinerários entre duas paragens usando RAPTOR.
     *
     * @param string $fromStopId
     * @param string $toStopId
     * @param string $departureTime Formato H:i:s
     * @param string $date Formato YYYY-MM-DD
     * @param int $maxRounds Número máximo de transferências (0 = directo)
     * @return array Lista de itinerários ordenados por tempo total
     */
    public function findItineraries($fromStopId, $toStopId, $departureTime, $date, $maxRounds = 4) {
        $serviceId = $this->getServiceIdForDate($date);
        if (!$serviceId) return [];

        // Estruturas de suporte
        $bestArrival = [];            // stopId => melhor timestamp de chegada
        $bestRoute = [];              // stopId => dados do itinerário (para reconstrução)
        $currentStops = [$fromStopId];
        $bestArrival[$fromStopId] = strtotime($departureTime);
        $itineraries = [];

        for ($round = 0; $round <= $maxRounds; $round++) {
            // Buscar todas as viagens que partem das paragens actuais depois da hora de chegada
            $trips = $this->getTripsFromStops($currentStops, $bestArrival, $serviceId);
            if (empty($trips)) break;

            $newStops = [];

            foreach ($trips as $trip) {
                $tripId = $trip['trip_id'];
                $routeId = $trip['route_id'];
                $routeName = $trip['route_short_name'];
                $boardingStop = $trip['boarding_stop'];

                // Obter todas as paragens da trip com horários de chegada
                $stops = $this->getStopsForTrip($tripId);
                $foundBoarding = false;
                $segment = [];

                foreach ($stops as $stop) {
                    if (!$foundBoarding && $stop['stop_id'] == $boardingStop) {
                        $foundBoarding = true;
                        $segment[] = $stop;
                        continue;
                    }
                    if ($foundBoarding) {
                        $segment[] = $stop;
                        $arrivalTimestamp = strtotime($stop['arrival_time']);

                        if (!isset($bestArrival[$stop['stop_id']]) || $arrivalTimestamp < $bestArrival[$stop['stop_id']]) {
                            $bestArrival[$stop['stop_id']] = $arrivalTimestamp;
                            $bestRoute[$stop['stop_id']] = [
                                'segment' => $segment,
                                'route' => $routeName,
                                'trip_id' => $tripId,
                                'prev_stop' => $boardingStop,
                                'round' => $round
                            ];
                            $newStops[] = $stop['stop_id'];
                        }
                    }
                }
            }

            // Se o destino foi alcançado neste round, guardar o itinerário
            if (isset($bestRoute[$toStopId])) {
                $itineraries[] = $this->buildItinerary($bestRoute, $toStopId, $bestArrival[$fromStopId]);
            }

            $currentStops = array_unique($newStops);
        }

        // Ordenar por tempo total (menor primeiro)
        usort($itineraries, fn($a, $b) => $a['total_time'] <=> $b['total_time']);
        return $itineraries;
    }

    private function getServiceIdForDate($date) {
        // Verificar calendar_dates (exception_type = 1 adiciona serviço)
        $stmt = $this->pdo->prepare("SELECT service_id FROM calendar_dates WHERE date = ? AND exception_type = 1 LIMIT 1");
        $stmt->execute([$date]);
        $row = $stmt->fetch();
        if ($row) return $row['service_id'];

        $weekday = strtolower(date('l', strtotime($date)));
        $stmt = $this->pdo->prepare("SELECT service_id FROM calendar WHERE start_date <= ? AND end_date >= ? AND $weekday = 1 LIMIT 1");
        $stmt->execute([$date, $date]);
        $row = $stmt->fetch();
        return $row ? $row['service_id'] : null;
    }

    private function getTripsFromStops($stops, $bestArrival, $serviceId) {
        $placeholders = implode(',', array_fill(0, count($stops), '?'));
        $minTime = date('H:i:s', min($bestArrival));
        $sql = "
            SELECT DISTINCT st.trip_id, st.stop_id AS boarding_stop, st.departure_time,
                   t.route_id, r.route_short_name
            FROM stop_times st
            JOIN trips t ON st.trip_id = t.trip_id
            JOIN routes r ON t.route_id = r.route_id
            WHERE st.stop_id IN ($placeholders)
              AND t.service_id = ?
              AND st.departure_time > ?
            ORDER BY st.departure_time
        ";
        $params = array_merge($stops, [$serviceId, $minTime]);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function getStopsForTrip($tripId) {
        if (isset($this->stopsCache[$tripId])) {
            return $this->stopsCache[$tripId];
        }
        $stmt = $this->pdo->prepare("SELECT stop_id, arrival_time FROM stop_times WHERE trip_id = ? ORDER BY stop_sequence");
        $stmt->execute([$tripId]);
        $stops = $stmt->fetchAll();
        $this->stopsCache[$tripId] = $stops;
        return $stops;
    }

    private function buildItinerary($bestRoute, $toStopId, $startTimestamp) {
        $segments = [];
        $current = $toStopId;
        while (isset($bestRoute[$current])) {
            array_unshift($segments, $bestRoute[$current]['segment']);
            $current = $bestRoute[$current]['prev_stop'];
        }
        $arrivalTimestamp = $bestRoute[$toStopId]['segment'][count($bestRoute[$toStopId]['segment'])-1]['arrival_time'];
        $arrivalTime = strtotime($arrivalTimestamp);
        $totalMinutes = round(($arrivalTime - $startTimestamp) / 60);
        return [
            'segments' => $segments,
            'total_time' => $totalMinutes,
            'transfers' => count($segments) - 1
        ];
    }
}