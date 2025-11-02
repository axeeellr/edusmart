-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 31, 2025 at 12:05 AM
-- Server version: 8.4.3
-- PHP Version: 8.3.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `smartedu`
--
CREATE DATABASE IF NOT EXISTS `smartedu` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE `smartedu`;

DELIMITER $$
--
-- Procedures
--
DROP PROCEDURE IF EXISTS `sp_calcular_promedio_estudiante`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_calcular_promedio_estudiante` (IN `p_estudiante_id` INT, IN `p_materia_id` INT, IN `p_trimestre` INT, OUT `p_promedio` DECIMAL(5,2), OUT `p_resultado` VARCHAR(200))   BEGIN
    DECLARE v_estudiante_valido BOOLEAN;
    DECLARE v_materia_valida BOOLEAN;
    
    -- Validar que el estudiante existe
    SELECT COUNT(*) INTO v_estudiante_valido FROM estudiantes WHERE id = p_estudiante_id AND activo = TRUE;
    -- Validar que la materia existe
    SELECT COUNT(*) INTO v_materia_valida FROM materias WHERE id = p_materia_id AND activa = TRUE;
    
    IF v_estudiante_valido = 0 THEN
        SET p_resultado = 'El estudiante no existe o está inactivo';
        SET p_promedio = 0;
    ELSEIF v_materia_valida = 0 THEN
        SET p_resultado = 'La materia no existe o está inactiva';
        SET p_promedio = 0;
    ELSE
        IF p_trimestre = 0 THEN
            -- Promedio general de todos los trimestres
            SELECT ROUND(AVG(n.calificacion * a.porcentaje / 100), 2) INTO p_promedio
            FROM notas n
            JOIN actividades a ON n.actividad_id = a.id
            WHERE n.estudiante_id = p_estudiante_id
            AND a.materia_id = p_materia_id
            AND a.porcentaje > 0;
        ELSE
            -- Promedio por trimestre específico
            SELECT ROUND(SUM(n.calificacion * a.porcentaje / 100), 2) INTO p_promedio
            FROM notas n
            JOIN actividades a ON n.actividad_id = a.id
            WHERE n.estudiante_id = p_estudiante_id
            AND a.materia_id = p_materia_id
            AND a.trimestre = p_trimestre
            AND a.porcentaje > 0;
        END IF;
        
        IF p_promedio IS NULL THEN
            SET p_resultado = 'No se encontraron calificaciones registradas';
            SET p_promedio = 0;
        ELSE
            SET p_resultado = 'Promedio calculado correctamente';
        END IF;
    END IF;
END$$

DROP PROCEDURE IF EXISTS `sp_generar_reporte_grupo`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_generar_reporte_grupo` (IN `p_grupo_id` INT, IN `p_materia_id` INT, IN `p_trimestre` INT)   BEGIN
    DECLARE v_grupo_valido BOOLEAN;
    DECLARE v_materia_valida BOOLEAN;
    
    -- Validar que el grupo existe
    SELECT COUNT(*) INTO v_grupo_valido FROM grupos WHERE id = p_grupo_id;
    -- Validar que la materia existe
    SELECT COUNT(*) INTO v_materia_valida FROM materias WHERE id = p_materia_id AND activa = TRUE;
    
    IF v_grupo_valido = 0 THEN
        SELECT 'Error: El grupo no existe' AS mensaje;
    ELSEIF v_materia_valida = 0 THEN
        SELECT 'Error: La materia no existe o está inactiva' AS mensaje;
    ELSE
        -- Datos del grupo y materia
        SELECT 
            g.nombre AS grupo_nombre, 
            g.grado, 
            m.nombre AS materia_nombre,
            CASE 
                WHEN p_trimestre = 0 THEN 'Promedio General'
                ELSE CONCAT('Trimestre ', p_trimestre)
            END AS periodo
        FROM grupos g, materias m
        WHERE g.id = p_grupo_id AND m.id = p_materia_id;
        
        -- Calificaciones por estudiante
        SELECT 
            e.id,
            e.nombre_completo,
            ROUND(SUM(n.calificacion * a.porcentaje / 100), 2) AS promedio,
            CASE WHEN SUM(n.calificacion * a.porcentaje / 100) >= 6 THEN 'Aprobado' ELSE 'Reprobado' END AS estado
        FROM estudiantes e
        LEFT JOIN notas n ON n.estudiante_id = e.id
        LEFT JOIN actividades a ON a.id = n.actividad_id
        WHERE e.grupo_id = p_grupo_id
        AND e.activo = TRUE
        AND a.materia_id = p_materia_id
        AND (a.trimestre = p_trimestre OR p_trimestre = 0)
        AND a.porcentaje > 0
        GROUP BY e.id, e.nombre_completo
        ORDER BY e.nombre_completo;
        
        -- Estadísticas generales
        SELECT 
            COUNT(e.id) AS total_estudiantes,
            ROUND(AVG(promedio), 2) AS promedio_grupo,
            SUM(CASE WHEN promedio >= 6 THEN 1 ELSE 0 END) AS aprobados,
            SUM(CASE WHEN promedio < 6 THEN 1 ELSE 0 END) AS reprobados,
            ROUND((SUM(CASE WHEN promedio >= 6 THEN 1 ELSE 0 END) / COUNT(e.id)) * 100, 2) AS porcentaje_aprobacion
        FROM (
            SELECT 
                e.id,
                SUM(n.calificacion * a.porcentaje / 100) AS promedio
            FROM estudiantes e
            LEFT JOIN notas n ON n.estudiante_id = e.id
            LEFT JOIN actividades a ON a.id = n.actividad_id
            WHERE e.grupo_id = p_grupo_id
            AND e.activo = TRUE
            AND a.materia_id = p_materia_id
            AND (a.trimestre = p_trimestre OR p_trimestre = 0)
            AND a.porcentaje > 0
            GROUP BY e.id
        ) AS calificaciones;
    END IF;
