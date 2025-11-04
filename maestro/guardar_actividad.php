<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'functions.php';

protegerPagina([3]); // Solo maestros

$db = new Database();
$maestro_id = $_SESSION['user_id'];

// Validar datos
$grupo_id = isset($_POST['grupo_id']) ? intval($_POST['grupo_id']) : 0;
$materia_id = isset($_POST['materia_id']) ? intval($_POST['materia_id']) : 0;
$trimestre = isset($_POST['trimestre']) ? intval($_POST['trimestre']) : 1;
$nombre = trim($_POST['nombre'] ?? '');
$porcentaje = isset($_POST['porcentaje']) ? floatval($_POST['porcentaje']) : 0;
$descripcion = trim($_POST['descripcion'] ?? '');
$fecha_entrega = $_POST['fecha_entrega'] ?? ''; // Nuevo campo capturado

// Validaciones básicas
if (empty($nombre) || empty($descripcion) || $porcentaje <= 0 || $grupo_id <= 0 || $materia_id <= 0 || empty($fecha_entrega)) {
    $_SESSION['error'] = "Datos incompletos o inválidos";
    header("Location: actividades.php?id=$grupo_id&materia_id=$materia_id");
    exit;
}

try {
    // Verificar que el grupo pertenece al maestro
    $db->query("SELECT id FROM grupos WHERE id = :grupo_id AND maestro_id = :maestro_id");
    $db->bind(':grupo_id', $grupo_id);
    $db->bind(':maestro_id', $maestro_id);
    $grupo = $db->single();

    if (!$grupo) {
        $_SESSION['error'] = "No tienes permiso para agregar actividades a este grupo";
        header("Location: actividades.php?id=$grupo_id");
        exit;
    }

    // Verificar que la materia está asignada al maestro
    $db->query("SELECT id FROM maestros_materias WHERE materia_id = :materia_id AND maestro_id = :maestro_id");
    $db->bind(':materia_id', $materia_id);
    $db->bind(':maestro_id', $maestro_id);
    $materia = $db->single();

    if (!$materia) {
        $_SESSION['error'] = "No tienes permiso para agregar actividades en esta materia";
        header("Location: actividades.php?id=$grupo_id");
        exit;
    }

    // Verificar que la suma de porcentajes no exceda 100% en el trimestre
    $db->query("SELECT SUM(porcentaje) as total FROM actividades 
                WHERE grupo_id = :grupo_id AND materia_id = :materia_id AND trimestre = :trimestre");
    $db->bind(':grupo_id', $grupo_id);
    $db->bind(':materia_id', $materia_id);
    $db->bind(':trimestre', $trimestre);
    $result = $db->single();
    $total_porcentaje = $result->total ?? 0;

    if (($total_porcentaje + $porcentaje) > 100) {
        $_SESSION['error'] = "La suma de porcentajes no puede exceder el 100% en un trimestre";
        header("Location: actividades.php?id=$grupo_id&materia_id=$materia_id");
        exit;
    }

    // Insertar nueva actividad (ahora incluyendo fecha_entrega)
    $db->query("INSERT INTO actividades 
            (nombre, descripcion, porcentaje, trimestre, fecha_entrega, materia_id, grupo_id) VALUES (:nombre, :descripcion, :porcentaje, :trimestre, :fecha_entrega, :materia_id, :grupo_id)");
    $db->bind(':nombre', $nombre);
    $db->bind(':descripcion', $descripcion ?: null);
    $db->bind(':porcentaje', $porcentaje);
    $db->bind(':trimestre', $trimestre);
    $db->bind(':fecha_entrega', $fecha_entrega); // Nuevo campo insertado
    $db->bind(':materia_id', $materia_id);
    $db->bind(':grupo_id', $grupo_id);

    $db->execute();

    $_SESSION['success'] = "Actividad creada correctamente";
    header("Location: actividades.php?id=$grupo_id&materia_id=$materia_id");
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al guardar la actividad: " . $e->getMessage();
    header("Location: actividades.php?id=$grupo_id&materia_id=$materia_id");
}