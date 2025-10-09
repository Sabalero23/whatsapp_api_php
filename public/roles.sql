-- ============================================
-- SISTEMA DE ROLES Y PERMISOS
-- ============================================

-- Tabla de permisos (acciones sobre páginas)
CREATE TABLE IF NOT EXISTS permisos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE COMMENT 'Ej: view_chats, manage_contacts',
    descripcion TEXT,
    modulo VARCHAR(50) NOT NULL COMMENT 'Ej: chats, contacts, users',
    hash VARCHAR(64) NOT NULL UNIQUE COMMENT 'Identificador único del permiso',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de roles
CREATE TABLE IF NOT EXISTS roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion TEXT,
    es_sistema TINYINT(1) DEFAULT 0 COMMENT '1=rol del sistema (no se puede eliminar)',
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relación roles-permisos (muchos a muchos)
CREATE TABLE IF NOT EXISTS rol_permisos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rol_id INT UNSIGNED NOT NULL,
    permiso_id INT UNSIGNED NOT NULL,
    fecha_asignacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_rol_permiso (rol_id, permiso_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Modificar tabla usuarios para usar roles nuevos
-- Primero verificamos si existe la columna rol_id, si no, la agregamos
SET @col_exists = (SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'usuarios' 
    AND COLUMN_NAME = 'rol_id');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE usuarios ADD COLUMN rol_id INT UNSIGNED NULL AFTER email',
    'SELECT "Column rol_id already exists" AS info');
    
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Agregar foreign key si no existe
SET @fk_exists = (SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'usuarios' 
    AND COLUMN_NAME = 'rol_id' 
    AND REFERENCED_TABLE_NAME = 'roles');

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE usuarios ADD FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE SET NULL',
    'SELECT "Foreign key already exists" AS info');
    
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- INSERTAR PERMISOS DEL SISTEMA
-- ============================================

INSERT INTO permisos (nombre, descripcion, modulo, hash) VALUES
-- Dashboard
('view_dashboard', 'Ver página principal', 'dashboard', SHA2('view_dashboard', 256)),

-- Chats
('view_chats', 'Ver chats', 'chats', SHA2('view_chats', 256)),
('send_messages', 'Enviar mensajes', 'chats', SHA2('send_messages', 256)),
('delete_messages', 'Eliminar mensajes', 'chats', SHA2('delete_messages', 256)),

-- Estados
('view_status', 'Ver estados', 'status', SHA2('view_status', 256)),
('create_status', 'Crear estados', 'status', SHA2('create_status', 256)),
('delete_status', 'Eliminar estados', 'status', SHA2('delete_status', 256)),

-- Contactos
('view_contacts', 'Ver contactos', 'contacts', SHA2('view_contacts', 256)),
('create_contacts', 'Crear contactos', 'contacts', SHA2('create_contacts', 256)),
('edit_contacts', 'Editar contactos', 'contacts', SHA2('edit_contacts', 256)),
('delete_contacts', 'Eliminar contactos', 'contacts', SHA2('delete_contacts', 256)),

-- Grupos
('view_groups', 'Ver grupos', 'groups', SHA2('view_groups', 256)),
('manage_groups', 'Gestionar grupos', 'groups', SHA2('manage_groups', 256)),

-- Difusión
('view_broadcast', 'Ver difusiones', 'broadcast', SHA2('view_broadcast', 256)),
('create_broadcast', 'Crear difusiones', 'broadcast', SHA2('create_broadcast', 256)),
('send_broadcast', 'Enviar difusiones', 'broadcast', SHA2('send_broadcast', 256)),
('delete_broadcast', 'Eliminar difusiones', 'broadcast', SHA2('delete_broadcast', 256)),

-- Plantillas
('view_templates', 'Ver plantillas', 'templates', SHA2('view_templates', 256)),
('manage_templates', 'Gestionar plantillas', 'templates', SHA2('manage_templates', 256)),

-- Respuestas Automáticas
('view_auto_reply', 'Ver respuestas automáticas', 'auto-reply', SHA2('view_auto_reply', 256)),
('manage_auto_reply', 'Gestionar respuestas automáticas', 'auto-reply', SHA2('manage_auto_reply', 256)),

-- Estadísticas
('view_stats', 'Ver estadísticas', 'stats', SHA2('view_stats', 256)),

-- Configuración
('view_settings', 'Ver configuración', 'settings', SHA2('view_settings', 256)),
('manage_settings', 'Gestionar configuración', 'settings', SHA2('manage_settings', 256)),