END$$

DROP PROCEDURE IF EXISTS `sp_usuario_create`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_usuario_create` (IN `p_nombre_completo` VARCHAR(100), IN `p_fecha_nacimiento` DATE, IN `p_rol_id` INT, IN `p_materias_id` JSON, OUT `p_resultado` VARCHAR(200))   BEGIN
    DECLARE v_username VARCHAR(50);
    DECLARE v_password VARCHAR(50);
    DECLARE v_usuario_id INT;
    DECLARE v_contador INT DEFAULT 1;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_resultado = 'Error al crear usuario';
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    -- Generar username automático
    SET v_username = CONCAT(
        LOWER(SUBSTRING(SUBSTRING_INDEX(p_nombre_completo, ' ', 1), 1, 1)),
        LOWER(SUBSTRING(SUBSTRING_INDEX(p_nombre_completo, ' ', -1), 1, 1)),
        DATE_FORMAT(NOW(), '%y')
    );
    
    -- Verificar username único
    WHILE EXISTS (SELECT 1 FROM usuarios WHERE username = v_username) DO
        SET v_username = CONCAT(
            SUBSTRING(v_username, 1, CHAR_LENGTH(v_username) - CHAR_LENGTH(v_contador) + 1),
            v_contador
        );
        SET v_contador = v_contador + 1;
    END WHILE;
    
    -- Generar password aleatorio seguro
    SET v_password = CONCAT(
        SUBSTRING('ABCDEFGHIJKLMNOPQRSTUVWXYZ', FLOOR(RAND() * 26) + 1, 1),
        FLOOR(RAND() * 10),
        SUBSTRING('!@#$%^&*', FLOOR(RAND() * 8) + 1, 1),
        SUBSTRING('abcdefghijklmnopqrstuvwxyz', FLOOR(RAND() * 26) + 1, 5)
    );
    
    -- Insertar usuario
    INSERT INTO usuarios (
        nombre_completo, fecha_nacimiento, username, password, rol_id, activo
    ) VALUES (
        p_nombre_completo, p_fecha_nacimiento, v_username, 
        SHA2(v_password, 256), p_rol_id, 1
    );
    
    SET v_usuario_id = LAST_INSERT_ID();
    
    -- Asignar materias si es maestro (rol_id = 3)
    IF p_rol_id = 3 AND p_materias_id IS NOT NULL AND JSON_LENGTH(p_materias_id) > 0 THEN
        SET @sql = CONCAT('
            INSERT INTO maestros_materias (maestro_id, materia_id)
            SELECT ', v_usuario_id, ', id 
            FROM materias 
            WHERE id IN (',
            REPLACE(REPLACE(REPLACE(p_materias_id, '[', ''), ']', ''), '"', ''),
            ')
        ');
        
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
    
    SET p_resultado = CONCAT('Usuario creado. Credenciales: ', v_username, '/', v_password);
    COMMIT;
END$$

DROP PROCEDURE IF EXISTS `sp_usuario_delete`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_usuario_delete` (IN `p_usuario_id` INT, OUT `p_resultado` VARCHAR(200))   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_resultado = CONCAT('Error al eliminar usuario: ', SQL_ERROR_MESSAGE);
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    -- Verificar si el usuario existe
    IF NOT EXISTS (SELECT 1 FROM usuarios WHERE id = p_usuario_id) THEN
        SET p_resultado = 'El usuario no existe';
    ELSE
        -- Eliminar asignaciones de materias primero
        DELETE FROM maestros_materias WHERE maestro_id = p_usuario_id;
        
        -- Desactivar usuario (no borrar físicamente)
        UPDATE usuarios SET activo = FALSE WHERE id = p_usuario_id;
        
        SET p_resultado = 'Usuario desactivado correctamente';
    END IF;
    
    COMMIT;
