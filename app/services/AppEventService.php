<?php

class AppEventService {
    private $pdo;
    private $tableChecked = false;
    private $tableAvailable = false;

    public function __construct($pdo = null) {
        $this->pdo = $pdo;
    }

    public function log(string $eventType, array $payload = [], ?int $userId = null, string $severity = 'info', ?string $entityType = null, ?string $entityId = null): void {
        if (!$this->isAvailable()) {
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO app_events (user_id, event_type, severity, entity_type, entity_id, payload_json, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $userId,
                $eventType,
                $severity,
                $entityType,
                $entityId,
                !empty($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null
            ]);
        } catch (Exception $e) {
            error_log('AppEventService log error: ' . $e->getMessage());
        }
    }

    public function logApiError(string $source, string $message, array $context = []): void {
        $payload = array_merge(['source' => $source, 'message' => $message], $context);
        $this->log('external_api_error', $payload, null, 'error', 'api', $source);
    }

    public function resolveUserIdFromRequest(): ?int {
        if (!$this->pdo) {
            return null;
        }

        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        $token = null;

        if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
        } elseif (!empty($_COOKIE['urban_auth_token'])) {
            $token = $_COOKIE['urban_auth_token'];
        }

        if (!$token) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT user_id
                FROM user_sessions
                WHERE session_token = ? AND expires_at > NOW()
                LIMIT 1
            ");
            $stmt->execute([$token]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (int)$row['user_id'] : null;
        } catch (Exception $e) {
            return null;
        }
    }

    private function isAvailable(): bool {
        if ($this->tableChecked) {
            return $this->tableAvailable;
        }

        $this->tableChecked = true;
        if (!$this->pdo) {
            return false;
        }

        try {
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'app_events'");
            $this->tableAvailable = $stmt && $stmt->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (Exception $e) {
            $this->tableAvailable = false;
        }

        return $this->tableAvailable;
    }
}
