<?php
require_once __DIR__ . '/../includes/config.php';
protegerPagina([2]); // Solo rector


$db->query("
    SELECT s.*, 
    n.calificacion AS nota_valor, 
    m.nombre AS nombre_materia,
    u.nombre_completo AS nombre_maestro,
    e.nombre_completo AS nombre_estudiante
    FROM solicitudes_modificacion s
    JOIN notas n ON s.nota_id = n.id
    JOIN actividades a ON n.actividad_id = a.id
    JOIN materias m ON a.materia_id = m.id
    JOIN usuarios u ON s.maestro_id = u.id
    JOIN estudiantes e ON n.estudiante_id = e.id
    ORDER BY s.fecha_solicitud DESC
");
$solicitudes = $db->resultSet();

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Rector - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">

    <body class="bg-gray-100">

        <div class="flex min-h-screen">
            <?php include './partials/sidebar.php'; ?>

            <div class="flex-1 p-8">
                <h2 class="text-3xl font-bold mb-6 text-gray-700">Solicitudes de Modificación</h2>

                <!-- Alertas nativas -->
                <?php if (isset($error)): ?>
                    <script>alert('Error: <?php echo addslashes($error); ?>');</script>
                <?php endif; ?>
                <?php if (isset($_SESSION['success'])): ?>
                    <script>alert('<?php echo addslashes($_SESSION['success']); ?>');</script>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Lista de solicitudes -->
                    <div class="lg:col-span-3">
                        <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-200">
                            <h3 class="text-xl font-semibold mb-4 text-gray-800">Solicitudes Recientes</h3>

                            <?php if (!empty($solicitudes)): ?>
                                <div class="space-y-4">
                                    <?php foreach ($solicitudes as $solicitud): ?>
                                        <div class="p-4 rounded-lg border-l-4
        <?php
        switch ($solicitud->estado) {
            case 'pendiente':
                echo 'border-yellow-400 bg-yellow-50';
                break;
            case 'aceptada':
                echo 'border-green-400 bg-green-50';
                break;
            case 'rechazada':
                echo 'border-red-400 bg-red-50';
                break;
        }
        ?>
    ">
                                            <div class="flex justify-between items-center mb-2">
                                                <span
                                                    class="font-semibold text-gray-800"><?php echo htmlspecialchars($solicitud->nombre_maestro); ?></span>
                                                <span
                                                    class="text-sm text-gray-600"><?php echo date('d/m/Y H:i', strtotime($solicitud->fecha_solicitud)); ?></span>
                                            </div>
                                            <div class="mb-2 text-gray-700">
                                                <strong>Materia:</strong>
                                                <?php echo htmlspecialchars($solicitud->nombre_materia); ?>
                                            </div>
                                            <div class="mb-2 text-gray-700">
                                                <strong>Nota:</strong> <?php echo htmlspecialchars($solicitud->nota_valor); ?>
                                            </div>
                                            <div class="mb-2 text-gray-700">
                                                <strong>Razón:</strong> <?php echo htmlspecialchars($solicitud->razon); ?>
                                            </div>
                                            <div class="text-sm font-semibold mb-2">
                                                Estado:
                                                <span class="<?php
                                                switch ($solicitud->estado) {
                                                    case 'pendiente':
                                                        echo 'text-yellow-600';
                                                        break;
                                                    case 'aceptada':
                                                        echo 'text-green-600';
                                                        break;
                                                    case 'rechazada':
                                                        echo 'text-red-600';
                                                        break;
                                                }
                                                ?>">
                                                    <?php echo ucfirst($solicitud->estado); ?>
                                                </span>
                                            </div>

                                            <!-- Botones solo si está pendiente -->
                                            <?php if ($solicitud->estado === 'pendiente'): ?>
                                                <div class="mt-3 flex gap-2">
                                                    <button onclick="gestionarSolicitud(<?= $solicitud->id ?>, 'aceptada')"
                                                        class="px-3 py-1 rounded-lg bg-green-500 text-white hover:bg-green-600 transition">
                                                        Aceptar
                                                    </button>
                                                    <button onclick="gestionarSolicitud(<?= $solicitud->id ?>, 'rechazada')"
                                                        class="px-3 py-1 rounded-lg bg-red-500 text-white hover:bg-red-600 transition">
                                                        Rechazar
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-gray-500">No hay solicitudes de modificación.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>

</html>