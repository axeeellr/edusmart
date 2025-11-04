<?php
require_once __DIR__ . '/../includes/config.php';
protegerPagina([3]); // Solo maestros

$db = new Database();
$maestro_id = $_SESSION['user_id'];
$grupo_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$materia_id = isset($_GET['materia_id']) ? intval($_GET['materia_id']) : 0;
$trimestre = isset($_GET['trimestre']) ? intval($_GET['trimestre']) : 1;

// Verificar permisos del maestro sobre el grupo
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

// Obtener materias del maestro
$db->query("SELECT m.id, m.nombre 
           FROM materias m
           JOIN maestros_materias mm ON mm.materia_id = m.id
           WHERE mm.maestro_id = :maestro_id
           ORDER BY m.nombre");
$db->bind(':maestro_id', $maestro_id);
$materias = $db->resultSet();

// Si hay materia seleccionada, obtener actividades y calificaciones
if ($materia_id) {
    // Verificar que el maestro imparte esta materia
    $db->query("SELECT id FROM maestros_materias WHERE materia_id = :materia_id AND maestro_id = :maestro_id");
    $db->bind(':materia_id', $materia_id);
    $db->bind(':maestro_id', $maestro_id);
    $materia_valida = $db->single();

    if (!$materia_valida) {
        $_SESSION['error'] = "No tienes permiso para acceder a esta materia";
        header("Location: calificaciones.php?id=$grupo_id");
        exit;
    }

    // Obtener actividades del trimestre con fecha de entrega
    $db->query("SELECT a.id, a.nombre, a.porcentaje, a.fecha_entrega 
               FROM actividades a
               WHERE a.grupo_id = :grupo_id AND a.materia_id = :materia_id AND a.trimestre = :trimestre
               ORDER BY a.fecha_entrega");
    $db->bind(':grupo_id', $grupo_id);
    $db->bind(':materia_id', $materia_id);
    $db->bind(':trimestre', $trimestre);
    $actividades = $db->resultSet();

    // Obtener calificaciones
    $calificaciones = [];
    $promedios = [];

    if (!empty($estudiantes) && !empty($actividades)) {
        foreach ($estudiantes as $estudiante) {
            $total_puntos = 0;
            $total_porcentaje = 0;

            foreach ($actividades as $actividad) {
                $db->query("SELECT id, calificacion, bloqueada, solicitud_revision FROM notas WHERE estudiante_id = :estudiante_id AND actividad_id = :actividad_id");
                $db->bind(':estudiante_id', $estudiante->id);
                $db->bind(':actividad_id', $actividad->id);
                $nota = $db->single();

                $calificaciones[$estudiante->id][$actividad->id] = [
                    'id' => $nota ? $nota->id : null,
                    'calificacion' => $nota ? $nota->calificacion : null,
                    'bloqueada' => $nota ? $nota->bloqueada : 0,
                    'solicitud_revision' => $nota ? $nota->solicitud_revision : null
                ];
                // Calcular contribución al promedio
                if ($nota && $nota->calificacion !== null) {
                    $total_puntos += $nota->calificacion * ($actividad->porcentaje / 100);
                    $total_porcentaje += $actividad->porcentaje;
                }
            }

            // Calcular promedio del trimestre
            $promedios[$estudiante->id] = $total_porcentaje > 0 ? round($total_puntos / ($total_porcentaje / 100), 2) : null;
        }
    }

    // Obtener información de la materia
    $db->query("SELECT nombre FROM materias WHERE id = :materia_id");
    $db->bind(':materia_id', $materia_id);
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
            // Evita múltiples peticiones por el mismo input al mismo tiempo
            const bloqueados = new Set();

            document.querySelectorAll('.calificacion-input').forEach(input => {
                // Escuchar tanto "input" como "change"
                ['input', 'change'].forEach(evento => {
                    input.addEventListener(evento, async function () {
                        if (this.disabled) return; // Si está bloqueada, no hace nada
                        if (bloqueados.has(this)) return; // Evita doble envío

                        bloqueados.add(this); // Marca el input como ocupado temporalmente

                        try {
                            console.log("⏳ Enviando calificación...");
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

                            console.log("📡 Respuesta HTTP recibida:", response.status);

                            const text = await response.text();
                            console.log("📦 Texto recibido del servidor:", text);

                            let data;
                            try {
                                data = JSON.parse(text);
                            } catch (parseError) {
                                console.error("❌ Error al parsear JSON:", parseError);
                                throw new Error("Respuesta no válida del servidor: " + text);
                            }

                            if (data.success) {
                                alertify.success(`Calificación actualizada (${this.value})`);
                            } else {
                                alertify.error(data.message || 'Error al actualizar la calificación');
                            }

                        } catch (error) {
                            console.error("🔥 Error global en fetch:", error);
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
                    if (data.nota_id) {
                        this.dataset.notaId = data.nota_id;
                        console.log("🆕 Nuevo nota_id asignado al input:", data.nota_id);
                    }

                    alertify.success(`Calificación actualizada (${this.value})`);
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

            // Si el campo está vacío, no mostrar alerta (por si el maestro aún no ha ingresado nada)
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
            <!-- Encabezado y pestañas (mantener igual que antes) -->
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
                                                    class="px-6 py-3 text-center font-medium text-gray-500 uppercase tracking-wider">
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
                                                    <?= htmlspecialchars($estudiante->nombre_completo) ?>
                                                    <?php if ($estudiante->id): ?>
                                                        <div class="text-sm text-gray-500">NIE: <?= $estudiante->id ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <?php foreach ($actividades as $actividad):
                                                    $nota = $calificaciones[$estudiante->id][$actividad->id] ?? null;
                                                    ?>
                                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                                        <div class="flex items-center justify-center space-x-2">
                                                            <input type="number" step="0.1" min="0" max="10"
                                                                class="w-20 p-1 border rounded calificacion-input text-center"
                                                                value="<?= $nota['calificacion'] ?? '' ?>"
                                                                data-nota-id="<?= $nota['id'] ?? '' ?>"
                                                                data-estudiante="<?= $estudiante->id ?>"
                                                                data-estudiante-nombre="<?= htmlspecialchars($estudiante->nombre_completo) ?>"
                                                                data-actividad="<?= $actividad->id ?>"
                                                                data-actividad-nombre="<?= htmlspecialchars($actividad->nombre) ?>"
                                                                data-porcentaje="<?= $actividad->porcentaje ?>" placeholder="0.0"
                                                                <?= ($nota['bloqueada'] == 1) ? 'disabled' : '' ?>>
                                                            <!-- Botón de guardar -->
                                                            <?php if (($nota['bloqueada'] ?? 0) == 1): ?><button
                                                                    class="bg-gray-500 text-white p-2 rounded"
                                                                    onclick="solicitarModificacion(<?= $nota['id'] ?>)"><i
                                                                        class="fas fa-exclamation-circle"></i> Solicitar
                                                                    Modificacion</button><?php else: ?>
                                                                <button type="button"
                                                                    class="bg-blue-500 hover:bg-blue-600 text-white p-2 rounded guardar-btn"
                                                                    title="Guardar calificación"
                                                                    onclick="guardarCalificacion(this.previousElementSibling)">
                                                                    <i class="fas fa-save"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                <?php endforeach; ?>
                                                <td class="px-6 py-4 whitespace-nowrap text-center font-semibold promedio">
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