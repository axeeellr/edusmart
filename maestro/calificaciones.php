<?php
require_once __DIR__ . '/../includes/config.php';
protegerPagina([3]); // Solo maestros

$db = new Database();
$maestro_id = $_SESSION['user_id'];
$grupo_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$materia_id = isset($_GET['materia_id']) ? intval($_GET['materia_id']) : 0;
$trimestre = isset($_GET['trimestre']) ? intval($_GET['trimestre']) : 1;

// Permisos sobre el grupo 
$db->query("SELECT g.* FROM grupos g WHERE g.id = :grupo_id AND g.maestro_id = :maestro_id");
$db->bind(':grupo_id', $grupo_id);
$db->bind(':maestro_id', $maestro_id);
$grupo = $db->single();
if (!$grupo) {
    $_SESSION['error'] = "Grupo no encontrado o no tienes permisos";
    header("Location: grupos.php");
    exit;
}

// Obtener estudiantes del grupo 
$db->query("SELECT * FROM estudiantes WHERE grupo_id = :grupo_id ORDER BY nombre_completo");
$db->bind(':grupo_id', $grupo_id);
$estudiantes = $db->resultSet();

//  Materias del maestro, para el selector 
$db->query("
SELECT DISTINCT m.id, m.nombre
FROM materias m
INNER JOIN maestros_materias mm
        ON mm.materia_id = m.id
        AND mm.maestro_id = :maestro_id
ORDER BY m.nombre
");
$db->bind(':maestro_id', $maestro_id);
$materias = $db->resultSet();

/* ===== Si hay materia seleccionada, cargar actividades y notas ===== */
$actividades = [];
$calificaciones = [];
$promedios = [];
$materia_actual = null;

if ($materia_id) {
    // Valida que el maestro imparte esa materia
    $db->query("SELECT id FROM maestros_materias WHERE materia_id = :materia_id AND maestro_id = :maestro_id");
    $db->bind(':materia_id', $materia_id);
    $db->bind(':maestro_id', $maestro_id);
    $materia_valida = $db->single();
    if (!$materia_valida) {
        $_SESSION['error'] = "No tienes permiso para acceder a esta materia";
        header("Location: calificaciones.php?id=$grupo_id");
        exit;
    }

    // Obtener actividades del trimestre
    $db->query("SELECT a.id, a.nombre, a.porcentaje, a.fecha_entrega
            FROM actividades a
            WHERE a.grupo_id = :grupo_id AND a.materia_id = :materia_id AND a.trimestre = :trimestre
            ORDER BY a.fecha_entrega");
    $db->bind(':grupo_id', $grupo_id);
    $db->bind(':materia_id', $materia_id);
    $db->bind(':trimestre', $trimestre);
    $actividades = $db->resultSet();

    // Notas + estado solicitud normalizado
    if (!empty($estudiantes) && !empty($actividades)) {
        foreach ($estudiantes as $estudiante) {
            $total_puntos = 0;
            $total_porcentaje = 0;

            foreach ($actividades as $actividad) {
                $db->query("
            SELECT n.id, n.calificacion, n.bloqueada, n.solicitud_revision,
                (SELECT s.estado
                FROM solicitudes_modificacion s
                WHERE s.nota_id = n.id AND s.maestro_id = :maestro_id
                ORDER BY s.id DESC LIMIT 1) AS estado_solicitud
            FROM notas n
            WHERE n.estudiante_id = :est AND n.actividad_id = :act
            LIMIT 1
            ");
                $db->bind(':maestro_id', $maestro_id);
                $db->bind(':est', $estudiante->id);
                $db->bind(':act', $actividad->id);
                $nota = $db->single();

                $ult = $nota ? ($nota->estado_solicitud ?? null) : null;
                $ult = $ult ? mb_strtolower(trim($ult), 'UTF-8') : null;
                if (in_array($ult, ['aprobada', 'aprobado', 'aceptada', 'aceptado', 'aprobado(a)'], true))
                    $ult = 'aprobada';
                elseif (in_array($ult, ['rechazada', 'rechazado'], true))
                    $ult = 'rechazada';
                elseif ($ult === 'pendiente')
                    $ult = 'pendiente';
                else
                    $ult = null;

                $calificaciones[$estudiante->id][$actividad->id] = [
                    'id' => $nota ? $nota->id : null,
                    'calificacion' => $nota ? $nota->calificacion : null,
                    'bloqueada' => $nota ? (int) $nota->bloqueada : 0,
                    'solicitud_revision' => $nota ? (int) $nota->solicitud_revision : 0,
                    'estado_solicitud' => $ult,
                ];

                if ($nota && $nota->calificacion !== null) {
                    $total_puntos += $nota->calificacion * ($actividad->porcentaje / 100);
                    $total_porcentaje += $actividad->porcentaje;
                }
            }

            $promedios[$estudiante->id] = $total_porcentaje > 0 ? round($total_puntos / ($total_porcentaje / 100), 2) : null;
        }
    }

    // obtener solicitud de la materia
    $db->query("SELECT nombre FROM materias WHERE id = :id");
    $db->bind(':id', $materia_id);
    $materia_actual = $db->single();
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calificaciones - <?= htmlspecialchars($grupo->nombre) ?> - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="<?= ALERTIFY ?>"></script>
    <link rel="stylesheet" href="<?= ALERTIFY_CSS ?>">
    <script>

        document.addEventListener('DOMContentLoaded', () => {
            const bloqueados = new Set();

            document.querySelectorAll('.calificacion-input').forEach(input => {
                ['input', 'change'].forEach(evento => {
                    input.addEventListener(evento, async function () {
                        if (!validarCalificacion(this)) return;
                        if (this.disabled) return;
                        if (bloqueados.has(this)) return;

                        bloqueados.add(this);

                        try {
                            const response = await fetch('guardar_calificacion.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    nota_id: this.dataset.notaId,
                                    estudiante_id: this.dataset.estudiante,
                                    actividad_id: this.dataset.actividad,
                                    calificacion: this.value,
                                    accion: 'actualizar'
                                })
                            });

                            const text = await response.text();
                            const data = JSON.parse(text);

                            if (data.success) {
                                alertify.success(`Calificación actualizada (${this.value})`);
                            } else {
                                alertify.error(data.message || 'Error al actualizar la calificación');
                            }

                        } catch (error) {
                            alertify.error('Error: ' + error.message);
                        } finally {
                            setTimeout(() => bloqueados.delete(this), 1000);
                        }
                    });
                });
            });
        });

        async function guardarCalificacion(input) {
            const notaId = input.dataset.notaId;
            const estudianteId = input.dataset.estudiante;
            const actividadId = input.dataset.actividad;
            const calificacion = input.value;

            const estudianteNombre = input.dataset.estudianteNombre || `ID: ${estudianteId}`;
            const actividadNombre = input.dataset.actividadNombre || `Actividad ID: ${actividadId}`;

            const confirmacion = await Swal.fire({
                title: '¿Guardar y bloquear calificación?',
                html: `
            <div style="text-align: left;">
                <p><b>Estudiante:</b> ${estudianteNombre}</p>
                <p><b>Actividad:</b> ${actividadNombre}</p>
                <p><b>Calificación:</b> <span style="color:blue">${calificacion}</span></p>
                <p style="color:red;"><b>Nota:</b> Una vez guardada, la calificación quedará bloqueada.</p>
            </div>
        `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, guardar y bloquear',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33'
            });

            if (!confirmacion.isConfirmed) return;

            try {
                const response = await fetch('guardar_calificacion.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        nota_id: notaId,
                        estudiante_id: estudianteId,
                        actividad_id: actividadId,
                        calificacion: calificacion,
                        accion: 'bloquear'
                    })
                });

                const data = await response.json();

                if (data.success) {
                    alertify.success(`Calificación Guardada (${input.value})`);
                } else {
                    alertify.error(data.message || 'Error al actualizar la calificación');
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message,
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        }

        async function actualizarPromedio(estudianteId) {
            const fila = document.querySelector(`tr[data-estudiante="${estudianteId}"]`);
            const inputs = fila.querySelectorAll('.calificacion-input');
            let totalPuntos = 0;
            let totalPorcentaje = 0;

            inputs.forEach(input => {
                const porcentaje = parseFloat(input.dataset.porcentaje);
                const valor = parseFloat(input.value);

                if (!isNaN(valor)) {
                    totalPuntos += valor * (porcentaje / 100);
                    totalPorcentaje += porcentaje;
                }
            });

            if (totalPorcentaje > 0) {
                const promedio = (totalPuntos / (totalPorcentaje / 100)).toFixed(2);
                fila.querySelector('.promedio').textContent = promedio;
            } else {
                fila.querySelector('.promedio').textContent = 'N/A';
            }
        }

        function validarCalificacion(input) {
            let valor = parseFloat(input.value);

            // Si el campo está vacío, no mostrar alerta
            if (input.value.trim() === "") return true;

            // Validar que sea número entre 0 y 10
            if (isNaN(valor) || valor < 0 || valor > 10) {
                Swal.fire({
                    title: 'Valor inválido',
                    text: 'La calificación debe ser un número entre 0 y 10.',
                    icon: 'error',
                    confirmButtonText: 'Entendido',
                    confirmButtonColor: '#3085d6',
                }).then(() => {
                    input.value = ''; // limpiar campo
                    input.focus();    // volver a enfocar
                });
                return false;
            }

            // Redondear a dos decimales
            input.value = valor.toFixed(2);
            return true;
        }

        function solicitarModificacion(nota_id) {
            Swal.fire({
                title: 'Solicitar modificación',
                text: 'Escribe la razón o comentario para la solicitud:',
                input: 'textarea',
                inputPlaceholder: 'Ejemplo: Considero que la calificación no refleja el desempeño del estudiante...',
                inputAttributes: {
                    'aria-label': 'Razón de la solicitud'
                },
                showCancelButton: true,
                confirmButtonText: 'Enviar solicitud',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                inputValidator: (value) => {
                    if (!value) {
                        return 'Por favor escribe una razón para la solicitud.';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const razon = result.value;

                    fetch('solicitar_modificacion.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ nota_id, razon })
                    })
                        .then(res => res.json())
                        .then(data => {
                            Swal.fire({
                                icon: data.success ? 'success' : 'warning',
                                title: data.success ? 'Solicitud enviada' : 'Atención',
                                text: data.message
                            }).then(() => {
                                if (data.success) location.reload(); // Opcional: recarga
                            });
                        })
                        .catch(err => {
                            console.error(err);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Ocurrió un error al enviar la solicitud.'
                            });
                        });
                }
            });
        }

    </script>
