<?php
/**
 * Clase Auth para gestión de autenticación y permisos
 */
class Auth
{
    private static $instance = null;
    private $db;
    private $userId;
    private $userPermissions = [];
    private $userRole = null;
    
    private function __construct()
    {
        $this->db = Database::getInstance();
        
        if (isset($_SESSION['user_id'])) {
            $this->userId = $_SESSION['user_id'];
            $this->loadUserPermissions();
        }
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Cargar permisos del usuario actual
     */
    private function loadUserPermissions(): void
    {
        if (!$this->userId) return;
        
        // Obtener permisos del usuario a través de su rol
        $permissions = $this->db->fetchAll("
            SELECT DISTINCT
                p.nombre,
                p.hash,
                p.modulo
            FROM usuarios u
            JOIN roles r ON u.rol_id = r.id
            JOIN rol_permisos rp ON r.id = rp.rol_id
            JOIN permisos p ON rp.permiso_id = p.id
            WHERE u.id = ? AND u.activo = 1 AND r.activo = 1
        ", [$this->userId]);
        
        foreach ($permissions as $perm) {
            $this->userPermissions[$perm['nombre']] = [
                'hash' => $perm['hash'],
                'modulo' => $perm['modulo']
            ];
        }
        
        // Cargar rol del usuario
        $user = $this->db->fetch("
            SELECT r.nombre as rol_nombre, r.id as rol_id
            FROM usuarios u
            JOIN roles r ON u.rol_id = r.id
            WHERE u.id = ?
        ", [$this->userId]);
        
        if ($user) {
            $this->userRole = [
                'id' => $user['rol_id'],
                'nombre' => $user['rol_nombre']
            ];
            $_SESSION['rol'] = $user['rol_nombre']; // Mantener compatibilidad
        }
    }
    
    /**
     * Verificar si el usuario tiene un permiso específico
     */
    public function hasPermission(string $permissionName): bool
    {
        return isset($this->userPermissions[$permissionName]);
    }
    
    /**
     * Verificar si el usuario tiene acceso a un módulo
     */
    public function hasModuleAccess(string $module): bool
    {
        foreach ($this->userPermissions as $perm) {
            if ($perm['modulo'] === $module) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Verificar múltiples permisos (requiere todos)
     */
    public function hasAllPermissions(array $permissions): bool
    {
        foreach ($permissions as $perm) {
            if (!$this->hasPermission($perm)) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Verificar múltiples permisos (requiere al menos uno)
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $perm) {
            if ($this->hasPermission($perm)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Obtener todos los permisos del usuario
     */
    public function getUserPermissions(): array
    {
        return $this->userPermissions;
    }
    
    /**
     * Obtener rol del usuario
     */
    public function getUserRole(): ?array
    {
        return $this->userRole;
    }
    
    /**
     * Verificar si el usuario es administrador
     */
    public function isAdmin(): bool
    {
        return $this->userRole && $this->userRole['nombre'] === 'Administrador';
    }
    
    /**
     * Requerir permiso o redirigir
     */
    public static function requirePermission(string $permission): void
    {
        $auth = self::getInstance();
        
        if (!$auth->hasPermission($permission)) {
            http_response_code(403);
            header('Location: index.php?page=dashboard&error=no_permission');
            exit;
        }
    }
    
    /**
     * Requerir acceso a módulo o redirigir
     */
    public static function requireModuleAccess(string $module): void
    {
        $auth = self::getInstance();
        
        if (!$auth->hasModuleAccess($module)) {
            http_response_code(403);
            header('Location: index.php?page=dashboard&error=no_permission');
            exit;
        }
    }
    
    /**
     * Obtener mapeo de páginas a permisos requeridos
     */
    public static function getPagePermissions(): array
    {
        return [
            'dashboard' => ['view_dashboard'],
            'chats' => ['view_chats'],
            'status' => ['view_status'],
            'contacts' => ['view_contacts'],
            'groups' => ['view_groups'],
            'broadcast' => ['view_broadcast'],
            'templates' => ['view_templates'],
            'auto-reply' => ['view_auto_reply'],
            'stats' => ['view_stats'],
            'settings' => ['view_settings'],
            'users' => ['view_users'],
            'qr-connect' => ['view_qr_connect']
        ];
    }
    
    /**
     * Verificar si el usuario puede ver una página
     */
    public function canViewPage(string $page): bool
    {
        $pagePermissions = self::getPagePermissions();
        
        if (!isset($pagePermissions[$page])) {
            return false; // Página no definida
        }
        
        $requiredPerms = $pagePermissions[$page];
        
        // Verificar si tiene al menos uno de los permisos requeridos
        return $this->hasAnyPermission($requiredPerms);
    }
    
    /**
     * Obtener páginas permitidas para el sidebar
     */
    public function getAllowedPages(): array
    {
        $allPages = self::getPagePermissions();
        $allowedPages = [];
        
        foreach ($allPages as $page => $perms) {
            if ($this->hasAnyPermission($perms)) {
                $allowedPages[] = $page;
            }
        }
        
        return $allowedPages;
    }
    
    /**
     * Obtener todos los roles disponibles
     */
    public static function getAllRoles(): array
    {
        $db = Database::getInstance();
        return $db->fetchAll("
            SELECT id, nombre, descripcion, es_sistema, activo
            FROM roles
            WHERE activo = 1
            ORDER BY 
                CASE nombre 
                    WHEN 'Administrador' THEN 1 
                    WHEN 'Operador' THEN 2 
                    WHEN 'Visor' THEN 3 
                    ELSE 4 
                END,
                nombre
        ");
    }
    
    /**
     * Obtener todos los permisos disponibles
     */
    public static function getAllPermissions(): array
    {
        $db = Database::getInstance();
        return $db->fetchAll("
            SELECT id, nombre, descripcion, modulo, hash
            FROM permisos
            ORDER BY modulo, nombre
        ");
    }
    
    /**
     * Obtener permisos de un rol específico
     */
    public static function getRolePermissions(int $roleId): array
    {
        $db = Database::getInstance();
        return $db->fetchAll("
            SELECT p.id, p.nombre, p.descripcion, p.modulo, p.hash
            FROM permisos p
            JOIN rol_permisos rp ON p.id = rp.permiso_id
            WHERE rp.rol_id = ?
            ORDER BY p.modulo, p.nombre
        ", [$roleId]);
    }
    
    /**
     * Crear un nuevo rol
     */
    public static function createRole(string $nombre, string $descripcion, array $permissionIds = []): int
    {
        $db = Database::getInstance();
        
        $db->beginTransaction();
        
        try {
            // Crear rol
            $roleId = $db->insert('roles', [
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'es_sistema' => 0,
                'activo' => 1
            ]);
            
            // Asignar permisos
            if (!empty($permissionIds)) {
                foreach ($permissionIds as $permId) {
                    $db->insert('rol_permisos', [
                        'rol_id' => $roleId,
                        'permiso_id' => $permId
                    ]);
                }
            }
            
            $db->commit();
            return $roleId;
            
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Actualizar permisos de un rol
     */
    public static function updateRolePermissions(int $roleId, array $permissionIds): bool
    {
        $db = Database::getInstance();
        
        $db->beginTransaction();
        
        try {
            // Eliminar permisos actuales
            $db->delete('rol_permisos', 'rol_id = ?', [$roleId]);
            
            // Agregar nuevos permisos
            foreach ($permissionIds as $permId) {
                $db->insert('rol_permisos', [
                    'rol_id' => $roleId,
                    'permiso_id' => $permId
                ]);
            }
            
            $db->commit();
            return true;
            
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Eliminar un rol (solo si no es del sistema)
     */
    public static function deleteRole(int $roleId): bool
    {
        $db = Database::getInstance();
        
        // Verificar que no sea rol del sistema
        $role = $db->fetch("SELECT es_sistema FROM roles WHERE id = ?", [$roleId]);
        
        if ($role && $role['es_sistema'] == 1) {
            throw new Exception('No se puede eliminar un rol del sistema');
        }
        
        // Verificar que no tenga usuarios asignados
        $usersCount = $db->fetch("SELECT COUNT(*) as total FROM usuarios WHERE rol_id = ?", [$roleId]);
        
        if ($usersCount['total'] > 0) {
            throw new Exception('No se puede eliminar un rol que tiene usuarios asignados');
        }
        
        $db->delete('roles', 'id = ?', [$roleId]);
        return true;
    }
    
    /**
     * Log de actividad del usuario
     */
    public function logActivity(string $action, string $description, array $data = []): void
    {
        if (!$this->userId) return;
        
        $this->db->insert('logs', [
            'usuario_id' => $this->userId,
            'accion' => $action,
            'descripcion' => $description,
            'datos' => !empty($data) ? json_encode($data) : null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
        ]);
    }
}