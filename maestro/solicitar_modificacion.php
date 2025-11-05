<?php
require_once __DIR__ . '/../includes/config.php';
protegerPagina([3]);
header('Content-Type: application/json; charset=utf-8');

try {
    $db = new Database();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    if (empty($body['nota_id']) || !isset($body['razon']) || trim($body['razon']) === '') {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
        exit;
    }

    $nota_id = (int) $body['nota_id'];
    $razon = trim($body['razon']);
    $maestro_id = (int) $_SESSION['user_id'];

    // 1) Permisos
    $db->query("
        SELECT 1
        FROM notas n
        JOIN actividades a        ON a.id = n.actividad_id
        JOIN grupos g             ON g.id = a.grupo_id
        JOIN maestros_materias mm ON mm.materia_id = a.materia_id
        WHERE n.id = :nota_id
        AND g.maestro_id = :m
        AND mm.maestro_id = :m
        LIMIT 1
    ");
    $db->bind(':nota_id', $nota_id);
    $db->bind(':m', $maestro_id);
    if (!$db->single()) {
        echo json_encode(['success' => false, 'message' => 'No tienes permiso para esta nota.']);
        exit;
    }

    // 2) Insertar SOLO si no existe PENDIENTE (permite crear otra si la previa fue aceptada/rechazada)
    $db->beginTransaction();

    $db->query("
        INSERT INTO solicitudes_modificacion (nota_id, maestro_id, razon, estado, fecha_solicitud)
        SELECT :nota_id, :maestro_id, :razon, 'pendiente', NOW()
        FROM DUAL
        WHERE NOT EXISTS (
            SELECT 1
            FROM solicitudes_modificacion
            WHERE nota_id = :nota_id_chk
            AND maestro_id = :maestro_id_chk
            AND estado = 'pendiente'
            LIMIT 1
        )
    ");
    $db->bind(':nota_id', $nota_id);
    $db->bind(':maestro_id', $maestro_id);
    $db->bind(':razon', $razon);
    $db->bind(':nota_id_chk', $nota_id);
    $db->bind(':maestro_id_chk', $maestro_id);
    $db->execute();

    if ((int) $db->rowCount() === 1) {
        // Se insertó: marcamos la nota
        $db->query('UPDATE notas SET solicitud_revision = 1 WHERE id = :id');
        $db->bind(':id', $nota_id);
        $db->execute();

        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Solicitud enviada.']);
        exit;
    }

    // No se insertó porque ya había una PENDIENTE
    $db->commit();
    echo json_encode([
        'success' => false,
        'code' => 'DUP_SOLICITUD',
        'message' => 'Ya existía una solicitud pendiente.'
    ]);
    exit;

} catch (Throwable $e) {
    // Rollback seguro si estaba en transacción
    if (method_exists($db ?? null, 'inTransaction') && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode([
        'success' => false,
        'code' => 'SERVER_ERROR',
        'message' => 'Error del servidor.'
    ]);
    exit;
}