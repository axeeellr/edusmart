<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'functions.php';

protegerPagina([3]); // Solo maestros
header('Content-Type: application/json');

$db = new Database();

// Leer JSON del POST
$data = json_decode(file_get_contents('php://input'), true);

$id = $data['id'] ?? null;
$nombre = $data['nombre'] ?? null;
$descripcion = $data['descripcion'] ?? null;
$porcentaje = $data['porcentaje'] ?? null;
$materia_id = $data['materia_id'] ?? null;
$trimestre = $data['trimestre'] ?? null;

if (!$id || !$nombre || !$descripcion || !$porcentaje || !$materia_id || !$trimestre) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

try {
    // Verificar permiso - CORREGIDO
    $db->query("SELECT a.id 
                FROM actividades a
                JOIN grupos g ON a.grupo_id = g.id
                WHERE a.id = :actividad_id 
                AND g.maestro_id = :maestro_id");
    $db->bind(':actividad_id', $id);
    $db->bind(':maestro_id', $_SESSION['user_id']);
    $actividad = $db->single();

    if (!$actividad) {
        echo json_encode(['success' => false, 'message' => 'No tienes permiso para editar esta actividad']);
        exit;
    }

    // Verificar porcentaje total
    $db->query("SELECT SUM(porcentaje) as suma 
                FROM actividades 
                WHERE materia_id = :materia_id 
                AND trimestre = :trimestre 
                AND id != :actividad_id");
    $db->bind(':materia_id', $materia_id);
    $db->bind(':trimestre', $trimestre);
    $db->bind(':actividad_id', $id);
    $sumaObj = $db->single();
    $suma = $sumaObj->suma ?? 0;

    if (($suma + floatval($porcentaje)) > 100) {
        echo json_encode(['success' => false, 'message' => 'El porcentaje total excede el 100%']);
        exit;
    }

    // Actualizar actividad
    $db->query("UPDATE actividades 
                SET nombre = :nombre, descripcion = :descripcion, porcentaje = :porcentaje 
                WHERE id = :id");
    $db->bind(':nombre', $nombre);
    $db->bind(':descripcion', $descripcion);
    $db->bind(':porcentaje', $porcentaje);
    $db->bind(':id', $id);
    $db->execute();

    echo json_encode(['success' => true, 'message' => 'Actividad actualizada correctamente']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
}