END$$

DROP PROCEDURE IF EXISTS `sp_usuario_update`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_usuario_update` (IN `p_usuario_id` INT, IN `p_nombre_completo` VARCHAR(100), IN `p_fecha_nacimiento` DATE, IN `p_rol_id` INT, IN `p_activo` BOOLEAN, IN `p_materias_id` JSON, OUT `p_resultado` VARCHAR(200))   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_resultado = CONCAT('Error al actualizar usuario: ', SQL_ERROR_MESSAGE);
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    -- Actualizar datos básicos
    UPDATE usuarios SET
        nombre_completo = p_nombre_completo,
        fecha_nacimiento = p_fecha_nacimiento,
        rol_id = p_rol_id,
        activo = p_activo
    WHERE id = p_usuario_id;
    
    -- Si es maestro, actualizar materias
    IF p_rol_id = 3 THEN
        -- Eliminar asignaciones anteriores
        DELETE FROM maestros_materias WHERE maestro_id = p_usuario_id;
        
        -- Insertar nuevas asignaciones
        IF p_materias_id IS NOT NULL AND JSON_LENGTH(p_materias_id) > 0 THEN
            SET @sql = CONCAT('
                INSERT INTO maestros_materias (maestro_id, materia_id)
                SELECT ', p_usuario_id, ', id 
                FROM materias 
                WHERE id IN (',
                REPLACE(REPLACE(REPLACE(p_materias_id, '[', ''), ']', ''), '"', ''),
                ')
            ');
            
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        END IF;
    END IF;
    
    SET p_resultado = 'Usuario actualizado correctamente';
    COMMIT;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `actividades`
--

DROP TABLE IF EXISTS `actividades`;
CREATE TABLE `actividades` (
  `id` int NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text,
  `porcentaje` decimal(5,2) NOT NULL,
  `trimestre` int NOT NULL,
  `fecha_entrega` date DEFAULT NULL,
  `materia_id` int NOT NULL,
  `grupo_id` int NOT NULL,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Triggers `actividades`
--
DROP TRIGGER IF EXISTS `tr_bitacora_delete_actividades`;
DELIMITER $$
CREATE TRIGGER `tr_bitacora_delete_actividades` AFTER DELETE ON `actividades` FOR EACH ROW BEGIN
    INSERT INTO bitacora (
        usuario_sistema, nombre_tabla, accion, id_registro_afectado,
        valores_anteriores, ip_conexion, modulo
    ) VALUES (
        CURRENT_USER(), 'actividades', 'DELETE', OLD.id,
        JSON_OBJECT(
            'nombre', OLD.nombre,
            'materia_id', OLD.materia_id
        ),
        (SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(USER(), '@', 1), '@', -1)),
        'Gestión Académica'
    );
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `tr_bitacora_insert_actividades`;
DELIMITER $$
CREATE TRIGGER `tr_bitacora_insert_actividades` AFTER INSERT ON `actividades` FOR EACH ROW BEGIN
    INSERT INTO bitacora (
        usuario_sistema, nombre_tabla, accion, id_registro_afectado,
        valores_nuevos, ip_conexion, modulo
    ) VALUES (
        CURRENT_USER(), 'actividades', 'INSERT', NEW.id,
        JSON_OBJECT(
            'nombre', NEW.nombre,
            'materia_id', NEW.materia_id,
            'grupo_id', NEW.grupo_id,
            'trimestre', NEW.trimestre,
            'porcentaje', NEW.porcentaje,
            'fecha_entrega', NEW.fecha_entrega
        ),
        (SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(USER(), '@', 1), '@', -1)),
        'Gestión Académica'
    );
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `tr_bitacora_update_actividades`;
DELIMITER $$
CREATE TRIGGER `tr_bitacora_update_actividades` AFTER UPDATE ON `actividades` FOR EACH ROW BEGIN
    INSERT INTO bitacora (
        usuario_sistema, nombre_tabla, accion, id_registro_afectado,
        valores_anteriores, valores_nuevos, ip_conexion, modulo
    ) VALUES (
        CURRENT_USER(), 'actividades', 'UPDATE', NEW.id,
        JSON_OBJECT(
            'nombre', OLD.nombre,
            'porcentaje', OLD.porcentaje,
            'trimestre', OLD.trimestre,
            'fecha_entrega', OLD.fecha_entrega
        ),
        JSON_OBJECT(
            'nombre', NEW.nombre,
            'porcentaje', NEW.porcentaje,
            'trimestre', NEW.trimestre,
            'fecha_entrega', NEW.fecha_entrega
        ),
        (SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(USER(), '@', 1), '@', -1)),
        'Gestión Académica'
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `bitacora`
--

DROP TABLE IF EXISTS `bitacora`;
CREATE TABLE `bitacora` (
  `id_reg` int NOT NULL,
  `usuario_sistema` varchar(50) NOT NULL,
  `fecha_hora_sistema` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `nombre_tabla` varchar(50) NOT NULL,
  `accion` enum('INSERT','UPDATE','DELETE') NOT NULL,
  `id_registro_afectado` int DEFAULT NULL,
  `valores_anteriores` json DEFAULT NULL,
  `valores_nuevos` json DEFAULT NULL,
  `ip_conexion` varchar(45) DEFAULT NULL,
  `modulo` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `estudiantes`
--

DROP TABLE IF EXISTS `estudiantes`;
CREATE TABLE `estudiantes` (
  `id` int NOT NULL,
  `nombre_completo` varchar(100) NOT NULL,
  `fecha_nacimiento` date NOT NULL,
  `grupo_id` int NOT NULL,
  `estado` enum('activo','inactivo','retirado','egresado') DEFAULT 'activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Triggers `estudiantes`
--
DROP TRIGGER IF EXISTS `tr_bitacora_delete_estudiantes`;
DELIMITER $$
CREATE TRIGGER `tr_bitacora_delete_estudiantes` AFTER DELETE ON `estudiantes` FOR EACH ROW BEGIN
    INSERT INTO bitacora (
        usuario_sistema, nombre_tabla, accion, id_registro_afectado,
        valores_anteriores, ip_conexion, modulo
    ) VALUES (
        CURRENT_USER(), 'estudiantes', 'DELETE', OLD.id,
        JSON_OBJECT(
            'nombre_completo', OLD.nombre_completo,
            'grupo_id', OLD.grupo_id
        ),
        (SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(USER(), '@', 1), '@', -1)),
        'Gestión de Estudiantes'
    );
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `tr_bitacora_insert_estudiantes`;
DELIMITER $$
CREATE TRIGGER `tr_bitacora_insert_estudiantes` AFTER INSERT ON `estudiantes` FOR EACH ROW INSERT INTO bitacora (
        usuario_sistema, nombre_tabla, accion, id_registro_afectado,
        valores_nuevos, ip_conexion, modulo
    ) VALUES (
        CURRENT_USER(), 'estudiantes', 'INSERT', NEW.id,
        JSON_OBJECT(
            'nombre_completo', NEW.nombre_completo,
            'fecha_nacimiento', NEW.fecha_nacimiento,
            'grupo_id', NEW.grupo_id,
            'estado', NEW.estado
        ),
        (SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(USER(), '@', 1), '@', -1)),
        'Gestión de Estudiantes'
    )
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `tr_bitacora_update_estudiantes`;
DELIMITER $$
CREATE TRIGGER `tr_bitacora_update_estudiantes` AFTER UPDATE ON `estudiantes` FOR EACH ROW INSERT INTO bitacora (
        usuario_sistema, nombre_tabla, accion, id_registro_afectado,
        valores_anteriores, valores_nuevos, ip_conexion, modulo
    ) VALUES (
        CURRENT_USER(), 'estudiantes', 'UPDATE', NEW.id,
        JSON_OBJECT(
            'nombre_completo', OLD.nombre_completo,
            'fecha_nacimiento', OLD.fecha_nacimiento,
            'grupo_id', OLD.grupo_id,
            'estado', OLD.estado
        ),
        JSON_OBJECT(
            'nombre_completo', NEW.nombre_completo,
            'fecha_nacimiento', NEW.fecha_nacimiento,
            'grupo_id', NEW.grupo_id,
            'estado', NEW.estado
        ),
        (SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(USER(), '@', 1), '@', -1)),
        'Gestión de Estudiantes'
    )
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `grupos`
--

DROP TABLE IF EXISTS `grupos`;
CREATE TABLE `grupos` (
  `id` int NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `grado` varchar(20) NOT NULL,
  `ciclo_escolar` varchar(20) NOT NULL,
  `maestro_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Triggers `grupos`
