<?php
require_once __DIR__ . '/../includes/config.php';
protegerPagina([2]); // Solo rector
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['ok' => false, 'message' => 'Método no permitido']);
        exit;
    }

    // Lee POST; si alguna instalación usa JSON, cae al body también
    $id     = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $accion = isset($_POST['accion']) ? trim($_POST['accion']) : '';
    if ($id <= 0 || $accion === '') {
        echo json_encode(['ok' => false, 'message' => 'Parámetros inválidos']);
        exit;
    }

    $db = new Database();

    // Aprobada / Rechazada: solo si está pendiente
    if ($accion === 'aprobada' || $accion === 'rechazada') {
        $db->query("SELECT id, nota_id FROM solicitudes_modificacion WHERE id = :id AND estado = 'pendiente' LIMIT 1");
        $db->bind(':id', $id, PDO::PARAM_INT);
        $sol = $db->single();
        if (!$sol) {
            echo json_encode(['ok' => false, 'message' => 'La solicitud no existe o ya fue gestionada.']);
            exit;
        }

        // Transacción por consistencia
        $db->beginTransaction();

        $db->query("UPDATE solicitudes_modificacion 
                    SET estado = :estado, fecha_resolucion = NOW()
                    WHERE id = :id");
        $db->bind(':estado', $accion);
        $db->bind(':id', $id, PDO::PARAM_INT);
        $db->execute();

        // Al resolverse, quita el flag y DESBLOQUEA la nota
        $db->query("UPDATE notas 
                    SET solicitud_revision = 0, bloqueada = 0
                    WHERE id = :nid");
        $db->bind(':nid', (int)$sol->nota_id, PDO::PARAM_INT);
        $db->execute();

        $db->commit();

        echo json_encode([
            'ok'      => true,
            'message' => ($accion === 'aprobada' ? 'Solicitud aprobada.' : 'Solicitud rechazada.')
        ]);
        exit;
    }

    // Eliminar: solo si NO está pendiente
    if ($accion === 'eliminar') {
        // Verifica estado para mensaje más claro
        $db->query("SELECT estado FROM solicitudes_modificacion WHERE id = :id LIMIT 1");
        $db->bind(':id', $id, PDO::PARAM_INT);
        $info = $db->single();
        if (!$info) {
            echo json_encode(['ok' => false, 'message' => 'No existe la solicitud.']);
            exit;
        }
        if ($info->estado === 'pendiente') {
            echo json_encode(['ok' => false, 'message' => 'No se puede eliminar una solicitud pendiente.']);
            exit;
        }

        $db->query("DELETE FROM solicitudes_modificacion WHERE id = :id LIMIT 1");
        $db->bind(':id', $id, PDO::PARAM_INT);
        $db->execute();

        if ($db->rowCount() === 1) {
            echo json_encode(['ok' => true, 'message' => 'Solicitud eliminada.']);
        } else {
            echo json_encode(['ok' => false, 'message' => 'No se pudo eliminar (no afectó filas).']);
        }
        exit;
    }

    echo json_encode(['ok' => false, 'message' => 'Acción no soportada']);
} catch (Throwable $e) {
    // TEMP: devuélvelo para depurar (si prefieres, loguea y deja mensaje genérico en prod)
    echo json_encode([
        'ok' => false,
        'message' => 'Error del servidor',
        'debug' => $e->getMessage()
    ]);
}