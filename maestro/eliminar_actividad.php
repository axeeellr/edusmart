<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'functions.php';

protegerPagina([3]); // Solo maestros
header('Content-Type: application/json');

$db = new Database();
$data = json_decode(file_get_contents('php://input'), true);

// Validar datos
if (empty($data['actividad_id'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

try {
    // Verificar que la actividad pertenece a un grupo del maestro - CORREGIDO
    $db->query("SELECT a.id 
                FROM actividades a
                JOIN grupos g ON a.grupo_id = g.id
                WHERE a.id = :actividad_id 
                AND g.maestro_id = :maestro_id");
    $db->bind(':actividad_id', $data['actividad_id']);
    $db->bind(':maestro_id', $_SESSION['user_id']);
    $actividad = $db->single();
    
    if (!$actividad) {
        echo json_encode(['success' => false, 'message' => 'No tienes permiso para eliminar esta actividad']);
        exit;
    }
    
    // Eliminar las calificaciones primero
    $db->query("DELETE FROM notas WHERE actividad_id = :actividad_id");
    $db->bind(':actividad_id', $data['actividad_id']);
    $db->execute();
    
    // Luego eliminar la actividad
    $db->query("DELETE FROM actividades WHERE id = :actividad_id");
    $db->bind(':actividad_id', $data['actividad_id']);
    $db->execute();
    
    echo json_encode(['success' => true, 'message' => 'Actividad eliminada correctamente']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
}