--
DROP TRIGGER IF EXISTS `tr_bitacora_delete_grupos`;
DELIMITER $$
CREATE TRIGGER `tr_bitacora_delete_grupos` AFTER DELETE ON `grupos` FOR EACH ROW BEGIN
    INSERT INTO bitacora (
        usuario_sistema, nombre_tabla, accion, id_registro_afectado,
        valores_anteriores, ip_conexion, modulo
    ) VALUES (
        CURRENT_USER(), 'grupos', 'DELETE', OLD.id,
        JSON_OBJECT(
            'nombre', OLD.nombre,
            'grado', OLD.grado
        ),
        (SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(USER(), '@', 1), '@', -1)),
        'Gestión de Grupos'
    );
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `tr_bitacora_insert_grupos`;
DELIMITER $$
CREATE TRIGGER `tr_bitacora_insert_grupos` AFTER INSERT ON `grupos` FOR EACH ROW BEGIN
    INSERT INTO bitacora (
        usuario_sistema, nombre_tabla, accion, id_registro_afectado,
        valores_nuevos, ip_conexion, modulo
    ) VALUES (
        CURRENT_USER(), 'grupos', 'INSERT', NEW.id,
        JSON_OBJECT(
            'nombre', NEW.nombre,
            'grado', NEW.grado,
            'ciclo_escolar', NEW.ciclo_escolar,
            'maestro_id', NEW.maestro_id
        ),
        (SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(USER(), '@', 1), '@', -1)),
        'Gestión de Grupos'
    );
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `tr_bitacora_update_grupos`;
DELIMITER $$
CREATE TRIGGER `tr_bitacora_update_grupos` AFTER UPDATE ON `grupos` FOR EACH ROW BEGIN
    INSERT INTO bitacora (
        usuario_sistema, nombre_tabla, accion, id_registro_afectado,
        valores_anteriores, valores_nuevos, ip_conexion, modulo
    ) VALUES (
        CURRENT_USER(), 'grupos', 'UPDATE', NEW.id,
        JSON_OBJECT(
            'nombre', OLD.nombre,
            'grado', OLD.grado,
            'ciclo_escolar', OLD.ciclo_escolar,
            'maestro_id', OLD.maestro_id
        ),
        JSON_OBJECT(
            'nombre', NEW.nombre,
            'grado', NEW.grado,
            'ciclo_escolar', NEW.ciclo_escolar,
            'maestro_id', NEW.maestro_id
        ),
        (SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(USER(), '@', 1), '@', -1)),
        'Gestión de Grupos'
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `maestros_materias`
--

