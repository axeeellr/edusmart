<?php
require_once __DIR__ . '/../includes/config.php';
protegerPagina([3]); // Solo maestros pueden acceder

header('Content-Type: application/json');

try {
    $db = new Database();
    $data = json_decode(file_get_contents("php://input"), true);

    // Datos recibidos desde JS
    $nota_id = intval($data['nota_id'] ?? 0);
    $razon = trim($data['razon'] ?? '');
    $maestro_id = $_SESSION['user_id'] ?? 0; // Maestro logueado

    // Validaciones básicas
    if ($nota_id <= 0 || $razon === '') {
        echo json_encode(['success' => false, 'message' => 'Faltan datos para enviar la solicitud.']);
        exit;
    }

    if ($maestro_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'No se pudo identificar al maestro.']);
        exit;
    }

    // Verificar que la nota existe y está bloqueada
    $db->query("SELECT bloqueada FROM notas WHERE id = :nota_id");
    $db->bind(':nota_id', $nota_id);
    $nota = $db->single();

    if (!$nota) {
        echo json_encode(['success' => false, 'message' => 'La nota no existe.']);
        exit;
    }

    if ($nota->bloqueada != 1) {
        echo json_encode(['success' => false, 'message' => 'La nota no está bloqueada y no puede solicitar modificación.']);
        exit;
    }

    // Insertar solicitud en la tabla separada
    $db->query("INSERT INTO solicitudes_modificacion (nota_id, maestro_id, razon) 
                VALUES (:nota_id, :maestro_id, :razon)");
    $db->bind(':nota_id', $nota_id);
    $db->bind(':maestro_id', $maestro_id);
    $db->bind(':razon', $razon);
    $db->execute();

    echo json_encode(['success' => true, 'message' => 'Solicitud de modificación enviada correctamente.']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}
