<?php
// /urban/app/controllers/AuthController.php

// Iniciar sessão PHP
session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../services/AppEventService.php';

if (!defined('URBAN_SKIP_AUTH_ROUTER')) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    // Handle preflight OPTIONS request
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        exit(0);
    }
}

class AuthController {
    private $conn;
    private $eventLogger;
    private $usersHasRoleColumn = false;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
        $this->eventLogger = new AppEventService($conn);
        $this->usersHasRoleColumn = $this->detectUsersRoleColumn();
    }
    
    /**
     * Registar novo utilizador
     */
    public function register() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['email']) || !isset($data['password']) || !isset($data['name'])) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Dados incompletos. Email, password e name são obrigatórios.'
            ]);
            return;
        }
        
        $email = $this->normalizeEmail($data['email'] ?? '');
        $password = (string)($data['password'] ?? '');
        $name = $this->normalizeName($data['name'] ?? '');
        
        // Validações básicas
        if (!$this->isValidEmail($email)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Email inválido.'
            ]);
            return;
        }
        
        if (!$this->isValidPassword($password)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Password deve ter entre 6 e 72 caracteres.'
            ]);
            return;
        }
        
        if (!$this->isValidName($name)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Nome deve ter entre 2 e 80 caracteres.'
            ]);
            return;
        }
        
        try {
            // Verificar se a conexão está ativa
            if (!$this->conn || !($this->conn instanceof PDO)) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Erro de conexão com a base de dados. Por favor, tente novamente.'
                ]);
                return;
            }
            
            // Verificar se a tabela users existe
            $tables = $this->conn->query("SHOW TABLES LIKE 'users'")->fetchAll();
            if (empty($tables)) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Base de dados não configurada. Contacte o administrador.'
                ]);
                error_log("AuthController: Tabela 'users' não existe");
                return;
            }
            
            // Verificar se email já existe
            $stmt = $this->conn->prepare("SELECT id FROM users WHERE email = ?");
            if (!$stmt) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Erro ao preparar consulta. Tente novamente.'
                ]);
                return;
            }
            
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Este email já está registado.'
                ]);
                return;
            }
            
            // Criar utilizador
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            if ($this->usersHasRoleColumn) {
                $stmt = $this->conn->prepare("
                    INSERT INTO users (email, password_hash, name, role) 
                    VALUES (?, ?, ?, 'user')
                ");
            } else {
                $stmt = $this->conn->prepare("
                    INSERT INTO users (email, password_hash, name) 
                    VALUES (?, ?, ?)
                ");
            }
            
            if (!$stmt) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Erro ao preparar inserção. Tente novamente.'
                ]);
                return;
            }
            
            $stmt->execute([$email, $passwordHash, $name]);
            
            $userId = $this->conn->lastInsertId();
            
            if (!$userId) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Erro ao obter ID do utilizador. Tente novamente.'
                ]);
                return;
            }
            
            // Criar sessão
            $sessionToken = $this->createSession($userId);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Utilizador criado com sucesso.',
                'user' => [
                    'id' => $userId,
                    'email' => $email,
                    'name' => $name,
                    'role' => 'user'
                ],
                'token' => $sessionToken
            ]);
            $this->eventLogger->log('user_registered', [
                'email' => $email,
                'name' => $name
            ], (int)$userId, 'success', 'user', (string)$userId);
            
        } catch (PDOException $e) {
            error_log("AuthController PDO error: " . $e->getMessage());
            
            // Erros específicos de PDO
            if (strpos($e->getMessage(), 'Table') !== false && strpos($e->getMessage(), "doesn't exist") !== false) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Base de dados não configurada. Execute o setup primeiro.'
                ]);
            } elseif (strpos($e->getMessage(), 'Connection') !== false || strpos($e->getMessage(), 'server has gone away') !== false) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Erro de conexão com a base de dados. Tente novamente em alguns segundos.'
                ]);
            } elseif (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Este email já está registado.'
                ]);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Erro na base de dados. Tente novamente.'
                ]);
            }
        } catch (Exception $e) {
            error_log("AuthController general error: " . $e->getMessage());
            echo json_encode([
                'status' => 'error',
                'message' => 'Erro ao criar utilizador. Tente novamente.'
            ]);
        }
    }
    
    /**
     * Login de utilizador
     */
    public function login() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['email']) || !isset($data['password'])) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Email e password são obrigatórios.'
            ]);
            return;
        }
        
        $email = $this->normalizeEmail($data['email'] ?? '');
        $password = (string)($data['password'] ?? '');

        if (!$this->isValidEmail($email)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Email inválido.'
            ]);
            return;
        }

        if (!$this->isValidPassword($password)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Password inválida.'
            ]);
            return;
        }
        
        try {
            $roleSelect = $this->usersHasRoleColumn ? ', role' : '';
            $stmt = $this->conn->prepare("
                SELECT id, email, password_hash, name, last_login {$roleSelect}
                FROM users 
                WHERE email = ? AND is_active = 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || !password_verify($password, $user['password_hash'])) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Email ou password incorretos.'
                ]);
                $this->eventLogger->log('login_failed', [
                    'email' => $email
                ], null, 'warning', 'auth');
                return;
            }
            
            // Atualizar último login
            $stmt = $this->conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            // Criar sessão
            $sessionToken = $this->createSession($user['id']);
            $sessionUser = [
                'id' => (int)$user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'role' => $user['role'] ?? 'user'
            ];
            $this->storeUserInSession($sessionUser);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Login realizado com sucesso.',
                'user' => [
                    'id' => $sessionUser['id'],
                    'email' => $sessionUser['email'],
                    'name' => $sessionUser['name'],
                    'last_login' => $user['last_login'],
                    'role' => $sessionUser['role']
                ],
                'token' => $sessionToken
            ]);
            $this->eventLogger->log('login_success', [
                'email' => $user['email']
            ], (int)$user['id'], 'success', 'auth', (string)$user['id']);
            
        } catch (Exception $e) {
            error_log("AuthController login error: " . $e->getMessage());
            echo json_encode([
                'status' => 'error',
                'message' => 'Erro ao fazer login. Tente novamente.'
            ]);
        }
    }
    
    /**
     * Verificar sessão (middleware)
     */
    public function verifySession($emitJsonErrors = true) {
        $token = $this->resolveBearerToken();

        if (!$token) {
            $sessionUser = $this->getUserFromSession();
            if ($sessionUser) {
                return $sessionUser;
            }
        }
        
        if (!$token) {
            if ($emitJsonErrors) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Token de autenticação não fornecido.'
                ]);
            }
            return null;
        }
        
        try {
            $roleSelect = $this->usersHasRoleColumn ? ', u.role' : '';
            $stmt = $this->conn->prepare("
                SELECT s.user_id, u.email, u.name, s.expires_at {$roleSelect}
                FROM user_sessions s
                JOIN users u ON s.user_id = u.id
                WHERE s.session_token = ? AND s.expires_at > NOW() AND u.is_active = 1
            ");
            $stmt->execute([$token]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session) {
                if ($emitJsonErrors) {
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Sessão inválida ou expirada.'
                    ]);
                }
                return null;
            }
            
            $sessionUser = [
                'id' => $session['user_id'],
                'email' => $session['email'],
                'name' => $session['name'],
                'role' => $session['role'] ?? 'user'
            ];
            $this->storeUserInSession($sessionUser);

            return $sessionUser;
            
        } catch (Exception $e) {
            error_log("AuthController verifySession error: " . $e->getMessage());
            if ($emitJsonErrors) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Erro ao verificar sessão.'
                ]);
            }
            return null;
        }
    }
    
    /**
     * Logout
     */
    public function logout() {
        $user = $this->verifySession();
        
        if (!$user) {
            return;
        }
        
        try {
            $token = $this->resolveBearerToken();

            if ($token) {
                $stmt = $this->conn->prepare("DELETE FROM user_sessions WHERE session_token = ?");
                $stmt->execute([$token]);
            }

            $this->clearUserSession();
            setcookie('urban_auth_token', '', time() - 3600, '/');
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Logout realizado com sucesso.'
            ]);
            $this->eventLogger->log('logout', [], (int)$user['id'], 'info', 'auth', (string)$user['id']);
            
        } catch (Exception $e) {
            error_log("AuthController logout error: " . $e->getMessage());
            echo json_encode([
                'status' => 'error',
                'message' => 'Erro ao fazer logout.'
            ]);
        }
    }
    
    /**
     * Obter perfil do utilizador
     */
    public function getProfile() {
        $user = $this->verifySession();
        
        if (!$user) {
            return;
        }
        
        try {
            $roleSelect = $this->usersHasRoleColumn ? ', role' : '';
            $stmt = $this->conn->prepare("
                SELECT id, email, name, created_at, last_login {$roleSelect}
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$user['id']]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($userData) {
                $this->storeUserInSession([
                    'id' => (int)$userData['id'],
                    'email' => $userData['email'],
                    'name' => $userData['name'],
                    'role' => $userData['role'] ?? 'user'
                ]);
            }
            
            echo json_encode([
                'status' => 'success',
                'user' => $userData
            ]);
            
        } catch (Exception $e) {
            error_log("AuthController getProfile error: " . $e->getMessage());
            echo json_encode([
                'status' => 'error',
                'message' => 'Erro ao obter perfil.'
            ]);
        }
    }
    
    /**
     * Criar sessão de utilizador
     */
    private function createSession($userId) {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO user_sessions (user_id, session_token, expires_at, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $token, $expiresAt, $ipAddress, $userAgent]);
            
            return $token;
            
        } catch (Exception $e) {
            error_log("AuthController createSession error: " . $e->getMessage());
            throw new Exception('Erro ao criar sessão.');
        }
    }

    private function resolveBearerToken() {
        $headers = [];

        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            foreach ($_SERVER as $key => $value) {
                if (strpos($key, 'HTTP_') !== 0) {
                    continue;
                }

                $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$headerName] = $value;
            }

            if (isset($_SERVER['CONTENT_TYPE'])) {
                $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
            }

            if (isset($_SERVER['CONTENT_LENGTH'])) {
                $headers['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
            }
        }

        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        $cookieToken = $_COOKIE['urban_auth_token'] ?? '';
        if (is_string($cookieToken) && trim($cookieToken) !== '') {
            return trim($cookieToken);
        }

        return null;
    }

    private function normalizeEmail($email): string {
        return strtolower(trim((string)$email));
    }

    private function normalizeName($name): string {
        return preg_replace('/\s+/', ' ', trim((string)$name)) ?? '';
    }

    private function isValidEmail(string $email): bool {
        return $email !== '' && strlen($email) <= 120 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function isValidPassword(string $password): bool {
        $length = strlen($password);
        return $length >= 6 && $length <= 72;
    }

    private function isValidName(string $name): bool {
        $length = mb_strlen($name);
        return $length >= 2 && $length <= 80;
    }

    private function detectUsersRoleColumn(): bool {
        try {
            $stmt = $this->conn->query("SHOW COLUMNS FROM users LIKE 'role'");
            return $stmt && $stmt->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    private function storeUserInSession(array $user): void {
        $_SESSION['user'] = [
            'id' => (int)($user['id'] ?? 0),
            'email' => (string)($user['email'] ?? ''),
            'name' => (string)($user['name'] ?? ''),
            'role' => (string)($user['role'] ?? 'user')
        ];
    }

    private function getUserFromSession(): ?array {
        $user = $_SESSION['user'] ?? null;
        if (!is_array($user)) {
            return null;
        }

        if (empty($user['id']) || empty($user['email']) || empty($user['name'])) {
            return null;
        }

        return [
            'id' => (int)$user['id'],
            'email' => (string)$user['email'],
            'name' => (string)$user['name'],
            'role' => (string)($user['role'] ?? 'user')
        ];
    }

    private function clearUserSession(): void {
        unset($_SESSION['user']);
    }
}

if (!defined('URBAN_SKIP_AUTH_ROUTER') || !URBAN_SKIP_AUTH_ROUTER) {
    // Router
    $action = $_GET['action'] ?? '';
    $auth = new AuthController();

    switch ($action) {
        case 'register':
            $auth->register();
            break;
        case 'login':
            $auth->login();
            break;
        case 'logout':
            $auth->logout();
            break;
        case 'profile':
            $auth->getProfile();
            break;
        case 'verify':
            $user = $auth->verifySession();
            if ($user) {
                echo json_encode([
                    'status' => 'success',
                    'user' => $user
                ]);
            }
            break;
        default:
            echo json_encode([
                'status' => 'error',
                'message' => 'Ação inválida.'
            ]);
    }
}
