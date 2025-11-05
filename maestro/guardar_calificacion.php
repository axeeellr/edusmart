<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'functions.php';

protegerPagina([3]); // Solo maestros
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$db = new Database();
$data = json_decode(file_get_contents('php://input'), true);

// Validar datos obligatorios
if (
    !isset($data['estudiante_id']) ||
    !isset($data['actividad_id']) ||
    !isset($data['calificacion']) ||
    !isset($data['accion'])
) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

// Normalizar valores
$accion = $data['accion'];

if ($calificacion === null || $calificacion < 0 || $calificacion > 10) {
    echo json_encode(['success' => false, 'message' => 'Calificación inválida']);
    exit;
}

if ($actividad_id <= 0 || $estudiante_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Parámetros incompletos']);
    exit;
}

try {
    // Verificar permiso del maestro
    $db->query("
        SELECT a.id
        FROM actividades a
        JOIN grupos g ON a.grupo_id = g.id
        JOIN maestros_materias mm ON a.materia_id = mm.materia_id
        WHERE a.id = :actividad_id
        AND g.maestro_id = :maestro_id
        AND mm.maestro_id = :maestro_id
    ");
    $db->bind(':actividad_id', $data['actividad_id']);
    $db->bind(':maestro_id', $_SESSION['user_id']);
    $actividad = $db->single();

    if (!$actividad) {
        echo json_encode(['success' => false, 'message' => 'No tienes permiso para modificar esta actividad']);
        exit;
    }

    // Verificar que el estudiante pertenece al grupo de la actividad
    $db->query("
        SELECT e.id
        FROM estudiantes e
        JOIN actividades a ON e.grupo_id = a.grupo_id
        WHERE e.id = :estudiante_id AND a.id = :actividad_id
    ");
    $db->bind(':estudiante_id', $data['estudiante_id']);
    $db->bind(':actividad_id', $data['actividad_id']);
    $estudiante = $db->single();

    if (!$estudiante) {
        echo json_encode(['success' => false, 'message' => 'El estudiante no pertenece al grupo de esta actividad']);
        exit;
    }

    // Acción principal
    switch ($accion) {
        case 'actualizar':
            $notaIdGenerada = null;

            if (!empty($data['nota_id'])) {

                // Actualizar nota existente (solo si no está bloqueada)
                $db->query("UPDATE notas SET calificacion = :calificacion 
                        WHERE id = :id AND bloqueada = 0");
                $db->bind(':calificacion', $calificacion);
                $db->bind(':id', $data['nota_id']);
                $db->execute();

            } else {
                // Solo insertar si no existe una nota previa
                if ($calificacion !== null) {
                    $db->query("
                SELECT id FROM notas 
                WHERE estudiante_id = :estudiante_id 
                AND actividad_id = :actividad_id
                LIMIT 1
            ");
                    $db->bind(':estudiante_id', $data['estudiante_id']);
                    $db->bind(':actividad_id', $data['actividad_id']);
                    $existe = $db->single();

                    if (!$existe) {
                        $db->query("
                    INSERT INTO notas (estudiante_id, actividad_id, calificacion, bloqueada)
                    VALUES (:estudiante_id, :actividad_id, :calificacion, 0)
                ");
                        $db->bind(':estudiante_id', $data['estudiante_id']);
                        $db->bind(':actividad_id', $data['actividad_id']);
                        $db->bind(':calificacion', $calificacion);
                        $db->execute();

                        // Recuperar el ID insertado
                        $notaIdGenerada = $db->lastInsertId();
                    }
                }
            }

            // Si se generó un nuevo ID, devolverlo al frontend
            echo json_encode(['success' => true, 'nota_id' => $notaIdGenerada]);
            exit;

        case 'bloquear':
            // Guardar o actualizar y luego bloquear
            if (!empty($data['nota_id'])) {
                $db->query("
                    UPDATE notas 
                    SET calificacion = :calificacion, bloqueada = 1
                    WHERE id = :id
                ");
                $db->bind(':calificacion', $calificacion);
                $db->bind(':id', $data['nota_id']);
            } else {
                $db->query("
                    INSERT INTO notas (estudiante_id, actividad_id, calificacion, bloqueada)
                    VALUES (:estudiante_id, :actividad_id, :calificacion, 1)
                ");
                $db->bind(':estudiante_id', $data['estudiante_id']);
                $db->bind(':actividad_id', $data['actividad_id']);
                $db->bind(':calificacion', $calificacion);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            exit;
    }

    $db->execute();
    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
}