DROP TABLE IF EXISTS `maestros_materias`;
CREATE TABLE `maestros_materias` (
  `id` int NOT NULL,
  `maestro_id` int NOT NULL,
  `materia_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Triggers `maestros_materias`
--
DROP TRIGGER IF EXISTS `tr_bitacora_delete_maestros_materias`;
DELIMITER $$
CREATE TRIGGER `tr_bitacora_delete_maestros_materias` AFTER DELETE ON `maestros_materias` FOR EACH ROW BEGIN
    INSERT INTO bitacora (
        usuario_sistema, nombre_tabla, accion, id_registro_afectado,
        valores_anteriores, ip_conexion, modulo
    ) VALUES (
        CURRENT_USER(), 'maestros_materias', 'DELETE', OLD.id,
        JSON_OBJECT(
            'maestro_id', OLD.maestro_id,
            'materia_id', OLD.materia_id
        ),
        (SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(USER(), '@', 1), '@', -1)),
        'Asignación de Materias'
    );
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `tr_bitacora_insert_maestros_materias`;
DELIMITER $$
CREATE TRIGGER `tr_bitacora_insert_maestros_materias` AFTER INSERT ON `maestros_materias` FOR EACH ROW BEGIN
    INSERT INTO bitacora (
        usuario_sistema, nombre_tabla, accion, id_registro_afectado,
        valores_nuevos, ip_conexion, modulo
    ) VALUES (
        CURRENT_USER(), 'maestros_materias', 'INSERT', NEW.id,
        JSON_OBJECT(
            'maestro_id', NEW.maestro_id,
            'materia_id', NEW.materia_id
        ),
        (SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(USER(), '@', 1), '@', -1)),
        'Asignación de Materias'
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `materias`
--

DROP TABLE IF EXISTS `materias`;
CREATE TABLE `materias` (
  `id` int NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text,
  `activa` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Triggers `materias`
--
DROP TRIGGER IF EXISTS `tr_bitacora_delete_materias`;
DELIMITER $$
CREATE TRIGGER `tr_bitacora_delete_materias` AFTER DELETE ON `materias` FOR EACH ROW BEGIN
    INSERT INTO bitacora (
        usuario_sistema, nombre_tabla, accion, id_registro_afectado,
        valores_anteriores, ip_conexion, modulo
    ) VALUES (
        CURRENT_USER(), 'materias', 'DELETE', OLD.id,
        JSON_OBJECT(
            'nombre', OLD.nombre,
            'descripcion', OLD.descripcion
        ),
        (SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(USER(), '@', 1), '@', -1)),
        'Gestión Académica'
    );
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `tr_bitacora_insert_materias`;
DELIMITER $$
CREATE TRIGGER `tr_bitacora_insert_materias` AFTER INSERT ON `materias` FOR EACH ROW BEGIN
    INSERT INTO bitacora (
        usuario_sistema, nombre_tabla, accion, id_registro_afectado,
        valores_nuevos, ip_conexion, modulo
    ) VALUES (
        CURRENT_USER(), 'materias', 'INSERT', NEW.id,
        JSON_OBJECT(
            'nombre', NEW.nombre,
            'descripcion', NEW.descripcion,
            'activa', NEW.activa
        ),
        (SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(USER(), '@', 1), '@', -1)),
        'Gestión Académica'
    );
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `tr_bitacora_update_materias`;
DELIMITER $$
CREATE TRIGGER `tr_bitacora_update_materias` AFTER UPDATE ON `materias` FOR EACH ROW BEGIN
    INSERT INTO bitacora (
        usuario_sistema, nombre_tabla, accion, id_registro_afectado,
        valores_anteriores, valores_nuevos, ip_conexion, modulo
    ) VALUES (
        CURRENT_USER(), 'materias', 'UPDATE', NEW.id,
        JSON_OBJECT(
            'nombre', OLD.nombre,
            'descripcion', OLD.descripcion,
            'activa', OLD.activa
        ),
        JSON_OBJECT(
            'nombre', NEW.nombre,
            'descripcion', NEW.descripcion,
            'activa', NEW.activa
        ),
        (SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(USER(), '@', 1), '@', -1)),
        'Gestión Académica'
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `materias_niveles`
--

DROP TABLE IF EXISTS `materias_niveles`;
CREATE TABLE `materias_niveles` (
  `id` int NOT NULL,
  `materia_id` int NOT NULL,
  `nivel_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `niveles`
--

DROP TABLE IF EXISTS `niveles`;
CREATE TABLE `niveles` (
  `id` int NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


INSERT INTO niveles (nombre, descripcion)
VALUES 
('Primer ciclo', 'Incluye los grados de 1º a 3º de educación básica'),
('Segundo ciclo', 'Incluye los grados de 4º a 6º de educación básica'),
('Tercer ciclo', 'Incluye los grados de 7º a 9º de educación básica');
-- --------------------------------------------------------

--
-- Table structure for table `notas`
--

DROP TABLE IF EXISTS `notas`;
CREATE TABLE `notas` (
  `id` int NOT NULL,
  `estudiante_id` int NOT NULL,
  `actividad_id` int NOT NULL,
  `calificacion` decimal(5,2) NOT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Triggers `notas`
--
DROP TRIGGER IF EXISTS `tr_bitacora_delete_notas`;
DELIMITER $$
CREATE TRIGGER `tr_bitacora_delete_notas` AFTER DELETE ON `notas` FOR EACH ROW BEGIN
    INSERT INTO bitacora (
        usuario_sistema, nombre_tabla, accion, id_registro_afectado,
        valores_anteriores, ip_conexion, modulo
    ) VALUES (
        CURRENT_USER(), 'notas', 'DELETE', OLD.id,
        JSON_OBJECT(
            'estudiante_id', OLD.estudiante_id,
            'actividad_id', OLD.actividad_id
        ),
        (SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(USER(), '@', 1), '@', -1)),
        'Registro de Calificaciones'
    );
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `tr_bitacora_insert_notas`;
DELIMITER $$
CREATE TRIGGER `tr_bitacora_insert_notas` AFTER INSERT ON `notas` FOR EACH ROW BEGIN
    INSERT INTO bitacora (
        usuario_sistema, nombre_tabla, accion, id_registro_afectado,
        valores_nuevos, ip_conexion, modulo
    ) VALUES (
        CURRENT_USER(), 'notas', 'INSERT', NEW.id,
        JSON_OBJECT(
            'estudiante_id', NEW.estudiante_id,
            'actividad_id', NEW.actividad_id,
            'calificacion', NEW.calificacion
        ),
        (SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(USER(), '@', 1), '@', -1)),
        'Registro de Calificaciones'
    );
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `tr_bitacora_update_notas`;
DELIMITER $$
CREATE TRIGGER `tr_bitacora_update_notas` AFTER UPDATE ON `notas` FOR EACH ROW BEGIN
    INSERT INTO bitacora (
        usuario_sistema, nombre_tabla, accion, id_registro_afectado,
        valores_anteriores, valores_nuevos, ip_conexion, modulo
    ) VALUES (
        CURRENT_USER(), 'notas', 'UPDATE', NEW.id,
        JSON_OBJECT(
            'calificacion', OLD.calificacion
        ),
        JSON_OBJECT(
            'calificacion', NEW.calificacion
        ),
        (SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(USER(), '@', 1), '@', -1)),
        'Registro de Calificaciones'
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` int NOT NULL,
  `nombre` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


INSERT INTO roles (nombre) VALUES 
('admin'), ('rector'), ('maestro');

-- --------------------------------------------------------

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
  `id` int NOT NULL,
  `nombre_completo` varchar(100) NOT NULL,
  `fecha_nacimiento` date NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol_id` int NOT NULL,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `activo` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Triggers `usuarios`
--
DROP TRIGGER IF EXISTS `tr_bitacora_delete_usuarios`;
DELIMITER $$
CREATE TRIGGER `tr_bitacora_delete_usuarios` AFTER DELETE ON `usuarios` FOR EACH ROW BEGIN
    INSERT INTO bitacora (
        usuario_sistema, nombre_tabla, accion, id_registro_afectado,
        valores_anteriores, ip_conexion, modulo
    ) VALUES (
        CURRENT_USER(), 'usuarios', 'DELETE', OLD.id,
        JSON_OBJECT(
            'nombre_completo', OLD.nombre_completo,
            'username', OLD.username,
            'rol_id', OLD.rol_id
        ),
        (SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(USER(), '@', 1), '@', -1)),
        'Gestión de Usuarios'
    );
