<?php
// Incluir el archivo de configuración para acceder a las configuraciones globales y proteger la página
require_once __DIR__ . '/../includes/config.php';
protegerPagina([1]); // Solo admin

// Crear una instancia de la base de datos
$db = new Database();

// Obtener ID del grupo desde la URL
$grupo_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Obtener información del grupo
$db->query("SELECT g.*, u.nombre_completo as maestro 
           FROM grupos g 
           LEFT JOIN usuarios u ON g.maestro_id = u.id 
           WHERE g.id = :id");
$db->bind(':id', $grupo_id);
$grupo = $db->single();

if (!$grupo) {
    // Si no se encuentra el grupo, redirigir con un mensaje de error
    $_SESSION['error'] = "Grupo no encontrado";
    header("Location: grupos.php");
    exit;
}

// Obtener estudiantes del grupo
$db->query("SELECT * FROM estudiantes 
           WHERE grupo_id = :grupo_id 
           ORDER BY nombre_completo");
$db->bind(':grupo_id', $grupo_id);
$estudiantes = $db->resultSet();

// Obtener maestros disponibles para asignar
$db->query("SELECT id, nombre_completo FROM usuarios WHERE rol_id = 3 ORDER BY nombre_completo");
$maestros = $db->resultSet();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($grupo->nombre) ?> - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-gray-100">
    <div class="flex">
        <!-- Sidebar -->
        <?php include './partials/sidebar.php'; ?>

        <!-- Contenido principal -->
        <div class="flex-1 p-8">
            <!-- Encabezado con información del grupo -->
            <div class="flex justify-between items-start mb-6">
                <div>
                    <h2 class="text-3xl font-bold"><?= htmlspecialchars($grupo->nombre) ?></h2>
                    <div class="flex items-center mt-2 text-gray-600">
                        <span class="mr-4"><i
                                class="fas fa-graduation-cap mr-2"></i><?= htmlspecialchars($grupo->grado) ?></span>
                        <span><i
                                class="fas fa-calendar-alt mr-2"></i><?= htmlspecialchars($grupo->ciclo_escolar) ?></span>
                    </div>
                </div>
                <a href="grupos.php" class="text-blue-500 hover:text-blue-700">
                    <i class="fas fa-arrow-left mr-2"></i>Volver a grupos
                </a>
            </div>

            <!-- Tarjeta de información del grupo -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <h3 class="font-semibold text-gray-700 mb-2">Maestro Asignado</h3>
                        <p class="text-lg">
                            <?= $grupo->maestro ? htmlspecialchars($grupo->maestro) : '<span class="text-gray-500">Sin asignar</span>' ?>
                        </p>
                    </div>

                    <div>
                        <h3 class="font-semibold text-gray-700 mb-2">Total Estudiantes</h3>
                        <p class="text-lg"><?= count($estudiantes) ?></p>
                    </div>

                    <div>
                        <h3 class="font-semibold text-gray-700 mb-2">Acciones</h3>
                        <div class="flex space-x-3">
                            <button
                                onclick="document.getElementById('modal-asignar-maestro').classList.remove('hidden')"
                                class="text-blue-500 hover:text-blue-700">
                                <i class="fas fa-chalkboard-teacher mr-1"></i> Asignar maestro
                            </button>
                            <a href="exportar_estudiantes.php?grupo_id=<?= $grupo_id ?>"
                                class="text-green-500 hover:text-green-700">
                                <i class="fas fa-file-export mr-1"></i> Exportar lista
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel de estudiantes -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="flex justify-between items-center p-4 border-b">
                    <h3 class="text-xl font-semibold">Lista de Estudiantes</h3>
                    <div class="flex space-x-3">
                        <button onclick="document.getElementById('modal-importar').classList.remove('hidden')"
                            class="flex items-center text-sm bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600">
                            <i class="fas fa-file-import mr-1"></i> Importar
                        </button>
                        <button onclick="document.getElementById('modal-agregar').classList.remove('hidden')"
                            class="flex items-center text-sm bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600">
                            <i class="fas fa-plus mr-1"></i> Agregar
                        </button>
                        <?php if (count($estudiantes) > 0): ?>
                        <button onclick="document.getElementById('modal-eliminar-todos').classList.remove('hidden')"
                            class="flex items-center text-sm bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600">
                            <i class="fas fa-trash-alt mr-1"></i> Eliminar Todos
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tabla de estudiantes -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    No.</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    NIE</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Nombre Completo</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (count($estudiantes) > 0): ?>
                                <?php foreach ($estudiantes as $index => $estudiante): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap"><?= $index + 1 ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($estudiante->id) ?></td>
                                        <td class="px-6 py-4"><?= htmlspecialchars($estudiante->nombre_completo) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <button
                                                onclick='editarEstudiante(<?= $estudiante->id ?>, <?= json_encode($estudiante->nombre_completo) ?>)'
                                                class="text-blue-500 hover:text-blue-700 mr-3">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="eliminarEstudiante(<?= $estudiante->id ?>)"
                                                class="text-red-500 hover:text-red-700">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                        No hay estudiantes registrados en este grupo
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para asignar maestro -->
    <div id="modal-asignar-maestro"
        class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-semibold">Asignar Maestro</h3>
                    <button onclick="cerrarModal('modal-asignar-maestro')"
                        class="text-gray-500 hover:text-gray-700">&times;</button>
                </div>
                <form action="asignar_maestro.php" method="POST">
                    <input type="hidden" name="grupo_id" value="<?= $grupo_id ?>">

                    <div class="mb-4">
                        <label for="maestro_id" class="block text-gray-700 mb-2">Seleccionar Maestro</label>
                        <select id="maestro_id" name="maestro_id" class="w-full px-3 py-2 border rounded-lg">
                            <option value="">-- Sin asignar --</option>
                            <?php foreach ($maestros as $maestro): ?>
                                <option value="<?= $maestro->id ?>" <?= $grupo->maestro_id == $maestro->id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($maestro->nombre_completo) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="cerrarModal('modal-asignar-maestro')"
                            class="px-4 py-2 border rounded-lg hover:bg-gray-100">
                            Cancelar
                        </button>
                        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para agregar estudiante -->
    <div id="modal-agregar"
        class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-semibold">Agregar Estudiante</h3>
                    <button onclick="cerrarModal('modal-agregar')"
                        class="text-gray-500 hover:text-gray-700">&times;</button>
                </div>
                <form id="form-agregar" method="POST" action="agregar_estudiante.php">
                    <input type="hidden" name="grupo_id" value="<?= $grupo_id ?>">

                    <div class="space-y-4">
                        <div>
                            <label for="nie" class="block text-gray-700 mb-2">NIE</label>
                            <input type="text" id="nie" name="id" class="w-full px-3 py-2 border rounded-lg" required autocomplete="off">
                        </div>

                        <div>
                            <label for="nombre" class="block text-gray-700 mb-2">Nombre Completo</label>
                            <input type="text" id="nombre" name="nombre_completo"
                                class="w-full px-3 py-2 border rounded-lg" required autocomplete="off">
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" onclick="cerrarModal('modal-agregar')"
                            class="px-4 py-2 border rounded-lg hover:bg-gray-100">
                            Cancelar
                        </button>
                        <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600">
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para editar estudiante -->
    <div id="modal-editar"
        class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-semibold">Editar Estudiante</h3>
                    <button onclick="cerrarModal('modal-editar')"
                        class="text-gray-500 hover:text-gray-700">&times;</button>
                </div>
                <form id="form-editar" method="POST" action="editar_estudiante.php">
                    <input type="hidden" id="edit-id" name="id">
                    <input type="hidden" name="grupo_id" value="<?= $grupo_id ?>">

                    <div class="space-y-4">
                        <div>
                            <label for="edit-nie" class="block text-gray-700 mb-2">NIE</label>
                            <input type="" id="edit-nie" name="id"
                                class="w-full px-3 py-2 border rounded-lg ouline-none border-none cursor-not-allowed focus:outline-none" required readonly>
                        </div>

                        <div>
                            <label for="edit-nombre" class="block text-gray-700 mb-2">Nombre Completo</label>
                            <input type="text" id="edit-nombre" name="nombre_completo"
                                class="w-full px-3 py-2 border rounded-lg" required autocomplete="off">
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" onclick="cerrarModal('modal-editar')"
                            class="px-4 py-2 border rounded-lg hover:bg-gray-100">
                            Cancelar
                        </button>
                        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">
                            Actualizar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para importar estudiantes -->
    <div id="modal-importar"
        class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-semibold">Importar Estudiantes</h3>
                    <button onclick="cerrarModal('modal-importar')"
                        class="text-gray-500 hover:text-gray-700">&times;</button>
                </div>
                <form action="importar_estudiantes.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="grupo_id" value="<?= $grupo_id ?>">

                    <div class="mb-4">
                        <label for="archivo" class="block text-gray-700 mb-2">Archivo Excel</label>
                        <input type="file" id="archivo" name="archivo" accept=".xlsx,.xls"
                            class="w-full px-3 py-2 border rounded-lg" required>
                        <p class="text-sm text-gray-500 mt-1">Formato: Columnas NIE y Nombre del estudiante</p>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="cerrarModal('modal-importar')"
                            class="px-4 py-2 border rounded-lg hover:bg-gray-100">
                            Cancelar
                        </button>
                        <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600">
                            Importar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para eliminar todos los estudiantes -->
    <div id="modal-eliminar-todos"
        class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-semibold text-red-600">Eliminar Todos los Estudiantes</h3>
                    <button onclick="cerrarModal('modal-eliminar-todos')"
                        class="text-gray-500 hover:text-gray-700">&times;</button>
                </div>
                <div class="mb-4">
                    <p class="text-gray-700">¿Estás seguro de que deseas eliminar todos los estudiantes de este grupo?</p>
                    <p class="text-red-600 font-semibold mt-2">Esta acción no se puede deshacer.</p>
                    <p class="text-sm text-gray-500 mt-2">Se eliminarán <?= count($estudiantes) ?> estudiantes.</p>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="cerrarModal('modal-eliminar-todos')"
                        class="px-4 py-2 border rounded-lg hover:bg-gray-100">
                        Cancelar
                    </button>
                    <button onclick="eliminarTodosEstudiantes()" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600">
                        Eliminar Todos
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Funciones para manejar los modales
        function cerrarModal(id) {
            document.getElementById(id).classList.add('hidden');
        }

        // Editar estudiante
        function editarEstudiante(id, nombre) {
            document.getElementById('edit-nie').value = id;
            document.getElementById('edit-nombre').value = nombre;
            document.getElementById('modal-editar').classList.remove('hidden');
        }

        // Eliminar estudiante individual
        function eliminarEstudiante(id) {
            if (confirm('¿Estás seguro de eliminar este estudiante?')) {
                fetch(`eliminar_estudiante.php?id=${id}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `grupo_id=<?= $grupo_id ?>`
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.message || 'Error al eliminar el estudiante');
                        }
                    });
            }
        }

        // Eliminar todos los estudiantes
        function eliminarTodosEstudiantes() {
            if (confirm('¿ESTÁS ABSOLUTAMENTE SEGURO? Se eliminarán TODOS los estudiantes de este grupo.')) {
                fetch(`eliminar_todos_estudiantes.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `grupo_id=<?= $grupo_id ?>`
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.message || 'Error al eliminar los estudiantes');
                        }
                    });
            }
        }

        // Enviar formulario de agregar con AJAX
        document.getElementById('form-agregar').addEventListener('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            fetch('agregar_estudiante.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Error al agregar el estudiante');
                    }
                });
        });

        // Enviar formulario de editar con AJAX
        document.getElementById('form-editar').addEventListener('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            fetch('editar_estudiante.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Error al actualizar el estudiante');
                    }
                });
        });
    </script>
</body>

</html>