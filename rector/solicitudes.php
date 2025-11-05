<?php
require_once __DIR__ . '/../includes/config.php';
protegerPagina([2]); // Solo rector


$db->query("
  SELECT s.*, n.calificacion AS nota_valor, m.nombre AS nombre_materia,
         u.nombre_completo AS nombre_maestro, e.nombre_completo AS nombre_estudiante
  FROM solicitudes_modificacion s
  JOIN notas n ON s.nota_id = n.id
  JOIN actividades a ON n.actividad_id = a.id
  JOIN materias m ON a.materia_id = m.id
  JOIN usuarios u ON s.maestro_id = u.id
  JOIN estudiantes e ON n.estudiante_id = e.id
  ORDER BY (s.estado='pendiente') DESC, s.fecha_solicitud DESC
  LIMIT 200
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
     <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> 
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
            case 'aprobada':
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
                                                    case 'aprobada':
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

                                            <!-- Botones con el nuevo sistema de confirmación -->
                                            <?php if ($solicitud->estado === 'pendiente'): ?>
                                              <div class="mt-3 flex gap-2">
                                                <button
                                                  class="px-3 py-1 rounded-lg bg-green-600 text-white hover:bg-green-700 transition"
                                                  onclick="confirmarGestion(<?= (int)$solicitud->id ?>, 'aprobada')">
                                                  Aceptar
                                                </button>

                                                <button
                                                  class="px-3 py-1 rounded-lg bg-red-600 text-white hover:bg-red-700 transition"
                                                  onclick="confirmarGestion(<?= (int)$solicitud->id ?>, 'rechazada')">
                                                  Rechazar
                                                </button>
                                              </div>
                                            <?php else: ?>
                                              <div class="mt-3">
                                                <button
                                                  class="px-3 py-1 rounded-lg bg-gray-600 text-white hover:bg-gray-700 transition"
                                                  onclick="confirmarEliminar(<?= (int)$solicitud->id ?>)">
                                                  Eliminar
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
    <script>
async function postAccion(id, accion) {
  const res = await fetch('gestionar_solicitud.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ id, accion })
  });
  const raw = await res.text();

  let data;
  try { data = JSON.parse(raw); }
  catch {
    await Swal.fire({icon:'error', title:'Respuesta no válida', text: raw.slice(0,400)});
    return { ok:false, message:'Respuesta no válida' };
  }

  // ← muestra detalle del servidor si viene
  if (!data.ok && data.debug) {
    console.warn('DEBUG:', data.debug);
    await Swal.fire({icon:'error', title:'Error del servidor', text:data.debug});
  }
  return data;
}


function iconoPorAccion(a){
  return a==='aprobada' ? 'success' : (a==='rechazada' ? 'error' : 'warning');
}

async function confirmarGestion(id, accion) {
  const txt = accion === 'aprobada'
    ? '¿Estás seguro de ACEPTAR? Esta acción es definitiva.'
    : '¿Estás seguro de RECHAZAR? Esta acción es definitiva.';

  const {isConfirmed} = await Swal.fire({
    title: 'Confirmación',
    text: txt,
    icon: iconoPorAccion(accion),
    showCancelButton: true,
    confirmButtonText: 'Sí, continuar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: accion==='aprobada' ? '#16a34a' : '#dc2626'
  });
  if (!isConfirmed) return;

  try {
    const data = await postAccion(id, accion);
    if (data.ok) {
      await Swal.fire({icon:'success', title:'Hecho', text:data.message || 'Operación realizada'});
      location.reload();
    } else {
      Swal.fire({icon:'warning', title:'Atención', text:data.message || 'No se pudo completar'});
    }
  } catch(e) {
    Swal.fire({icon:'error', title:'Error', text:e.message || 'Fallo de red'});
  }
}

async function confirmarEliminar(id) {
  const {isConfirmed} = await Swal.fire({
    title: 'Eliminar solicitud',
    text: '¿Deseas eliminar esta solicitud? Solo hazlo si ya fue gestionada.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Eliminar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#374151'
  });
  if (!isConfirmed) return;

  try {
    const data = await postAccion(id, 'eliminar');
    if (data.ok) {
      await Swal.fire({icon:'success', title:'Eliminada', text:data.message || 'Solicitud eliminada'});
      location.reload();
    } else {
      Swal.fire({icon:'warning', title:'Atención', text:data.message || 'No se pudo eliminar'});
    }
  } catch(e) {
    Swal.fire({icon:'error', title:'Error', text:e.message || 'Fallo de red'});
  }
}
</script>

</html>