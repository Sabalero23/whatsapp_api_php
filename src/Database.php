<?php
/**
 * Clase Database para gestión de base de datos
 */
class Database
{
    private static $instance = null;
    private $pdo;
    
    private function __construct()
    {
        $envFile = __DIR__ . '/../.env';
        $config = $this->loadEnv($envFile);
        
        // Leer desde .env o usar valores por defecto
        $host = $config['DB_HOST'] ?? '127.0.0.1';
        $dbname = $config['DB_NAME'] ?? 'whatsapp_db';
        $username = $config['DB_USER'] ?? 'whatsapp_db';
        $password = $config['DB_PASSWORD'] ?? 'b2Byp8e3WwaipXJ4';
        
        try {
            $this->pdo = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            // Log del error con más detalles para debugging
            error_log("Error de conexión a BD: " . $e->getMessage());
            error_log("Intentando conectar a: mysql:host=$host;dbname=$dbname con usuario=$username");
            die('Error de conexión a la base de datos. Verifica la configuración en .env');
        }
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    public function fetch(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    public function insert(string $table, array $data): int
    {
        $fields = array_keys($data);
        $values = array_values($data);
        $placeholders = array_fill(0, count($fields), '?');
        
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table,
            implode(', ', $fields),
            implode(', ', $placeholders)
        );
        
        $this->query($sql, $values);
        return (int) $this->pdo->lastInsertId();
    }
    
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $fields = [];
        foreach (array_keys($data) as $field) {
            $fields[] = "$field = ?";
        }
        
        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $table,
            implode(', ', $fields),
            $where
        );
        
        $params = array_merge(array_values($data), $whereParams);
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM $table WHERE $where";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }
    
    public function commit(): bool
    {
        return $this->pdo->commit();
    }
    
    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }
    
    private function loadEnv(string $path): array
    {
        $env = [];
        if (!file_exists($path)) {
            error_log("Archivo .env no encontrado en: $path");
            return $env;
        }
        
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Ignorar comentarios
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') === false) continue;
            
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remover comillas si existen
            $value = trim($value, '"\'');
            
            $env[$key] = $value;
        }
        
        return $env;
    }
    
    public function getPDO(): PDO
    {
        return $this->pdo;
    }
    
    /**
     * Método helper para obtener la configuración cargada
     */
    public function getConfig(): array
    {
        $envFile = __DIR__ . '/../.env';
        return $this->loadEnv($envFile);
    }
}