</head>

<body class="bg-gray-100">
    <div class="flex">
        <?php include './partials/sidebar.php'; ?>

        <div class="flex-1 p-8">
            <!-- Encabezado y pestañas -->
            <div class="flex justify-between items-start mb-6">
                <div>
                    <h2 class="text-3xl font-bold">Calificaciones - <?= htmlspecialchars($grupo->nombre) ?></h2>
                    <div class="flex items-center mt-2 text-gray-600">
                        <span class="mr-4"><i
                                class="fas fa-graduation-cap mr-2"></i><?= htmlspecialchars($grupo->grado) ?></span>
                        <span><i
                                class="fas fa-calendar-alt mr-2"></i><?= htmlspecialchars($grupo->ciclo_escolar) ?></span>
                    </div>
                </div>
                <a href="ver_grupo.php?id=<?= $grupo_id ?>" class="text-blue-500 hover:text-blue-700">
                    <i class="fas fa-arrow-left mr-2"></i> Volver al grupo
                </a>
            </div>

            <!-- Pestañas -->
            <div class="mb-6 border-b border-gray-200">
                <ul class="flex flex-wrap -mb-px">
                    <li class="mr-2">
                        <a href="ver_grupo.php?id=<?= $grupo_id ?>"
                            class="inline-block p-4 border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300">
                            <i class="fas fa-users mr-2"></i> Estudiantes
                        </a>
                    </li>
                    <li class="mr-2">
                        <a href="calificaciones.php?id=<?= $grupo_id ?>"
                            class="inline-block p-4 border-b-2 border-blue-500 text-blue-600">
                            <i class="fas fa-graduation-cap mr-2"></i> Calificaciones
                        </a>
                    </li>
                    <li class="mr-2">
                        <a href="actividades.php?id=<?= $grupo_id ?>"
                            class="inline-block p-4 border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300">
                            <i class="fas fa-tasks mr-2"></i> Actividades
                        </a>
                    </li>
                </ul>
            </div>
            <!-- Filtros -->
            <div class="bg-white p-4 rounded-lg shadow mb-6">
                <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <input type="hidden" name="id" value="<?= $grupo_id ?>">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Materia</label>
                        <select name="materia_id" class="w-full p-2 border rounded" onchange="this.form.submit()">
                            <option value="">-- Seleccione una materia --</option>
                            <?php foreach ($materias as $materia): ?>
                                <option value="<?= $materia->id ?>" <?= $materia_id == $materia->id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($materia->nombre) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($materia_id): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Trimestre</label>
                            <select name="trimestre" class="w-full p-2 border rounded" onchange="this.form.submit()">
                                <?php for ($i = 1; $i <= 3; $i++): ?>
                                    <option value="<?= $i ?>" <?= $trimestre == $i ? 'selected' : '' ?>>
                                        Trimestre <?= $i ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Leyenda -->
            <div class="bg-white rounded-lg shadow p-3 mb-4">
                <div class="legend">
                    <span class="mx-4 px-2 rounded-sm bg-gray-100 text-gray-700"><i class="fas fa-lock"></i>
                        Bloqueada</span>
                    <span class="mx-4 px-2 rounded-sm bg-emerald-100 text-emerald-700"><span class="dot"
                            style="background:#10b981"></span> Solicitud enviada</span>
                    <span class="mx-4 px-2 rounded-sm bg-red-100 text-red-700"><span class="dot"
                            style="background:#ef4444"></span>
                        Solicitud rechazada</span>
                    <span class="mx-4 px-2 rounded-sm bg-blue-100 text-blue-700"><i class="fas fa-save"></i> Guardar y
                        bloquear</span>
                    <span class="mx-4 px-2 rounded-sm bg-gray-100 text-gray-700"><i
                            class="fas fa-exclamation-circle"></i> Pedir
                        modificación</span>
                </div>
            </div>

            <!-- Contenido principal -->
            <?php if ($materia_id): ?>
                <div class="mb-6">
                    <h3 class="text-xl font-semibold mb-2"><?= htmlspecialchars($materia_actual->nombre) ?> - Trimestre
                        <?= $trimestre ?>
                    </h3>

                    <?php if (empty($actividades)): ?>
                        <div class="bg-white p-6 rounded-lg shadow text-center">
                            <p class="text-gray-500">No hay actividades registradas para este trimestre.</p>
                            <a href="actividades.php?id=<?= $grupo_id ?>&materia_id=<?= $materia_id ?>"
                                class="mt-4 inline-block bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                                <i class="fas fa-plus mr-1"></i> Crear Actividad
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Tabla de calificaciones mejorada -->
                        <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
                            <div class="overflow-x-auto">
                                <table class="min-w-full">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">
                                                Estudiante</th>
                                            <?php foreach ($actividades as $actividad): ?>
                                                <th
                                                    class="px-4 py-3 text-center font-medium text-gray-500 uppercase tracking-wider">
                                                    <div class="flex flex-col items-center">
                                                        <span><?= htmlspecialchars($actividad->nombre) ?></span>
                                                        <span class="text-xs text-gray-500"><?= $actividad->porcentaje ?>%</span>
                                                        <span
                                                            class="text-xs text-blue-600"><?= date('d/m/Y', strtotime($actividad->fecha_entrega)) ?></span>
                                                    </div>
                                                </th>
                                            <?php endforeach; ?>
                                            <th
                                                class="px-6 py-3 text-center font-medium text-gray-500 uppercase tracking-wider">
                                                Promedio</th>
                                        </tr>
                                    </thead>

                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($estudiantes as $estudiante): ?>
                                            <tr data-estudiante="<?= $estudiante->id ?>">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-gray-900 font-medium">
                                                        <?= htmlspecialchars($estudiante->nombre_completo) ?></div>
                                                    <div class="text-sm text-gray-500">NIE: <?= $estudiante->id ?></div>
                                                </td>

                                                <?php foreach ($actividades as $actividad):
                                                    $notaData = $calificaciones[$estudiante->id][$actividad->id] ?? null;
                                                    $bloq = (int) ($notaData['bloqueada'] ?? 0);
                                                    $solRev = (int) ($notaData['solicitud_revision'] ?? 0);
                                                    $ultEst = $notaData['estado_solicitud'] ?? null;
                                                    $disabled = $bloq ? 'disabled' : '';
                                                    ?>
                                                    <td class="px-3 py-3 whitespace-nowrap text-center">
                                                        <div class="flex items-center justify-center gap-2">
                                                            <input type="number" step="0.01" min="0" max="10"
                                                                class="w-20 text-center border border-gray-300 rounded-md p-1 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition calificacion-input"
                                                                value="<?= $notaData['calificacion'] ?? '' ?>"
                                                                data-nota-id="<?= $notaData['id'] ?? '' ?>"
                                                                data-estudiante="<?= $estudiante->id ?>"
                                                                data-estudiante-nombre="<?= htmlspecialchars($estudiante->nombre_completo) ?>"
                                                                data-actividad="<?= $actividad->id ?>"
                                                                data-actividad-nombre="<?= htmlspecialchars($actividad->nombre) ?>"
                                                                data-porcentaje="<?= $actividad->porcentaje ?>" <?= $disabled ?>>

                                                            <div class="flex items-center">
                                                                <?php if (!$bloq): ?>
                                                                    <button type="button" class="text-blue-600 hover:text-blue-700"
                                                                        title="Guardar y bloquear"
                                                                        onclick="guardarCalificacion(this.parentElement.previousElementSibling)">
                                                                        <i class="fas fa-save"></i>
                                                                    </button>
                                                                <?php else: ?>
                                                                    <?php if ($ultEst === 'pendiente' || $solRev == 1): ?>
                                                                        <span
                                                                            class="inline-flex items-center justify-center w-2 h-2 rounded-full bg-green-500"
                                                                            title="Solicitud enviada"></span>
                                                                    <?php elseif ($ultEst === 'rechazada'): ?>
                                                                        <span
                                                                            class="inline-flex items-center justify-center w-2 h-2 rounded-full bg-red-500"
                                                                            title="Solicitud rechazada"></span>
                                                                    <?php elseif ($ultEst === 'aprobada'): ?>
                                                                        <button type="button" class="text-gray-500" title="Bloqueada" disabled>
                                                                            <i class="fas fa-lock"></i>
                                                                        </button>
                                                                    <?php else: ?>
                                                                        <button type="button" class="text-gray-700 hover:text-gray-800"
                                                                            title="Solicitar modificación"
                                                                            onclick="solicitarModificacion(<?= (int) ($notaData['id'] ?? 0) ?>, this)">
                                                                            <i class="fas fa-exclamation-circle"></i>
                                                                        </button>
                                                                    <?php endif; ?>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                <?php endforeach; ?>

                                                <td class="px-6 py-4 whitespace-nowrap text-center font-semibold text-gray-800">
                                                    <?= $promedios[$estudiante->id] ?? 'N/A' ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Resumen estadístico -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div class="bg-white p-4 rounded-lg shadow border-l-4 border-blue-500">
                                <h4 class="font-medium mb-2">Promedio del grupo</h4>
                                <p class="text-2xl font-bold">
                                    <?php
                                    $suma_promedios = 0;
                                    $contador = 0;
                                    foreach ($promedios as $promedio) {
                                        if ($promedio !== null) {
                                            $suma_promedios += $promedio;
                                            $contador++;
                                        }
                                    }
                                    echo $contador > 0 ? round($suma_promedios / $contador, 2) : 'N/A';
                                    ?>
                                </p>
                            </div>
                            <div class="bg-white p-4 rounded-lg shadow border-l-4 border-green-500">
                                <h4 class="font-medium mb-2">Mejor promedio</h4>
                                <p class="text-2xl font-bold">
                                    <?= !empty(array_filter($promedios)) ? max(array_filter($promedios)) : '0' ?>
                                </p>
                            </div>
                            <div class="bg-white p-4 rounded-lg shadow border-l-4 border-red-500">
                                <h4 class="font-medium mb-2">Peor promedio</h4>
                                <p class="text-2xl font-bold">
                                    <?= !empty(array_filter($promedios)) ? min(array_filter($promedios)) : '0' ?>
                                </p>
                            </div>
                        </div>

                        <!-- Botones de acción -->
                        <div class="flex justify-between">
                            <a href="actividades.php?id=<?= $grupo_id ?>&materia_id=<?= $materia_id ?>"
                                class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 inline-block">
                                <i class="fas fa-tasks mr-1"></i> Gestionar Actividades
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="bg-white p-6 rounded-lg shadow text-center">
                    <p class="text-gray-500">Seleccione una materia para ver las calificaciones.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>