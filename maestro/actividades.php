<?php
require_once __DIR__ . '/../includes/config.php';
protegerPagina([3]); // Solo maestros

$db = new Database();
$maestro_id = $_SESSION['user_id'];
$grupo_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$materia_id = isset($_GET['materia_id']) ? intval($_GET['materia_id']) : 0;
$trimestre = isset($_GET['trimestre']) ? intval($_GET['trimestre']) : 1;

// Verificar que el grupo pertenece al maestro
$db->query("SELECT g.* FROM grupos g WHERE g.id = :grupo_id AND g.maestro_id = :maestro_id");
$db->bind(':grupo_id', $grupo_id);
$db->bind(':maestro_id', $maestro_id);
$grupo = $db->single();

if (!$grupo) {
    $_SESSION['error'] = "Grupo no encontrado o no tienes permisos";
    header("Location: grupos.php");
    exit;
}

// Obtener materias que imparte el maestro en este grupo
$db->query("SELECT m.id, m.nombre 
           FROM materias m
           JOIN maestros_materias mm ON mm.materia_id = m.id
           WHERE mm.maestro_id = :maestro_id
           ORDER BY m.nombre");
$db->bind(':maestro_id', $maestro_id);
$materias = $db->resultSet();

// Si hay materia seleccionada, obtener actividades
if ($materia_id) {
    // Verificar que el maestro imparte esta materia
    $db->query("SELECT id FROM maestros_materias WHERE materia_id = :materia_id AND maestro_id = :maestro_id");
    $db->bind(':materia_id', $materia_id);
    $db->bind(':maestro_id', $maestro_id);
    $materia_valida = $db->single();

    if (!$materia_valida) {
        $_SESSION['error'] = "No tienes permiso para acceder a esta materia";
        header("Location: actividades.php?id=$grupo_id");
        exit;
    }

    // Obtener actividades
    $db->query("SELECT a.id, a.nombre, a.descripcion, a.porcentaje, a.trimestre,a.fecha_entrega
            FROM actividades a
            WHERE a.grupo_id = :grupo_id AND a.materia_id = :materia_id AND a.trimestre = :trimestre
            ORDER BY a.fecha_entrega");
    $db->bind(':grupo_id', $grupo_id);
    $db->bind(':materia_id', $materia_id);
    $db->bind(':trimestre', $trimestre);
    $actividades = $db->resultSet();

    // Obtener información de la materia
    $db->query("SELECT nombre FROM materias WHERE id = :materia_id");
    $db->bind(':materia_id', $materia_id);
    $materia_actual = $db->single();

    // Calcular porcentaje total por trimestre
    $porcentajes_trimestre = [1 => 0, 2 => 0, 3 => 0];
    foreach ($actividades as $actividad) {
        $porcentajes_trimestre[$actividad->trimestre] += $actividad->porcentaje;
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actividades - <?= htmlspecialchars($grupo->nombre) ?> - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-gray-100">
    <div class="flex">
        <?php include './partials/sidebar.php'; ?>

        <div class="flex-1 p-8">

            <div class="flex justify-between items-start mb-6">
                <div>
                    <h2 class="text-3xl font-bold">Actividades - <?= htmlspecialchars($grupo->nombre) ?></h2>
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

            <!-- Muestra los errores waza -->
            <?php if (!empty($_SESSION['success'])): ?>
                <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                    <i class="fas fa-check-circle mr-2"></i> <?= htmlspecialchars($_SESSION['success']) ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (!empty($_SESSION['error'])): ?>
                <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                    <i class="fas fa-exclamation-circle mr-2"></i> <?= htmlspecialchars($_SESSION['error']) ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

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
                            class="inline-block p-4 border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300">
                            <i class="fas fa-graduation-cap mr-2"></i> Calificaciones
                        </a>
                    </li>
                    <li class="mr-2">
                        <a href="actividades.php?id=<?= $grupo_id ?>"
                            class="inline-block p-4 border-b-2 border-blue-500 text-blue-600">
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
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-semibold"><?= htmlspecialchars($materia_actual->nombre) ?></h3>
                        <?php if ($materia_id && $trimestre): ?>
                            <?php if ($porcentajes_trimestre[$trimestre] < 100): ?>
                                <button onclick="mostrarFormulario()"
                                    class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                                    <i class="fas fa-plus mr-1"></i> Nueva Actividad
                                </button>
                            <?php else: ?>
                                <button disabled class="bg-gray-400 text-white px-4 py-2 rounded cursor-not-allowed"
                                    title="El trimestre ya alcanzó el 100%">
                                    <i class="fas fa-ban mr-1"></i> No se pueden agregar más
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Resumen de porcentajes por trimestre -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <?php for ($i = 1; $i <= 3; $i++): ?>
                            <div
                                class="bg-white p-4 rounded-lg shadow border-l-4 <?= $porcentajes_trimestre[$i] > 100 ? 'border-red-500' : 'border-blue-500' ?>">
                                <h4 class="font-medium mb-2">Trimestre <?= $i ?></h4>
                                <p class="text-lg <?= $porcentajes_trimestre[$i] > 100 ? 'text-red-600' : 'text-gray-700' ?>">
                                    <?= $porcentajes_trimestre[$i] ?>% / 100%
                                </p>
                                <?php if ($porcentajes_trimestre[$i] > 100): ?>
                                    <p class="text-sm text-red-500 mt-1">¡El porcentaje total excede el 100%!</p>
                                <?php endif; ?>
                            </div>
                        <?php endfor; ?>
                    </div>

                    <!-- Formulario para nueva actividad (oculto inicialmente) -->
                    <div id="formulario-actividad" class="bg-white p-6 rounded-lg shadow mb-6 hidden">
                        <h3 class="text-xl font-semibold mb-4">Nueva Actividad</h3>
                        <form action="guardar_actividad.php" method="POST" class="space-y-4">
                            <input type="hidden" name="grupo_id" value="<?= $grupo_id ?>">
                            <input type="hidden" name="materia_id" value="<?= $materia_id ?>">

                            <!-- En la sección del formulario de nueva actividad -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                                    <input type="text" name="nombre" class="w-full p-2 border rounded" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Porcentaje</label>
                                    <input type="number" name="porcentaje" step="0.1" min="0" max="100"
                                        class="w-full p-2 border rounded" required>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Trimestre</label>
                                    <select name="trimestre" class="w-full p-2 border rounded" required>
                                        <?php for ($i = 1; $i <= 3; $i++): ?>
                                            <option value="<?= $i ?>">Trimestre <?= $i ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha de Entrega</label>
                                    <input type="date" name="fecha_entrega" class="w-full p-2 border rounded" required>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                                <textarea name="descripcion" class="w-full p-2 border rounded" required></textarea>
                            </div>

                            <div class="flex justify-end space-x-3">
                                <button type="button" onclick="ocultarFormulario()"
                                    class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                                    Cancelar
                                </button>
                                <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                                    Guardar Actividad
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Lista de actividades -->
                    <?php if (empty($actividades)): ?>
                        <div class="bg-white p-6 rounded-lg shadow text-center">
                            <p class="text-gray-500">No hay actividades registradas para esta materia.</p>
                        </div>
                    <?php else: ?>
                        <div class="bg-white rounded-lg shadow overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="min-w-full">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">
                                                Nombre</th>
                                            <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">
                                                Descripción</th>
                                            <th
                                                class="px-6 py-3 text-center font-medium text-gray-500 uppercase tracking-wider">
                                                Porcentaje</th>
                                            <th
                                                class="px-6 py-3 text-center font-medium text-gray-500 uppercase tracking-wider">
                                                Trimestre</th>
                                            <th
                                                class="px-6 py-3 text-center font-medium text-gray-500 uppercase tracking-wider">
                                                Fecha</th>
                                            <th
                                                class="px-6 py-3 text-center font-medium text-gray-500 uppercase tracking-wider">
                                                Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($actividades as $actividad): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <?= htmlspecialchars($actividad->nombre) ?>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <?= htmlspecialchars($actividad->descripcion) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                                    <?= $actividad->porcentaje ?>%
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                                    <?= $actividad->trimestre ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                                    <?= date('d/m/Y', strtotime($actividad->fecha_entrega)) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                                    <a href="calificaciones.php?id=<?= $grupo_id ?>&materia_id=<?= $materia_id ?>&trimestre=<?= $actividad->trimestre ?>"
                                                        class="text-blue-500 hover:text-blue-700 mr-2">
                                                        <i class="fas fa-graduation-cap mr-1"></i> Calificaciones
                                                    </a>
                                                    <a href="#" onclick="abrirModalEditar(
                                                        <?= $actividad->id ?>,
                                                      '<?= addslashes(htmlspecialchars($actividad->nombre)) ?>',
                                                      '<?= addslashes(htmlspecialchars($actividad->descripcion)) ?>',
                                                       <?= $actividad->porcentaje ?>,
                                                       <?= $materia_id ?>,
                                                       <?= $actividad->trimestre ?>
                                                        )" class="text-green-500 hover:text-green-700 mr-2">
                                                        <i class="fas fa-edit mr-1"></i> Editar
                                                    </a>
                                                    <a href="#" onclick="eliminarActividad(<?= $actividad->id ?>)"
                                                        class="text-red-500 hover:text-red-700">
                                                        <i class="fas fa-trash mr-1"></i> Eliminar
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="bg-white p-6 rounded-lg shadow text-center">
                    <p class="text-gray-500">Seleccione una materia para ver las actividades.</p>
                </div>
            <?php endif; ?>
        </div>
        <!-- Modal de edición -->
        <div id="modalEditarActividad"
            class="fixed inset-0 z-50 flex items-center justify-center hidden bg-black bg-opacity-50">
            <div class="bg-white rounded-lg shadow-lg w-full max-w-md p-6">
                <h2 class="text-xl font-semibold mb-4">Editar Actividad</h2>
                <form id="formEditarActividad" action="editar_actividad.php" method="POST">
                    <input type="hidden" id="editar_id" name="id">
                    <input type="hidden" id="editar_materia_id" name="materia_id">
                    <input type="hidden" id="editar_trimestre" name="trimestre">

                    <div class="mb-4">
                        <label for="editar_nombre" class="block font-medium">Nombre</label>
                        <input type="text" id="editar_nombre" name="nombre" class="w-full border rounded p-2" required>
                    </div>

                    <div class="mb-4">
                        <label for="editar_descripcion" class="block font-medium">Descripción</label>
                        <textarea id="editar_descripcion" name="descripcion" class="w-full border rounded p-2"
                            required></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="editar_porcentaje" class="block font-medium">Porcentaje</label>
                        <input type="number" id="editar_porcentaje" name="porcentaje" step="0.01" min="0" max="100"
                            class="w-full border rounded p-2" required>
                    </div>

                    <div id="editar_error" class="text-red-500 text-sm mb-2 hidden"></div>

                    <div class="flex justify-end">
                        <button type="button" onclick="cerrarModalEditar()"
                            class="mr-2 px-4 py-2 bg-gray-300 rounded">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            // Establecer fecha mínima como hoy en el formulario
            document.addEventListener('DOMContentLoaded', function () {
                const today = new Date().toISOString().split('T')[0];
                document.querySelector('input[name="fecha_entrega"]').min = today;

                // Si estás editando una actividad, puedes cargar su fecha aquí
                // document.querySelector('input[name="fecha_entrega"]').value = '<?= $actividad_editar->fecha_entrega ?? '' ?>';
            });
        </script>

        <script>
            function mostrarFormulario() {
                document.getElementById('formulario-actividad').classList.remove('hidden');
            }

            function ocultarFormulario() {
                document.getElementById('formulario-actividad').classList.add('hidden');
            }

            function editarActividad(id) {
                // Implementar lógica para editar actividad
                alert('Editar actividad ' + id);
            }
            function eliminarActividad(id) {
                mostrarConfirmacion(
                    '¿Está seguro de eliminar esta actividad? Esto también eliminará todas las calificaciones asociadas.',
                    confirmado => {
                        if (!confirmado) {
                            mostrarAlerta('Eliminación cancelada', false, false); // no recarga al cancelar
                            return;
                        }

                        fetch('eliminar_actividad.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ actividad_id: id })
                        })
                            .then(r => r.json())
                            .then(data => {
                                if (data.success) {
                                    mostrarAlerta('Eliminación completada', true, true); // mensaje fijo si se eliminó
                                } else {
                                    mostrarAlerta(data.message || 'No se pudo eliminar la actividad', false, false);
                                }
                            })
                            .catch(err => {
                                mostrarAlerta('Error al procesar la solicitud: ' + err, false, false);
                            });
                    }
                );
            }

            function mostrarAlerta(mensaje, exito = true, recargar = true) {
                const a = document.createElement('div');
                Object.assign(a.style, {
                    position: 'fixed',
                    top: '20px',
                    right: '20px',
                    padding: '15px 20px',
                    color: 'white',
                    fontWeight: 'bold',
                    borderRadius: '6px',
                    boxShadow: '0 4px 8px rgba(0,0,0,0.2)',
                    zIndex: 9999,
                    maxWidth: '300px',
                    opacity: 0,
                    transition: 'opacity 0.3s, transform 0.3s',
                    transform: 'translateY(-20px)',
                    backgroundColor: exito ? '#4CAF50' : '#F44336'
                });
                a.textContent = mensaje;
                document.body.appendChild(a);
                setTimeout(() => { a.style.opacity = '1'; a.style.transform = 'translateY(0)'; }, 10);
                setTimeout(() => {
                    a.style.opacity = '0';
                    a.style.transform = 'translateY(-20px)';
                    setTimeout(() => {
                        a.remove();
                        if (recargar && exito) window.location.reload();
                    }, 300);
                }, 1500);
            }

        </script>
        <script>
            function abrirModalEditar(id, nombre, descripcion, porcentaje, materia_id, trimestre) {
                const set = (el, val) => document.getElementById(el).value = val;
                set('editar_id', id); set('editar_nombre', nombre); set('editar_descripcion', descripcion);
                set('editar_porcentaje', porcentaje); set('editar_materia_id', materia_id); set('editar_trimestre', trimestre);
                document.getElementById('editar_error').classList.add('hidden');
                document.getElementById('modalEditarActividad').classList.remove('hidden');
            }

            function cerrarModalEditar() {
                document.getElementById('modalEditarActividad').classList.add('hidden');
            }

            // Confirmacion para enviar formulario
            const form = document.getElementById('formEditarActividad');
            form.addEventListener('submit', e => {
                e.preventDefault();

                mostrarConfirmacion('¿Desea guardar los cambios?', confirmado => {
                    if (!confirmado) return recargar('Actualización cancelada');

                    const datos = ['id', 'nombre', 'descripcion', 'porcentaje', 'materia_id', 'trimestre']
                        .reduce((obj, c) => ({ ...obj, [c]: document.getElementById(`editar_${c}`).value }), {});

                    fetch('editar_actividad.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(datos)
                    })
                        .then(r => r.json())
                        .then(d => recargar(d.message, d.success))
                        .catch(() => recargar('Error al procesar la solicitud', false));
                });
            });

            // Alertas de  confir
            function recargar(mensaje, exito = false) {
                const a = document.createElement('div');
                Object.assign(a.style, {
                    position: 'fixed', top: '20px', right: '20px', padding: '15px 20px', color: 'white', fontWeight: 'bold',
                    borderRadius: '6px', boxShadow: '0 4px 8px rgba(0,0,0,0.2)', zIndex: 9999, maxWidth: '300px',
                    opacity: 0, transition: 'opacity 0.3s, transform 0.3s', transform: 'translateY(-20px)',
                    backgroundColor: exito ? '#4CAF50' : '#F44336'
                });
                a.textContent = mensaje;
                document.body.appendChild(a);
                setTimeout(() => a.style.opacity = '1', 10);
                setTimeout(() => {
                    a.style.opacity = '0';
                    setTimeout(() => {
                        a.remove();
                        window.location.reload();
                    }, 300);
                }, 1500);
            }

            // Confirmación simple
            function mostrarConfirmacion(msg, callback) {
                const overlay = document.createElement('div');
                overlay.style = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:9998;';
                const modal = document.createElement('div');
                modal.style = 'background:white;padding:20px;border-radius:8px;text-align:center;max-width:400px;box-shadow:0 4px 12px rgba(0,0,0,0.3);';
                modal.innerHTML = `<p style="margin-bottom:20px;">${msg}</p>
                           <button id="confirmar" style="margin-right:10px;padding:10px 20px;border:none;border-radius:5px;background:#4CAF50;color:white;cursor:pointer;">Confirmar</button>
                           <button id="cancelar" style="padding:10px 20px;border:none;border-radius:5px;background:#F44336;color:white;cursor:pointer;">Cancelar</button>`;
                overlay.appendChild(modal);
                document.body.appendChild(overlay);

                overlay.querySelector('#confirmar').onclick = () => { callback(true); overlay.remove(); };
                overlay.querySelector('#cancelar').onclick = () => { callback(false); overlay.remove(); };
            }

        </script>



</body>

</html>