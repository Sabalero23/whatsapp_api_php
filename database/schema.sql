-- phpMyAdmin SQL Dump - BASE DE DATOS LIMPIA
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Base de datos: `whatsapp_db`
-- Versión limpia para inicio de proyecto

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `whatsapp_db`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`whatsapp_db`@`localhost` PROCEDURE `sp_estadisticas_bot` (IN `dias` INT)   BEGIN
    SELECT 
        ra.palabra_clave,
        ra.contador_usos,
        ra.ultima_vez_usada,
        ra.activa,
        ra.prioridad
    FROM respuestas_automaticas ra
    WHERE ra.ultima_vez_usada >= DATE_SUB(NOW(), INTERVAL dias DAY)
        OR ra.ultima_vez_usada IS NULL
    ORDER BY ra.contador_usos DESC, ra.prioridad DESC;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `chats`
--

CREATE TABLE `chats` (
  `id` bigint UNSIGNED NOT NULL,
  `chat_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ultimo_mensaje` text COLLATE utf8mb4_unicode_ci,
  `timestamp` bigint DEFAULT NULL,
  `no_leidos` int DEFAULT '0',
  `archivado` tinyint(1) DEFAULT '0',
  `fijado` tinyint(1) DEFAULT '0',
  `fecha_actualizacion` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion`
--

CREATE TABLE `configuracion` (
  `id` int NOT NULL,
  `clave` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` text COLLATE utf8mb4_unicode_ci,
  `tipo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'string',
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `fecha_actualizacion` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `configuracion`
--

INSERT INTO `configuracion` (`id`, `clave`, `valor`, `tipo`, `descripcion`, `fecha_actualizacion`) VALUES
(1, 'respuestas_automaticas_activas', '1', 'boolean', 'Activar/desactivar respuestas automáticas', NOW()),
(2, 'delay_entre_mensajes', '2', 'integer', 'Delay en segundos entre mensajes masivos', NOW()),
(3, 'max_mensajes_por_hora', '100', 'integer', 'Límite de mensajes por hora', NOW()),
(4, 'mensaje_bienvenida', 'Hola! Gracias por contactarnos.', 'text', 'Mensaje de bienvenida automático', NOW()),
(5, 'horario_atencion_inicio', '08:00', 'time', 'Hora de inicio de atención', NOW()),
(6, 'horario_atencion_fin', '18:00', 'time', 'Hora de fin de atención', NOW()),
(7, 'mensaje_fuera_horario', 'Gracias por tu mensaje. Te responderemos en nuestro horario de atención.', 'text', 'Mensaje fuera de horario', NOW()),
(8, 'bot_activo', '1', 'boolean', 'Estado del bot de respuestas automáticas (0=inactivo, 1=activo)', NOW());

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `contactos`
--

CREATE TABLE `contactos` (
  `id` int UNSIGNED NOT NULL,
  `numero` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `empresa` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notas` text COLLATE utf8mb4_unicode_ci,
  `etiquetas` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bloqueado` tinyint(1) DEFAULT '0',
  `favorito` tinyint(1) DEFAULT '0',
  `ultimo_contacto` datetime DEFAULT NULL,
  `validado` tinyint(1) DEFAULT '0',
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `difusiones`
--

CREATE TABLE `difusiones` (
  `id` bigint UNSIGNED NOT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mensaje` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'text',
  `media_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` enum('borrador','programada','enviando','completada','cancelada') COLLATE utf8mb4_unicode_ci DEFAULT 'borrador',
  `total_destinatarios` int DEFAULT '0',
  `enviados` int DEFAULT '0',
  `fallidos` int DEFAULT '0',
  `programada_para` datetime DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `fecha_inicio` datetime DEFAULT NULL,
  `fecha_fin` datetime DEFAULT NULL,
  `creado_por` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `difusion_destinatarios`
--

CREATE TABLE `difusion_destinatarios` (
  `id` bigint UNSIGNED NOT NULL,
  `difusion_id` bigint UNSIGNED NOT NULL,
  `numero` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` enum('pendiente','enviado','fallido') COLLATE utf8mb4_unicode_ci DEFAULT 'pendiente',
  `fecha_envio` datetime DEFAULT NULL,
  `error` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estadisticas_diarias`
--

CREATE TABLE `estadisticas_diarias` (
  `id` int NOT NULL,
  `fecha` date NOT NULL,
  `mensajes_enviados` int DEFAULT '0',
  `mensajes_recibidos` int DEFAULT '0',
  `mensajes_fallidos` int DEFAULT '0',
  `nuevos_contactos` int DEFAULT '0',
  `respuestas_automaticas` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estados`
--

CREATE TABLE `estados` (
  `id` bigint UNSIGNED NOT NULL,
  `usuario_id` int DEFAULT NULL,
  `status_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `texto` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `background_color` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT '#25D366',
  `font` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Arial',
  `tipo` enum('texto','imagen','video') COLLATE utf8mb4_unicode_ci DEFAULT 'texto',
  `media_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vistas` int DEFAULT '0',
  `fecha_publicacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `expira_en` datetime GENERATED ALWAYS AS ((`fecha_publicacion` + interval 24 hour)) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estado_vistas`
--

CREATE TABLE `estado_vistas` (
  `id` bigint UNSIGNED NOT NULL,
  `estado_id` bigint UNSIGNED NOT NULL,
  `numero_viewer` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_vista` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `grupos`
--

CREATE TABLE `grupos` (
  `id` bigint UNSIGNED NOT NULL,
  `group_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `creado_por` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `total_participantes` int DEFAULT '0',
  `codigo_invitacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `grupo_participantes`
--

CREATE TABLE `grupo_participantes` (
  `id` bigint UNSIGNED NOT NULL,
  `grupo_id` bigint UNSIGNED NOT NULL,
  `numero` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `es_admin` tinyint(1) DEFAULT '0',
  `fecha_ingreso` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `horarios_atencion`
--

CREATE TABLE `horarios_atencion` (
  `id` int NOT NULL,
  `dia_semana` tinyint NOT NULL COMMENT '0=Domingo, 1=Lunes, 2=Martes, 3=Miércoles, 4=Jueves, 5=Viernes, 6=Sábado',
  `activo` tinyint(1) DEFAULT '1',
  `manana_inicio` time DEFAULT NULL,
  `manana_fin` time DEFAULT NULL,
  `tarde_inicio` time DEFAULT NULL,
  `tarde_fin` time DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `horarios_atencion`
--

INSERT INTO `horarios_atencion` (`id`, `dia_semana`, `activo`, `manana_inicio`, `manana_fin`, `tarde_inicio`, `tarde_fin`, `fecha_actualizacion`) VALUES
(1, 0, 0, NULL, NULL, NULL, NULL, NOW()),
(2, 1, 1, '08:00:00', '12:00:00', '16:00:00', '20:00:00', NOW()),
(3, 2, 1, '08:00:00', '12:00:00', '16:00:00', '20:00:00', NOW()),
(4, 3, 1, '08:00:00', '12:00:00', '16:00:00', '20:00:00', NOW()),
(5, 4, 1, '08:00:00', '12:00:00', '16:00:00', '20:00:00', NOW()),
(6, 5, 1, '08:00:00', '12:00:00', '16:00:00', '20:00:00', NOW()),
(7, 6, 1, '08:30:00', '12:00:00', NULL, NULL, NOW());

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `logs`
--

CREATE TABLE `logs` (
  `id` bigint UNSIGNED NOT NULL,
  `usuario_id` int DEFAULT NULL,
  `accion` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `datos` text COLLATE utf8mb4_unicode_ci,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mensajes_entrantes`
--

CREATE TABLE `mensajes_entrantes` (
  `id` bigint UNSIGNED NOT NULL,
  `mensaje_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero_remitente` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mensaje` text COLLATE utf8mb4_unicode_ci,
  `timestamp` bigint DEFAULT NULL,
  `tipo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'chat',
  `tiene_media` tinyint(1) DEFAULT '0',
  `media_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `procesado` tinyint(1) DEFAULT '0',
  `fecha_recepcion` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mensajes_salientes`
--

CREATE TABLE `mensajes_salientes` (
  `id` bigint UNSIGNED NOT NULL,
  `numero_destinatario` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mensaje` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `timestamp` bigint DEFAULT NULL,
  `mensaje_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` enum('pendiente','enviado','error') COLLATE utf8mb4_unicode_ci DEFAULT 'pendiente',
  `tipo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'text',
  `media_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `error_mensaje` text COLLATE utf8mb4_unicode_ci,
  `prioridad` tinyint(1) DEFAULT '0',
  `programado_para` datetime DEFAULT NULL,
  `fecha_envio` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permisos`
--

CREATE TABLE `permisos` (
  `id` int UNSIGNED NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ej: view_chats, manage_contacts',
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `modulo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ej: chats, contacts, users',
  `hash` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Identificador único del permiso',
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `permisos`
--

INSERT INTO `permisos` (`id`, `nombre`, `descripcion`, `modulo`, `hash`, `fecha_creacion`) VALUES
(1, 'view_dashboard', 'Ver página principal', 'dashboard', '2b2d6e89d2db91134296cbef5c151d42acab78aa7faf33c0117936969dd018a9', NOW()),
(2, 'view_chats', 'Ver chats', 'chats', '9797e07a0331b3e968c86bcf5a7c3923db3de6d4f6d879a7bbd5eb59a6c8a159', NOW()),
(3, 'send_messages', 'Enviar mensajes', 'chats', '985191994b4ea5eee0f93f84d0608fc94f1fc37b7032f24c5effdbf22163d8fb', NOW()),
(4, 'delete_messages', 'Eliminar mensajes', 'chats', '374c90b592828dfa531cb4db5c53db7ee45399f66c7aa46ddaf7a5d9f3ae650b', NOW()),
(5, 'view_status', 'Ver estados', 'status', 'fb64a9769a1c7df65b3748bb0dde5b52cfcc137c08373de4145a598459a3de08', NOW()),
(6, 'create_status', 'Crear estados', 'status', 'b71435cbf5e452503c143168ec086913625d820e57467b2d40acfb32a79435cb', NOW()),
(7, 'delete_status', 'Eliminar estados', 'status', 'f9ecc35dfc22e0a9883d493c7c0ade6080883e747bcd994ac03e9b09bca9c688', NOW()),
(8, 'view_contacts', 'Ver contactos', 'contacts', '68baa986375af3ba65587a7a02c21253422dc83a8b6abc61a73a2034700d9c2a', NOW()),
(9, 'create_contacts', 'Crear contactos', 'contacts', 'b8426e68d18cf23711312e9f8bc21a0faaa88996167351afff45c5d0a6c50539', NOW()),
(10, 'edit_contacts', 'Editar contactos', 'contacts', 'a91ffbaca5158a58205efa3db342d6e461b655f4f4f800b19eeb5c3ee8578cad', NOW()),
(11, 'delete_contacts', 'Eliminar contactos', 'contacts', 'dba3dd9b586f704a8af6faa7b4f6441487ee55bcd0f4f01cdfa8d4ac22e511c4', NOW()),
(12, 'view_groups', 'Ver grupos', 'groups', 'd9b9b165f95161a8224166afe7d9995ce0ae6704b541d1f95a6147430e36acb4', NOW()),
(13, 'manage_groups', 'Gestionar grupos', 'groups', 'fdf0ce0154b1aa29710b8f1ba902efb051a7fc0fc3c1abc4c2c7f7a078188d97', NOW()),
(14, 'view_broadcast', 'Ver difusiones', 'broadcast', '38886bb713db62b369f04a5879cf65827090de854d5e2d35eccbfd1445e07513', NOW()),
(15, 'create_broadcast', 'Crear difusiones', 'broadcast', 'afeae20f9a1c4e0a46c594e2ab3cf02732489b729e7ded5ba2a35b40046924f3', NOW()),
(16, 'send_broadcast', 'Enviar difusiones', 'broadcast', '27ed72968a80734ed6fce73d756117994ad9b6b0abd99197160030545a1c517e', NOW()),
(17, 'delete_broadcast', 'Eliminar difusiones', 'broadcast', '89a265c2c7fbb3bdb0435d3f0262b9f1f5b5da5366ed9443dc1b5c6afd260619', NOW()),
(18, 'view_templates', 'Ver plantillas', 'templates', 'de7b1e808abe90e1c51fb89675c2642b8cde090db1235b885afe3bea3d126917', NOW()),
(19, 'manage_templates', 'Gestionar plantillas', 'templates', '252b8c572adeda87f3cb3f1d61e85e21f7229f043a91c76c27467ca8d4b8f3d1', NOW()),
(20, 'view_auto_reply', 'Ver respuestas automáticas', 'auto-reply', '0cefb76a16d40fcdbc4cacae8123ff40b2a13eef8d44fc0a23c0b1eb558bad3d', NOW()),
(21, 'manage_auto_reply', 'Gestionar respuestas automáticas', 'auto-reply', '93634e1f6c03a6eebdf0fe5d2447d35d5529007638758d8f113db300d10cdf55', NOW()),
(22, 'view_stats', 'Ver estadísticas', 'stats', '54b219fbe2bede2e7d1f8a91c4f6b3c80daf187cb8967f07151d24cd8f9c9993', NOW()),
(23, 'view_settings', 'Ver configuración', 'settings', '66eace2fc1b5b57f3b230f3ab9aa83dd5dcb276f01044fbc6165d35bfdefe67c', NOW()),
(24, 'manage_settings', 'Gestionar configuración', 'settings', 'ea12da2092f5c26889f5977e4f4654db933229ee272c7ae9784f96ed6bf888c4', NOW()),
(25, 'view_users', 'Ver usuarios', 'users', '7957498184fd521c4e70a7bcc02ea5609b02183798ab11f421f34c062dc055ea', NOW()),
(26, 'create_users', 'Crear usuarios', 'users', '2ec26833348253d154509b2f55529350910a1043e5b6f07c07b0f4c4558dff7c', NOW()),
(27, 'edit_users', 'Editar usuarios', 'users', 'e3706b2bfb1ab4fcb9003c90b637235806236f8c44bfd4f0d194fc28b90fb80c', NOW()),
(28, 'delete_users', 'Eliminar usuarios', 'users', '8284cb1f8b3a043b62f246763d252d069a0e10276f3c037f79c645a11008a1a8', NOW()),
(29, 'manage_roles', 'Gestionar roles y permisos', 'users', 'd76d462932e444f4a58f0a5167c83be318a7cee4ecc1a980d54cd7a088b5053a', NOW()),
(30, 'view_qr_connect', 'Ver conexión API', 'qr-connect', '5ce5aa1cfb3292217c1c41324b1990ccfd7248f099ef653e5990980f2468101e', NOW()),
(31, 'manage_qr_connect', 'Gestionar conexión API', 'qr-connect', '9e029b52e998f2a73122969278d2951811075fd3fd65d78afc9ee8b590917e1f', NOW());

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `plantillas`
--

CREATE TABLE `plantillas` (
  `id` int UNSIGNED NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contenido` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `categoria` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'general',
  `activa` tinyint(1) DEFAULT '1',
  `fecha_creacion` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `plantillas`
--

INSERT INTO `plantillas` (`id`, `nombre`, `contenido`, `categoria`, `activa`, `fecha_creacion`) VALUES
(1, 'bienvenida', 'Hola {{nombre}}, bienvenido. ¿En qué podemos ayudarte?', 'general', 1, NOW()),
(2, 'seguimiento', 'Hola {{nombre}}, queremos saber tu opinión.', 'general', 1, NOW());

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `respuestas_automaticas`
--

CREATE TABLE `respuestas_automaticas` (
  `id` bigint UNSIGNED NOT NULL,
  `palabra_clave` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `respuesta` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `exacta` tinyint(1) DEFAULT '0' COMMENT '1=coincidencia exacta, 0=contiene',
  `activa` tinyint(1) DEFAULT '1',
  `prioridad` int DEFAULT '0',
  `contador_usos` int DEFAULT '0',
  `ultima_vez_usada` datetime DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `fecha_ultimo_uso` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id` int UNSIGNED NOT NULL,
  `nombre` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `es_sistema` tinyint(1) DEFAULT '0' COMMENT '1=rol del sistema (no se puede eliminar)',
  `activo` tinyint(1) DEFAULT '1',
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `nombre`, `descripcion`, `es_sistema`, `activo`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 'Administrador', 'Acceso total al sistema', 1, 1, NOW(), NOW()),
(2, 'Operador', 'Gestión de chats, contactos y difusiones', 1, 1, NOW(), NOW()),
(3, 'Visor', 'Solo lectura', 1, 1, NOW(), NOW());

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rol_permisos`
--

CREATE TABLE `rol_permisos` (
  `id` int UNSIGNED NOT NULL,
  `rol_id` int UNSIGNED NOT NULL,
  `permiso_id` int UNSIGNED NOT NULL,
  `fecha_asignacion` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `rol_permisos`
-- ADMINISTRADOR - Todos los permisos
--

INSERT INTO `rol_permisos` (`id`, `rol_id`, `permiso_id`, `fecha_asignacion`) VALUES
(1, 1, 1, NOW()), (2, 1, 2, NOW()), (3, 1, 3, NOW()), (4, 1, 4, NOW()),
(5, 1, 5, NOW()), (6, 1, 6, NOW()), (7, 1, 7, NOW()), (8, 1, 8, NOW()),
(9, 1, 9, NOW()), (10, 1, 10, NOW()), (11, 1, 11, NOW()), (12, 1, 12, NOW()),
(13, 1, 13, NOW()), (14, 1, 14, NOW()), (15, 1, 15, NOW()), (16, 1, 16, NOW()),
(17, 1, 17, NOW()), (18, 1, 18, NOW()), (19, 1, 19, NOW()), (20, 1, 20, NOW()),
(21, 1, 21, NOW()), (22, 1, 22, NOW()), (23, 1, 23, NOW()), (24, 1, 24, NOW()),
(25, 1, 25, NOW()), (26, 1, 26, NOW()), (27, 1, 27, NOW()), (28, 1, 28, NOW()),
(29, 1, 29, NOW()), (30, 1, 30, NOW()), (31, 1, 31, NOW()),

-- OPERADOR - Permisos de operación
(32, 2, 1, NOW()), (33, 2, 2, NOW()), (34, 2, 3, NOW()), (35, 2, 5, NOW()),
(36, 2, 6, NOW()), (37, 2, 8, NOW()), (38, 2, 9, NOW()), (39, 2, 10, NOW()),
(40, 2, 12, NOW()), (41, 2, 13, NOW()), (42, 2, 14, NOW()), (43, 2, 15, NOW()),
(44, 2, 16, NOW()), (45, 2, 18, NOW()), (46, 2, 19, NOW()), (47, 2, 22, NOW()),

-- VISOR - Solo lectura
(48, 3, 1, NOW()), (49, 3, 2, NOW()), (50, 3, 8, NOW()), (51, 3, 22, NOW());

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitudes_contacto`
--

CREATE TABLE `solicitudes_contacto` (
  `id` int UNSIGNED NOT NULL,
  `numero` varchar(50) NOT NULL,
  `mensaje` text,
  `fecha_solicitud` datetime NOT NULL,
  `atendido` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int NOT NULL,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rol_id` int UNSIGNED DEFAULT NULL,
  `rol` enum('admin','operador','visor') COLLATE utf8mb4_unicode_ci DEFAULT 'operador',
  `activo` tinyint(1) DEFAULT '1',
  `ultimo_acceso` datetime DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
-- IMPORTANTE: Cambiar la contraseña del usuario admin después de la primera instalación
-- Usuario: admin | Contraseña: admin123
--

INSERT INTO `usuarios` (`id`, `username`, `password`, `nombre`, `email`, `rol_id`, `rol`, `activo`, `ultimo_acceso`, `fecha_creacion`) VALUES
(1, 'admin', '$2y$10$trYTdg36s3tL6TydtqbUFe871AqOlEfvargI1GNxK77kpK3rK.oca', 'Administrador', 'admin@example.com', 1, 'admin', 1, NULL, NOW());

-- --------------------------------------------------------

--
-- Estructura para la vista `v_estadisticas_bot`
--

CREATE ALGORITHM=UNDEFINED DEFINER=`whatsapp_db`@`localhost` SQL SECURITY DEFINER VIEW `v_estadisticas_bot` AS 
SELECT 
    CAST(me.fecha_recepcion AS DATE) AS fecha,
    COUNT(DISTINCT me.id) AS total_mensajes_recibidos,
    COUNT(DISTINCT CASE WHEN me.procesado = 1 THEN me.id END) AS mensajes_procesados,
    COUNT(DISTINCT ms.id) AS respuestas_enviadas
FROM mensajes_entrantes me
LEFT JOIN mensajes_salientes ms ON (
    CAST(me.fecha_recepcion AS DATE) = CAST(ms.fecha_creacion AS DATE)
    AND ms.tipo = 'auto_reply'
)
WHERE me.numero_remitente <> 'status@broadcast'
GROUP BY CAST(me.fecha_recepcion AS DATE)
ORDER BY fecha DESC;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_roles_permisos`
--

CREATE ALGORITHM=UNDEFINED DEFINER=`whatsapp_db`@`localhost` SQL SECURITY DEFINER VIEW `v_roles_permisos` AS 
SELECT 
    r.id AS rol_id,
    r.nombre AS rol_nombre,
    r.descripcion AS rol_descripcion,
    p.id AS permiso_id,
    p.nombre AS permiso_nombre,
    p.descripcion AS permiso_descripcion,
    p.modulo AS permiso_modulo,
    p.hash AS permiso_hash
FROM roles r
LEFT JOIN rol_permisos rp ON r.id = rp.rol_id
LEFT JOIN permisos p ON rp.permiso_id = p.id
WHERE r.activo = 1
ORDER BY r.nombre ASC, p.modulo ASC, p.nombre ASC;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_usuarios_permisos`
--

CREATE ALGORITHM=UNDEFINED DEFINER=`whatsapp_db`@`localhost` SQL SECURITY DEFINER VIEW `v_usuarios_permisos` AS 
SELECT 
    u.id AS usuario_id,
    u.username,
    u.nombre AS usuario_nombre,
    u.email,
    u.activo AS usuario_activo,
    r.id AS rol_id,
    r.nombre AS rol_nombre,
    p.id AS permiso_id,
    p.nombre AS permiso_nombre,
    p.modulo AS permiso_modulo,
    p.hash AS permiso_hash
FROM usuarios u
LEFT JOIN roles r ON u.rol_id = r.id
LEFT JOIN rol_permisos rp ON r.id = rp.rol_id
LEFT JOIN permisos p ON rp.permiso_id = p.id
WHERE u.activo = 1 
  AND (r.activo = 1 OR r.activo IS NULL)
ORDER BY u.nombre ASC, p.modulo ASC, p.nombre ASC;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `chats`
--
ALTER TABLE `chats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `chat_id` (`chat_id`),
  ADD KEY `archivado` (`archivado`),
  ADD KEY `fijado` (`fijado`);

--
-- Indices de la tabla `configuracion`
--
ALTER TABLE `configuracion`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `clave` (`clave`);

--
-- Indices de la tabla `contactos`
--
ALTER TABLE `contactos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero` (`numero`),
  ADD KEY `idx_numero` (`numero`),
  ADD KEY `nombre` (`nombre`),
  ADD KEY `bloqueado` (`bloqueado`);

--
-- Indices de la tabla `difusiones`
--
ALTER TABLE `difusiones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `estado` (`estado`),
  ADD KEY `programada_para` (`programada_para`);

--
-- Indices de la tabla `difusion_destinatarios`
--
ALTER TABLE `difusion_destinatarios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `difusion_id` (`difusion_id`),
  ADD KEY `numero` (`numero`),
  ADD KEY `estado` (`estado`);

--
-- Indices de la tabla `estadisticas_diarias`
--
ALTER TABLE `estadisticas_diarias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `fecha` (`fecha`);

--
-- Indices de la tabla `estados`
--
ALTER TABLE `estados`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `fecha_publicacion` (`fecha_publicacion`),
  ADD KEY `expira_en` (`expira_en`);

--
-- Indices de la tabla `estado_vistas`
--
ALTER TABLE `estado_vistas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `estado_id` (`estado_id`),
  ADD KEY `numero_viewer` (`numero_viewer`);

--
-- Indices de la tabla `grupos`
--
ALTER TABLE `grupos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `group_id` (`group_id`);

--
-- Indices de la tabla `grupo_participantes`
--
ALTER TABLE `grupo_participantes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `grupo_id` (`grupo_id`),
  ADD KEY `numero` (`numero`);

--
-- Indices de la tabla `horarios_atencion`
--
ALTER TABLE `horarios_atencion`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dia_semana` (`dia_semana`);

--
-- Indices de la tabla `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `fecha` (`fecha`);

--
-- Indices de la tabla `mensajes_entrantes`
--
ALTER TABLE `mensajes_entrantes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mensaje_id` (`mensaje_id`),
  ADD KEY `idx_numero` (`numero_remitente`),
  ADD KEY `idx_procesado` (`procesado`),
  ADD KEY `idx_fecha_recepcion` (`fecha_recepcion`),
  ADD KEY `idx_numero_remitente` (`numero_remitente`),
  ADD KEY `idx_media_url` (`media_url`(255)),
  ADD KEY `idx_timestamp` (`timestamp`);

--
-- Indices de la tabla `mensajes_salientes`
--
ALTER TABLE `mensajes_salientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mensaje_id` (`mensaje_id`),
  ADD KEY `idx_numero` (`numero_destinatario`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `fecha_creacion` (`fecha_creacion`),
  ADD KEY `idx_tipo` (`tipo`),
  ADD KEY `idx_timestamp` (`timestamp`);

--
-- Indices de la tabla `permisos`
--
ALTER TABLE `permisos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`),
  ADD UNIQUE KEY `hash` (`hash`),
  ADD KEY `idx_permisos_modulo` (`modulo`),
  ADD KEY `idx_permisos_hash` (`hash`);

--
-- Indices de la tabla `plantillas`
--
ALTER TABLE `plantillas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `respuestas_automaticas`
--
ALTER TABLE `respuestas_automaticas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `palabra_clave` (`palabra_clave`),
  ADD KEY `activa` (`activa`),
  ADD KEY `idx_activa_prioridad` (`activa`,`prioridad`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`),
  ADD KEY `idx_roles_activo` (`activo`);

--
-- Indices de la tabla `rol_permisos`
--
ALTER TABLE `rol_permisos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_rol_permiso` (`rol_id`,`permiso_id`),
  ADD KEY `permiso_id` (`permiso_id`);

--
-- Indices de la tabla `solicitudes_contacto`
--
ALTER TABLE `solicitudes_contacto`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_atendido` (`atendido`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_usuarios_rol_id` (`rol_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

ALTER TABLE `chats`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `configuracion`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

ALTER TABLE `contactos`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `difusiones`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `difusion_destinatarios`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `estadisticas_diarias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `estados`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `estado_vistas`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `grupos`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `grupo_participantes`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `horarios_atencion`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

ALTER TABLE `logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `mensajes_entrantes`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `mensajes_salientes`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `permisos`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

ALTER TABLE `plantillas`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `respuestas_automaticas`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `roles`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `rol_permisos`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

ALTER TABLE `solicitudes_contacto`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `usuarios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restricciones para tablas volcadas
--

ALTER TABLE `difusion_destinatarios`
  ADD CONSTRAINT `difusion_destinatarios_ibfk_1` FOREIGN KEY (`difusion_id`) REFERENCES `difusiones` (`id`) ON DELETE CASCADE;

ALTER TABLE `estado_vistas`
  ADD CONSTRAINT `fk_estado_vistas` FOREIGN KEY (`estado_id`) REFERENCES `estados` (`id`) ON DELETE CASCADE;

ALTER TABLE `grupo_participantes`
  ADD CONSTRAINT `grupo_participantes_ibfk_1` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`) ON DELETE CASCADE;

ALTER TABLE `rol_permisos`
  ADD CONSTRAINT `rol_permisos_ibfk_1` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rol_permisos_ibfk_2` FOREIGN KEY (`permiso_id`) REFERENCES `permisos` (`id`) ON DELETE CASCADE;

ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- ========================================
-- INSTRUCCIONES DE INSTALACIÓN
-- ========================================
--
-- 1. Crear la base de datos en phpMyAdmin:
--    CREATE DATABASE whatsapp_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--
-- 2. Importar este archivo SQL
--
-- 3. CREDENCIALES INICIALES:
--    Usuario: admin
--    Contraseña: admin123
--
-- 4. IMPORTANTE: Cambiar la contraseña del administrador después del primer login
--
-- 5. PERMISOS PRECONFIGURADOS:
--    - Administrador: Acceso total (31 permisos)
--    - Operador: Gestión de chats, contactos, difusiones (16 permisos)
--    - Visor: Solo lectura (4 permisos)
--
-- 6. CONFIGURACIÓN PREDETERMINADA:
--    - Respuestas automáticas: ACTIVAS
--    - Bot: ACTIVO
--    - Delay entre mensajes: 2 segundos
--    - Límite de mensajes por hora: 100
--    - Horario de atención: Lun-Vie 8-12 y 16-20, Sáb 8:30-12
--
-- ========================================