END
$$
DELIMITER ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `actividades`
--
ALTER TABLE `actividades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `materia_id` (`materia_id`),
  ADD KEY `grupo_id` (`grupo_id`);

--
-- Indexes for table `bitacora`
--
ALTER TABLE `bitacora`
  ADD PRIMARY KEY (`id_reg`);

--
-- Indexes for table `estudiantes`
--
ALTER TABLE `estudiantes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `grupo_id` (`grupo_id`);

--
-- Indexes for table `grupos`
--
ALTER TABLE `grupos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `maestro_id` (`maestro_id`);

--
-- Indexes for table `maestros_materias`
--
ALTER TABLE `maestros_materias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `maestro_id` (`maestro_id`,`materia_id`),
  ADD KEY `materia_id` (`materia_id`);

--
-- Indexes for table `materias`
--
ALTER TABLE `materias`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `materias_niveles`
--
ALTER TABLE `materias_niveles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `materia_id` (`materia_id`,`nivel_id`),
  ADD KEY `nivel_id` (`nivel_id`);

--
-- Indexes for table `niveles`
--
ALTER TABLE `niveles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notas`
--
ALTER TABLE `notas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `estudiante_id` (`estudiante_id`,`actividad_id`),
  ADD KEY `actividad_id` (`actividad_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `rol_id` (`rol_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `actividades`
--
ALTER TABLE `actividades`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bitacora`
--
ALTER TABLE `bitacora`
  MODIFY `id_reg` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `estudiantes`
--
ALTER TABLE `estudiantes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `grupos`
--
ALTER TABLE `grupos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `maestros_materias`
--
ALTER TABLE `maestros_materias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `materias`
--
ALTER TABLE `materias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `materias_niveles`
--
ALTER TABLE `materias_niveles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `niveles`
--
ALTER TABLE `niveles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notas`
--
ALTER TABLE `notas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `actividades`
--
ALTER TABLE `actividades`
  ADD CONSTRAINT `actividades_ibfk_1` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`),
  ADD CONSTRAINT `actividades_ibfk_2` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`);

--
-- Constraints for table `estudiantes`
--
ALTER TABLE `estudiantes`
  ADD CONSTRAINT `estudiantes_ibfk_1` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`);

--
-- Constraints for table `grupos`
--
ALTER TABLE `grupos`
  ADD CONSTRAINT `grupos_ibfk_1` FOREIGN KEY (`maestro_id`) REFERENCES `usuarios` (`id`);

--
-- Constraints for table `maestros_materias`
--
ALTER TABLE `maestros_materias`
  ADD CONSTRAINT `maestros_materias_ibfk_1` FOREIGN KEY (`maestro_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `maestros_materias_ibfk_2` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`);

--
-- Constraints for table `materias_niveles`
--
ALTER TABLE `materias_niveles`
  ADD CONSTRAINT `materias_niveles_ibfk_1` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `materias_niveles_ibfk_2` FOREIGN KEY (`nivel_id`) REFERENCES `niveles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notas`
--
ALTER TABLE `notas`
  ADD CONSTRAINT `notas_ibfk_1` FOREIGN KEY (`estudiante_id`) REFERENCES `estudiantes` (`id`),
  ADD CONSTRAINT `notas_ibfk_2` FOREIGN KEY (`actividad_id`) REFERENCES `actividades` (`id`);

--
-- Constraints for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
