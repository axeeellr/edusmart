<?php
require_once __DIR__ . '/../includes/config.php';
protegerPagina([1]); // Solo administradores

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $grupo_id = intval($_POST['grupo_id']);
    
    $db = new Database();
    
    try {
        $db->beginTransaction();
        
        // Eliminar todos los estudiantes del grupo
        $db->query("DELETE FROM estudiantes WHERE grupo_id = :grupo_id");
        $db->bind(':grupo_id', $grupo_id);
        $db->execute();
        
        $db->commit();
        
        echo json_encode(['success' => true, 'message' => 'Todos los estudiantes han sido eliminados']);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error al eliminar los estudiantes: ' . $e->getMessage()]);
    }
}
?>