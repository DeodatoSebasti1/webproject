<?php

class CarrisApiService {

    private string $baseUrl = 'https://api.carrismetropolitana.pt';

    public function get(string $path): mixed {
        $url = $this->baseUrl . $path;
        $ctx = stream_context_create(['http' => [
            'timeout'       => 10,
            'ignore_errors' => true,
            'header'        => "Accept: application/json\r\nUser-Agent: UrbanTraffic/1.0\r\n"
        ]]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) throw new RuntimeException("Erro ao contactar API Carris: $url");
        $data = json_decode($raw, true);
        if ($data === null) throw new RuntimeException("Resposta inválida da API Carris");
        return $data;
    }

    public function getStops(): array             { return $this->get('/stops'); }
    public function getStop(string $id): array    { return $this->get("/stops/{$id}"); }
    public function getStopEtas(string $id): array { return $this->get("/stops/{$id}/realtime"); }
    public function getLines(): array             { return $this->get('/lines'); }
    public function getLine(string $id): array    { return $this->get("/lines/{$id}"); }
    public function getVehicles(): array          { return $this->get('/vehicles'); }
    public function getPattern(string $id): array { return $this->get("/patterns/{$id}"); }
}