-- Usuarios
('view_users', 'Ver usuarios', 'users', SHA2('view_users', 256)),
('create_users', 'Crear usuarios', 'users', SHA2('create_users', 256)),
('edit_users', 'Editar usuarios', 'users', SHA2('edit_users', 256)),
('delete_users', 'Eliminar usuarios', 'users', SHA2('delete_users', 256)),
('manage_roles', 'Gestionar roles y permisos', 'users', SHA2('manage_roles', 256)),

-- Conectar API
('view_qr_connect', 'Ver conexión API', 'qr-connect', SHA2('view_qr_connect', 256)),
('manage_qr_connect', 'Gestionar conexión API', 'qr-connect', SHA2('manage_qr_connect', 256));

-- ============================================
-- INSERTAR ROLES DEL SISTEMA
-- ============================================

INSERT INTO roles (nombre, descripcion, es_sistema) VALUES
('Administrador', 'Acceso total al sistema', 1),
('Operador', 'Gestión de chats, contactos y difusiones', 1),
('Visor', 'Solo lectura', 1);

-- ============================================
-- ASIGNAR PERMISOS A ROLES
-- ============================================

-- ROL: ADMINISTRADOR (todos los permisos)
INSERT INTO rol_permisos (rol_id, permiso_id)
SELECT 1, id FROM permisos;

-- ROL: OPERADOR
INSERT INTO rol_permisos (rol_id, permiso_id)
SELECT 2, id FROM permisos WHERE nombre IN (
    'view_dashboard',
    'view_chats', 'send_messages',
    'view_status', 'create_status',
    'view_contacts', 'create_contacts', 'edit_contacts',
    'view_groups', 'manage_groups',
    'view_broadcast', 'create_broadcast', 'send_broadcast',
    'view_templates', 'manage_templates',
    'view_stats'
);

-- ROL: VISOR
INSERT INTO rol_permisos (rol_id, permiso_id)
SELECT 3, id FROM permisos WHERE nombre IN (
    'view_dashboard',
    'view_chats',
    'view_contacts',
    'view_stats'
);

-- ============================================
-- MIGRAR USUARIOS EXISTENTES AL NUEVO SISTEMA
-- ============================================

-- Asignar rol_id basado en el rol antiguo (si existe)
UPDATE usuarios u
JOIN roles r ON (
    (u.rol = 'admin' AND r.nombre = 'Administrador') OR
    (u.rol = 'operador' AND r.nombre = 'Operador') OR
    (u.rol = 'visor' AND r.nombre = 'Visor')
)
SET u.rol_id = r.id
WHERE u.rol IS NOT NULL;

-- ============================================
-- VISTAS ÚTILES
-- ============================================

-- Vista de permisos por rol
CREATE OR REPLACE VIEW v_roles_permisos AS
SELECT 
    r.id as rol_id,
    r.nombre as rol_nombre,
    r.descripcion as rol_descripcion,
    p.id as permiso_id,
    p.nombre as permiso_nombre,
    p.descripcion as permiso_descripcion,
    p.modulo as permiso_modulo,
    p.hash as permiso_hash
FROM roles r
LEFT JOIN rol_permisos rp ON r.id = rp.rol_id
LEFT JOIN permisos p ON rp.permiso_id = p.id
WHERE r.activo = 1
ORDER BY r.nombre, p.modulo, p.nombre;

-- Vista de usuarios con sus permisos
CREATE OR REPLACE VIEW v_usuarios_permisos AS
SELECT 
    u.id as usuario_id,
    u.username,
    u.nombre as usuario_nombre,
    u.email,
    u.activo as usuario_activo,
    r.id as rol_id,
    r.nombre as rol_nombre,
    p.id as permiso_id,
    p.nombre as permiso_nombre,
    p.modulo as permiso_modulo,
    p.hash as permiso_hash
FROM usuarios u
LEFT JOIN roles r ON u.rol_id = r.id
LEFT JOIN rol_permisos rp ON r.id = rp.rol_id
LEFT JOIN permisos p ON rp.permiso_id = p.id
WHERE u.activo = 1 AND (r.activo = 1 OR r.activo IS NULL)
ORDER BY u.nombre, p.modulo, p.nombre;

-- ============================================
-- ÍNDICES PARA OPTIMIZACIÓN
-- ============================================

CREATE INDEX idx_permisos_modulo ON permisos(modulo);
CREATE INDEX idx_permisos_hash ON permisos(hash);
CREATE INDEX idx_roles_activo ON roles(activo);
CREATE INDEX idx_usuarios_rol_id ON usuarios(rol_id);