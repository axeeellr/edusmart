<?php
require_once __DIR__ . '/../includes/config.php';
protegerPagina([2]); // Solo rector

$db = new Database();

// Obtener lista de grupos para filtro
$db->query("SELECT * FROM grupos ORDER BY grado, nombre");
$grupos = $db->resultSet();

// Procesar filtros
$grupo_id = isset($_GET['grupo_id']) ? intval($_GET['grupo_id']) : null;
$estudiante_id = isset($_GET['estudiante_id']) ? intval($_GET['estudiante_id']) : null;
$tipo_reporte = isset($_GET['tipo_reporte']) ? $_GET['tipo_reporte'] : 'grupal';

// Inicializar variables
$estudiantes_lista = [];
$info_grupo = null;
$reporte_grupal = [];
$reporte_individual = [];
$materias_grupo = [];
$sumatorias_materias = [];
$promedios_materias = [];
$info_estudiante = null;

if ($grupo_id) {
    // Obtener información del grupo
    $db->query("SELECT * FROM grupos WHERE id = :grupo_id");
    $db->bind(':grupo_id', $grupo_id);
    $info_grupo = $db->single();
    
    // Obtener estudiantes del grupo
    $db->query("SELECT id, nombre_completo FROM estudiantes WHERE grupo_id = :grupo_id ORDER BY nombre_completo");
    $db->bind(':grupo_id', $grupo_id);
    $estudiantes_lista = $db->resultSet();

    // Obtener todas las materias del grupo
    $db->query("SELECT DISTINCT m.id, m.nombre 
               FROM materias m 
               JOIN actividades a ON a.materia_id = m.id 
               WHERE a.grupo_id = :grupo_id 
               AND m.activa = 1 
               ORDER BY m.nombre");
    $db->bind(':grupo_id', $grupo_id);
    $materias_grupo = $db->resultSet();

    if ($tipo_reporte == 'grupal' && !empty($estudiantes_lista)) {
        // REPORTE GRUPAL COMPLETO
        // Inicializar arrays para promedios por materia
        foreach ($materias_grupo as $materia) {
            $sumatorias_materias[$materia->id] = 0;
            $promedios_materias[$materia->id] = 0;
        }

        foreach ($estudiantes_lista as $estudiante) {
            $promedios_materias_estudiante = [];
            $total_general = 0;
            $total_materias = 0;

            foreach ($materias_grupo as $materia) {
                // Calcular promedio final de la materia
                $db->query("SELECT AVG(trimestre_promedio) AS promedio_final
                           FROM (
                               SELECT SUM(n.calificacion * a.porcentaje / 100) / SUM(a.porcentaje / 100) AS trimestre_promedio
                               FROM actividades a
                               JOIN notas n ON n.actividad_id = a.id
                               WHERE n.estudiante_id = :estudiante_id
                                 AND a.materia_id = :materia_id
                               GROUP BY a.trimestre
                           ) t");
                $db->bind(':estudiante_id', $estudiante->id);
                $db->bind(':materia_id', $materia->id);
                $result = $db->single();
                
                $promedio_materia = $result && $result->promedio_final ? round($result->promedio_final, 2) : 0;
                $estado_materia = $promedio_materia >= 6 ? 'APROBADO' : 'REPROBADO';
                
                $promedios_materias_estudiante[$materia->id] = [
                    'nombre' => $materia->nombre,
                    'promedio' => $promedio_materia,
                    'estado' => $estado_materia
                ];
                
                // Acumular para promedios finales por materia
                $sumatorias_materias[$materia->id] += $promedio_materia;
                
                $total_general += $promedio_materia;
                $total_materias++;
            }

            $promedio_general = $total_materias > 0 ? round($total_general / $total_materias, 2) : 0;

            $reporte_grupal[] = [
                'id' => $estudiante->id,
                'estudiante' => $estudiante->nombre_completo,
                'promedios_materias' => $promedios_materias_estudiante,
                'promedio_general' => $promedio_general,
                'estado' => $promedio_general >= 6 ? 'APROBADO' : 'REPROBADO'
            ];
        }

        // Calcular promedios finales por materia
        $total_estudiantes = count($estudiantes_lista);
        foreach ($materias_grupo as $materia) {
            $promedios_materias[$materia->id] = $total_estudiantes > 0 ? 
                round($sumatorias_materias[$materia->id] / $total_estudiantes, 2) : 0;
        }

    } elseif ($tipo_reporte == 'individual' && $estudiante_id) {
        // REPORTE INDIVIDUAL
        $db->query("SELECT e.id, e.nombre_completo, g.nombre as grupo_nombre, g.grado 
                   FROM estudiantes e 
                   JOIN grupos g ON e.grupo_id = g.id 
                   WHERE e.id = :estudiante_id");
        $db->bind(':estudiante_id', $estudiante_id);
        $info_estudiante = $db->single();

        $total_general = 0;
        $total_materias = 0;

        foreach ($materias_grupo as $materia) {
            $db->query("SELECT AVG(trimestre_promedio) AS promedio_final
                       FROM (
                           SELECT SUM(n.calificacion * a.porcentaje / 100) / SUM(a.porcentaje / 100) AS trimestre_promedio
                           FROM actividades a
                           JOIN notas n ON n.actividad_id = a.id
                           WHERE n.estudiante_id = :estudiante_id
                             AND a.materia_id = :materia_id
                           GROUP BY a.trimestre
                       ) t");
            $db->bind(':estudiante_id', $estudiante_id);
            $db->bind(':materia_id', $materia->id);
            $result = $db->single();
            
            $promedio_final = $result && $result->promedio_final ? round($result->promedio_final, 2) : 0;
            
            $reporte_individual[] = [
                'materia' => $materia->nombre,
                'promedio_final' => $promedio_final,
                'estado' => $promedio_final >= 6 ? 'APROBADO' : 'REPROBADO'
            ];
            
            $total_general += $promedio_final;
            $total_materias++;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes Académicos - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Estilos base mejorados */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        /* Estilos para impresión mejorados */
        @media print {
            @page {
                size: landscape;
                margin: 15mm 10mm;
            }
            
            .no-print { 
                display: none !important; 
            }
            
            body { 
                background: white !important;
                margin: 0 !important;
                padding: 0 !important;
                font-size: 12px !important;
                font-family: 'Times New Roman', serif !important;
            }
            
            .print-container { 
                box-shadow: none !important; 
                border: none !important;
                margin: 0 !important;
                padding: 0 !important;
                page-break-inside: avoid;
            }
            
            .print-header { 
                display: block !important; 
                text-align: center; 
                margin-bottom: 20px;
                padding-bottom: 15px;
                border-bottom: 3px double #000;
            }
            
            .print-header h1 {
                font-size: 22px !important;
                font-weight: bold;
                margin-bottom: 8px;
                text-transform: uppercase;
            }
            
            .print-header h2 {
                font-size: 16px !important;
                font-weight: bold;
                margin-bottom: 5px;
            }
            
            .print-header p {
                font-size: 13px !important;
                font-style: italic;
            }
            
            .print-footer { 
                display: block !important; 
                text-align: center; 
                margin-top: 20px; 
                font-size: 10px;
                padding-top: 10px;
                border-top: 1px solid #ccc;
                position: fixed;
                bottom: 0;
                width: 100%;
            }
            
            .table-print { 
                border-collapse: collapse; 
                width: 100% !important;
                font-size: 11px;
                page-break-inside: auto;
            }
            
            .table-print th, .table-print td { 
                border: 1px solid #000 !important; 
                padding: 6px 8px !important;
                text-align: center;
                page-break-inside: avoid;
            }
            
            .table-print th {
                background-color: #f8f9fa !important;
                font-weight: bold;
            }
            
            .bg-print-header { 
                background-color: #e9ecef !important; 
            }
            
            .estudiante-column { text-align: left !important; }
            .no-print-column { display: none !important; }
            .bg-promedio-materia { 
                background-color: #e8f5e8 !important;
                font-weight: bold;
            }
            
            .print-only {
                display: block !important;
            }
            
            .tabla-estadistica {
                margin-top: 25px;
                width: 100%;
                border-collapse: collapse;
                font-size: 11px;
                page-break-inside: avoid;
            }
            
            .tabla-estadistica th, .tabla-estadistica td {
                border: 1px solid #000 !important;
                padding: 8px 10px !important;
                text-align: center;
            }
            
            .titulo-estadistica {
                font-size: 13px;
                font-weight: bold;
                margin-bottom: 8px;
                text-align: center;
                text-transform: uppercase;
            }
            
            .firmas-container {
                margin-top: 40px !important;
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                flex-wrap: wrap;
                page-break-inside: avoid;
            }

            .fecha-box {
                width: 100%;
                text-align: left;
                margin-bottom: 30px;
                font-weight: bold;
            }

            .firma-box {
                text-align: center;
                width: 48%;
            }

            /* Evitar que los totales se repitan en cada página */
            tfoot {
                display: table-row-group !important;
                page-break-inside: avoid;
            }
            
            /* Ocultar URL en impresión */
            .print-url {
                display: none !important;
            }
        }
        
        /* Estilos para web mejorados */
        .print-only {
            display: none;
        }
        
        .estado-aprobado { 
            color: #059669; 
            font-weight: bold;
            background-color: #f0fdf4;
        }
        
        .estado-reprobado { 
            color: #dc2626; 
            font-weight: bold;
            background-color: #fef2f2;
        }
        
        .bg-promedio { 
            background: linear-gradient(135deg, #fef3c7, #fde68a);
        }
        
        .bg-promedio-materia { 
            background: linear-gradient(135deg, #dcfce7, #bbf7d0);
        }
        
        .table-container {
            overflow-x: auto;
            max-width: 100%;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        /* Mejoras visuales generales */
        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .shadow-custom {
            box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .hover-lift {
            transition: all 0.3s ease;
        }
        
        .hover-lift:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            transform: translateY(-1px);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            border: none;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-success:hover {
            background: linear-gradient(135deg, #38a169 0%, #48bb78 100%);
            transform: translateY(-1px);
        }

        /* Responsive mejorado */
        @media (max-width: 768px) {
            .main-container {
                flex-direction: column;
            }
            
            .sidebar-container {
                width: 100%;
            }
            
            .content-container .p-6 {
                padding: 1rem;
            }
            
            .bg-white.rounded-xl {
                margin: 0.5rem;
            }
            
            .grid-cols-1.lg\:grid-cols-4 {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .flex.justify-between.items-center {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .flex.space-x-3 {
                width: 100%;
                justify-content: space-between;
            }
            
            .table-container {
                margin: 0 -0.5rem;
            }
            
            .table-print th, .table-print td {
                padding: 4px 6px !important;
                font-size: 10px;
            }
        }
        
        /* Estilos para ocultar/mostrar elementos */
        .hidden {
            display: none !important;
        }
        
        /* Mejoras en selects y inputs */
        select, input {
            transition: all 0.3s ease;
        }
        
        select:focus, input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="main-container flex min-h-screen">
        <!-- Sidebar -->
        <div class="sidebar-container no-print w-64 flex-shrink-0">
            <?php include './partials/sidebar.php'; ?>
        </div>

        <!-- Main Content -->
        <div class="content-container flex-1 min-w-0">
            <div class="p-6 no-print">
                <div class="glass-effect rounded-xl shadow-custom border border-gray-200 p-6 mb-6">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800">Reportes Académicos Finales</h1>
                            <p class="text-gray-600">Sistema de gestión de calificaciones</p>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="bg-blue-50 rounded-lg p-3">
                                <i class="fas fa-chart-line text-blue-500 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Filtros -->
                    <form action="reportes.php" method="GET" class="grid grid-cols-1 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-users mr-2"></i>Grupo
                            </label>
                            <select name="grupo_id" required
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors hover-lift">
                                <option value="">Seleccionar Grupo</option>
                                <?php foreach ($grupos as $grupo): ?>
                                    <option value="<?= $grupo->id ?>" <?= $grupo_id == $grupo->id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($grupo->grado) ?> - <?= htmlspecialchars($grupo->nombre) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-file-alt mr-2"></i>Tipo de Reporte
                            </label>
                            <select name="tipo_reporte" id="tipo_reporte"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors hover-lift">
                                <option value="grupal" <?= $tipo_reporte == 'grupal' ? 'selected' : '' ?>>Reporte Grupal Completo</option>
                                <option value="individual" <?= $tipo_reporte == 'individual' ? 'selected' : '' ?>>Reporte Individual</option>
                            </select>
                        </div>

                        <div id="estudiante_filter" class="<?= $tipo_reporte == 'individual' ? '' : 'hidden' ?>">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-user-graduate mr-2"></i>Estudiante
                            </label>
                            <select name="estudiante_id"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors hover-lift">
                                <option value="">Seleccionar Estudiante</option>
                                <?php foreach ($estudiantes_lista as $est): ?>
                                    <option value="<?= $est->id ?>" <?= $estudiante_id == $est->id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($est->nombre_completo) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="flex items-end">
                            <button type="submit"
                                class="w-full btn-primary font-medium py-2.5 px-6 rounded-lg transition-colors flex items-center justify-center hover-lift">
                                <i class="fas fa-search mr-2"></i>Generar Reporte
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Reporte Grupal Completo -->
            <?php if ($tipo_reporte == 'grupal' && !empty($reporte_grupal) && !empty($materias_grupo)): ?>
                <div class="glass-effect rounded-xl shadow-custom border border-gray-200 mx-6 mb-6 print-container">
                    <!-- Header para impresión - MEJORADO -->
                   <div class="print-header print-only">
    <!-- TEXTO LARGO (DIRECCIÓN, CÓDIGO, ETC.) - ARRIBA -->
    <p style="font-size:12px; text-align:justify; font-weight:normal; margin: 0 0 15px 0; line-height: 1.5;">
        Cuadro de evaluación final de <?= htmlspecialchars($info_grupo->grado) ?> grado sección <?= htmlspecialchars(trim(preg_replace('/\bgrado\b.*/i', '', $info_grupo->nombre))) ?>. 
        Centro Escolar José Dolores Larreynaga, Código de infraestructura 11171, 
        Avenida 3 de Mayo y 9° Calle Poniente N° 43, 
        N° de acuerdo de creación 15-4766 de fecha 30/06/1999. 
        Distrito de Quezaltepeque, Departamento de La Libertad.
    </p>

    <!-- NÓMINA DE ALUMNOS - ABAJO -->
    <p style="font-size:14px; text-align:center; font-weight:bold; margin: 0; text-transform: uppercase;">
        Nómina de Estudiantes – <?= htmlspecialchars($info_grupo->grado) ?> – <?= htmlspecialchars(ucwords(strtolower(trim(preg_replace('/\bgrado\b.*/i', '', $info_grupo->nombre))))) ?><br>
        Promedios Finales – Año Escolar <?= date('Y') ?>
    </p>
</div>

                    <div class="p-6 no-print">
                        <div class="flex justify-between items-center mb-6">
                            <div>
                                <h2 class="text-2xl font-bold text-gray-800">Nómina de Alumnos</h2>
                                <p class="text-gray-600 text-lg"><?= htmlspecialchars($info_grupo->grado) ?> - <?= htmlspecialchars($info_grupo->nombre) ?></p>
                                <p class="text-gray-500">Promedios Finales - Año Escolar <?= date('Y') ?></p>
                            </div>
                            <div class="flex space-x-3">
                                <button onclick="imprimirReporte()" 
                                    class="btn-success font-medium py-2.5 px-6 rounded-lg transition-colors flex items-center hover-lift">
                                    <i class="fas fa-print mr-2"></i>Imprimir
                                </button>
                                <button onclick="exportToExcel()"
                                    class="btn-primary font-medium py-2.5 px-6 rounded-lg transition-colors flex items-center hover-lift">
                                    <i class="fas fa-file-excel mr-2"></i>Excel
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="table-container px-6 pb-6">
                        <table class="min-w-full table-print">
                            <thead class="bg-gray-100 bg-print-header">
                                <tr>
                                    <th class="px-3 py-2 text-center text-sm font-bold text-gray-700 border border-gray-300">#</th>
                                    <th class="px-3 py-2 text-left text-sm font-bold text-gray-700 border border-gray-300 estudiante-column">Estudiante</th>
                                    <?php foreach ($materias_grupo as $materia): ?>
                                        <th class="px-2 py-2 text-center text-sm font-bold text-gray-700 border border-gray-300" title="<?= htmlspecialchars($materia->nombre) ?>">
                                            <?= htmlspecialchars($materia->nombre) ?>
                                        </th>
                                    <?php endforeach; ?>
                                    <th class="px-3 py-2 text-center text-sm font-bold text-gray-700 border border-gray-300 bg-promedio no-print-column">
                                        Promedio
                                    </th>
                                    <!--<th class="px-3 py-2 text-center text-sm font-bold text-gray-700 border border-gray-300 bg-promedio">
                                        Estado
                                    </th> -->
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reporte_grupal as $index => $estudiante): ?>
                                    <tr class="<?= $index % 2 == 0 ? 'bg-white' : 'bg-gray-50' ?>">
                                        <td class="px-3 py-2 text-sm text-gray-600 border border-gray-300 text-center"><?= $index + 1 ?></td>
                                        <td class="px-3 py-2 text-sm font-medium text-gray-900 border border-gray-300 estudiante-column">
                                            <?= htmlspecialchars($estudiante['estudiante']) ?>
                                        </td>
                                        <?php foreach ($materias_grupo as $materia): ?>
                                            <td class="px-2 py-2 text-sm text-center border border-gray-300 font-medium <?= $estudiante['promedios_materias'][$materia->id]['promedio'] < 6 ? 'estado-reprobado' : '' ?>">
                                                <?= $estudiante['promedios_materias'][$materia->id]['promedio'] ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <td class="px-3 py-2 text-sm text-center font-bold border border-gray-300 bg-promedio no-print-column <?= $estudiante['promedio_general'] < 6 ? 'estado-reprobado' : 'estado-aprobado' ?>">
                                            <?= $estudiante['promedio_general'] ?>
                                        </td>
                                        <!--<td class="px-3 py-2 text-sm text-center font-bold border border-gray-300 bg-promedio <?= $estudiante['estado'] == 'APROBADO' ? 'estado-aprobado' : 'estado-reprobado' ?>">
                                            <?= $estudiante['estado'] ?>
                                        </td> -->
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            
                            <!-- SECCIÓN: TOTALES Y PROMEDIOS POR MATERIA - SOLO AL FINAL -->
                            <tfoot class="bg-promedio-materia">
                                <tr>
                                    <td class="px-3 py-2 text-sm font-bold text-gray-900 border border-gray-300 text-center" colspan="2">
                                        TOTAL DE PUNTOS
                                    </td>
                                    <?php foreach ($materias_grupo as $materia): ?>
                                        <td class="px-2 py-2 text-sm text-center font-bold border border-gray-300">
                                            <?= number_format($sumatorias_materias[$materia->id], 2) ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="px-3 py-2 text-sm text-center font-bold border border-gray-300 no-print-column">
                                        -
                                    </td>
                                    <td class="px-3 py-2 text-sm text-center font-bold border border-gray-300">
                                        -
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2 text-sm font-bold text-gray-900 border border-gray-300 text-center" colspan="2">
                                        PROMEDIO POR MATERIA
                                    </td>
                                    <?php foreach ($materias_grupo as $materia): ?>
                                        <td class="px-2 py-2 text-sm text-center font-bold border border-gray-300 estado-aprobado">
                                            <?= number_format($promedios_materias[$materia->id], 2) ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="px-3 py-2 text-sm text-center font-bold border border-gray-300 no-print-column">
                                        -
                                    </td>
                                    <td class="px-3 py-2 text-sm text-center font-bold border border-gray-300">
                                        -
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- TABLA ESTADÍSTICA - SOLO PARA IMPRESIÓN -->
                    <div class="print-only px-6 pb-6">
                        <div class="titulo-estadistica">Estadística</div>
                        <table class="tabla-estadistica">
                            <thead>
                                <tr>
                                    <th rowspan="2">Género</th>
                                    <th colspan="4">Estadísticas</th>
                                </tr>
                                <tr>
                                    <th>Matrícula Inicial</th>
                                    <th>Retirados</th>
                                    <th>Retenidos</th>
                                    <th>Matrícula Final</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Masculino</strong></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td><strong>Femenino</strong></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td><strong>Total</strong></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Fecha y firmas -->
                        <div class="firmas-container mt-8">
                            <div class="fecha-box">
                                <p class="font-bold text-sm">Fecha: _________________________</p>
                            </div>
                            <div class="firma-box">
                                <div style="height: 60px; margin: 8px 0;"></div>
                                <div style="border-top: 1px solid black; width: 100%;"></div>
                                <p class="text-xs mt-1 text-gray-700">Maestro Guía</p>
                            </div>
                            <div class="firma-box">
                                <div style="height: 60px; margin: 8px 0;"></div>
                                <div style="border-top: 1px solid black; width: 100%;"></div>
                                <p class="text-xs mt-1 text-gray-700">Director</p>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-200 no-print">
                        <div class="flex justify-between items-center text-sm text-gray-600">
                            <div>
                                <span class="font-medium">Total de estudiantes:</span> <?= count($reporte_grupal) ?>
                            </div>
                            <div>
                                <span class="font-medium">Generado el:</span> <?= date('d/m/Y H:i') ?>
                            </div>
                        </div>
                    </div>

                    <!-- Footer para impresión -->
                    <div class="print-footer print-only">
                        <p>Escuela "José Dolores Larreynaga" | Total de estudiantes: <?= count($reporte_grupal) ?> | Generado el: <?= date('d/m/Y H:i') ?></p>
                    </div>
                </div>

            <!-- Reporte Individual -->
            <?php elseif ($tipo_reporte == 'individual' && !empty($reporte_individual) && $info_estudiante): ?>
                <div class="glass-effect rounded-xl shadow-custom border border-gray-200 mx-6 mb-6 print-container">
                    <!-- Header para impresión - MEJORADO -->
                    <div class="print-header print-only">
                        <h1>Escuela "JOSÉ DOLORES LARREYNAGA"</h1>
                        <h2>REPORTE INDIVIDUAL - <?= htmlspecialchars($info_estudiante->nombre_completo) ?></h2>
                        <p><?= htmlspecialchars($info_estudiante->grado) ?> - <?= htmlspecialchars($info_estudiante->grupo_nombre) ?> - Año Escolar <?= date('Y') ?></p>
                    </div>

                    <div class="p-6 no-print">
                        <div class="flex justify-between items-center mb-6">
                            <div>
                                <h2 class="text-2xl font-bold text-gray-800">Reporte Individual</h2>
                                <p class="text-gray-600 text-lg"><?= htmlspecialchars($info_estudiante->nombre_completo) ?></p>
                                <p class="text-gray-500"><?= htmlspecialchars($info_estudiante->grado) ?> - <?= htmlspecialchars($info_estudiante->grupo_nombre) ?> - Año Escolar <?= date('Y') ?></p>
                            </div>
                            <div class="flex space-x-3">
                                <button onclick="imprimirReporte()" 
                                    class="btn-success font-medium py-2.5 px-6 rounded-lg transition-colors flex items-center hover-lift">
                                    <i class="fas fa-print mr-2"></i>Imprimir
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 pb-6">
                        <table class="min-w-full table-print">
                            <thead class="bg-gray-100 bg-print-header">
                                <tr>
                                    <th class="px-3 py-2 text-center text-sm font-bold text-gray-700 border border-gray-300">#</th>
                                    <th class="px-3 py-2 text-left text-sm font-bold text-gray-700 border border-gray-300">Materia</th>
                                    <th class="px-3 py-2 text-center text-sm font-bold text-gray-700 border border-gray-300">Promedio Final</th>
                                    <th class="px-3 py-2 text-center text-sm font-bold text-gray-700 border border-gray-300">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_general = 0;
                                $total_materias = count($reporte_individual);
                                foreach ($reporte_individual as $index => $materia): 
                                    $total_general += $materia['promedio_final'];
                                ?>
                                    <tr class="<?= $index % 2 == 0 ? 'bg-white' : 'bg-gray-50' ?>">
                                        <td class="px-3 py-2 text-sm text-gray-600 border border-gray-300 text-center"><?= $index + 1 ?></td>
                                        <td class="px-3 py-2 text-sm font-medium text-gray-900 border border-gray-300">
                                            <?= htmlspecialchars($materia['materia']) ?>
                                        </td>
                                        <td class="px-3 py-2 text-sm text-center font-bold border border-gray-300 <?= $materia['promedio_final'] < 6 ? 'estado-reprobado' : 'estado-aprobado' ?>">
                                            <?= $materia['promedio_final'] ?>
                                        </td>
                                        <td class="px-3 py-2 text-sm text-center font-bold border border-gray-300 <?= $materia['estado'] == 'APROBADO' ? 'estado-aprobado' : 'estado-reprobado' ?>">
                                            <?= $materia['estado'] ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-promedio-materia">
                                <tr>
                                    <td class="px-3 py-2 text-sm font-bold text-gray-900 border border-gray-300 text-center" colspan="2">
                                        PROMEDIO GENERAL
                                    </td>
                                    <td class="px-3 py-2 text-sm text-center font-bold border border-gray-300 estado-aprobado">
                                        <?= $total_materias > 0 ? number_format($total_general / $total_materias, 2) : '0.00' ?>
                                    </td>
                                    <td class="px-3 py-2 text-sm text-center font-bold border border-gray-300 <?= ($total_general / $total_materias) >= 6 ? 'estado-aprobado' : 'estado-reprobado' ?>">
                                        <?= ($total_general / $total_materias) >= 6 ? 'APROBADO' : 'REPROBADO' ?>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-200 no-print">
                        <div class="flex justify-between items-center text-sm text-gray-600">
                            <div>
                                <span class="font-medium">Total de materias:</span> <?= count($reporte_individual) ?>
                            </div>
                            <div>
                                <span class="font-medium">Generado el:</span> <?= date('d/m/Y H:i') ?>
                            </div>
                        </div>
                    </div>
                </div>

            <?php endif; ?>

            <?php if ($grupo_id && empty($reporte_grupal) && $tipo_reporte == 'grupal'): ?>
                <div class="glass-effect rounded-xl shadow-custom border border-gray-200 mx-6 mb-6 p-6 text-center">
                    <p class="text-gray-600">No hay datos disponibles para el grupo seleccionado.</p>
                </div>
            <?php endif; ?>

            <?php if ($tipo_reporte == 'individual' && $estudiante_id && empty($reporte_individual)): ?>
                <div class="glass-effect rounded-xl shadow-custom border border-gray-200 mx-6 mb-6 p-6 text-center">
                    <p class="text-gray-600">No hay datos disponibles para el estudiante seleccionado.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Toggle filtro de estudiante
        document.getElementById('tipo_reporte').addEventListener('change', function() {
            const estudianteFilter = document.getElementById('estudiante_filter');
            if (this.value === 'individual') {
                estudianteFilter.classList.remove('hidden');
            } else {
                estudianteFilter.classList.add('hidden');
            }
        });

        // Función para exportar a Excel PROFESIONAL
        function exportToExcel() {
            // Crear tabla HTML para Excel - INCLUYENDO TABLA ESTADÍSTICA
            let tablaHTML = `
                <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
                <head>
                    <meta charset="UTF-8">
                    <title>Reporte Académico</title>
                    <!--[if gte mso 9]>
                    <xml>
                        <x:ExcelWorkbook>
                            <x:ExcelWorksheets>
                                <x:ExcelWorksheet>
                                    <x:Name>Reporte Grupal</x:Name>
                                    <x:WorksheetOptions>
                                        <x:DisplayGridlines/>
                                        <x:Print>
                                            <x:ValidPrinterInfo/>
                                            <x:HorizontalResolution>600</x:HorizontalResolution>
                                            <x:VerticalResolution>600</x:VerticalResolution>
                                        </x:Print>
                                    </x:WorksheetOptions>
                                </x:ExcelWorksheet>
                            </x:ExcelWorksheets>
                        </x:ExcelWorkbook>
                    </xml>
                    <![endif]-->
                    <style>
                        table {
                            border-collapse: collapse;
                            font-family: Arial, sans-serif;
                            font-size: 11px;
                            margin-bottom: 20px;
                        }
                        th {
                            background-color: #2E86C1;
                            color: white;
                            font-weight: bold;
                            padding: 8px;
                            border: 1px solid #1B4F72;
                            text-align: center;
                            vertical-align: middle;
                        }
                        td {
                            padding: 6px;
                            border: 1px solid #BDC3C7;
                            text-align: center;
                            vertical-align: middle;
                        }
                        .estudiante-col {
                            text-align: left;
                            font-weight: bold;
                        }
                        .header-excel {
                            background-color: #1B4F72;
                            color: white;
                            font-size: 16px;
                            font-weight: bold;
                            padding: 12px;
                            text-align: center;
                        }
                        .subheader-excel {
                            background-color: #3498DB;
                            color: white;
                            font-size: 14px;
                            padding: 10px;
                            text-align: center;
                        }
                        .promedio-row {
                            background-color: #D5F5E3;
                            font-weight: bold;
                        }
                        .total-row {
                            background-color: #FCF3CF;
                            font-weight: bold;
                        }
                        .estado-aprobado { color: #27AE60; font-weight: bold; }
                        .estado-reprobado { color: #E74C3C; font-weight: bold; }
                        .section-title {
                            background-color: #7D3C98;
                            color: white;
                            font-size: 13px;
                            font-weight: bold;
                            padding: 10px;
                            text-align: center;
                            margin-top: 25px;
                        }
                    </style>
                </head>
                <body>
                    <!-- TABLA PRINCIPAL -->
                    <table width="100%">
                        <tr>
                            <td colspan="${<?= count($materias_grupo) ?> + 4}" class="header-excel">
                                Escuela "JOSÉ DOLORES LARREYNAGA"
                            </td>
                        </tr>
                        <tr>
                            <td colspan="${<?= count($materias_grupo) ?> + 4}" class="subheader-excel">
                                NÓMINA DE ALUMNOS - <?= htmlspecialchars($info_grupo->grado) ?> - <?= htmlspecialchars($info_grupo->nombre) ?>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="${<?= count($materias_grupo) ?> + 4}" style="padding: 8px; text-align: center; font-style: italic;">
                                Promedios Finales - Año Escolar <?= date('Y') ?> | Generado: <?= date('d/m/Y H:i') ?>
                            </td>
                        </tr>
                        <tr>
                            <th>#</th>
                            <th>Estudiante</th>
            `;

            // Agregar materias
            <?php foreach ($materias_grupo as $materia): ?>
                tablaHTML += `<th><?= htmlspecialchars($materia->nombre) ?></th>`;
            <?php endforeach; ?>

            tablaHTML += `
                           <th>Promedio</th>
                           
                        </tr>
            `;

            // Agregar datos de estudiantes
            <?php foreach ($reporte_grupal as $index => $estudiante): ?>
                tablaHTML += `
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td class="estudiante-col"><?= htmlspecialchars($estudiante['estudiante']) ?></td>
                `;
                
                <?php foreach ($materias_grupo as $materia): ?>
                    tablaHTML += `<td><?= $estudiante['promedios_materias'][$materia->id]['promedio'] ?></td>`;
                <?php endforeach; ?>
                
                tablaHTML += `
                        <td style="font-weight: bold;"><?= $estudiante['promedio_general'] ?></td>
                        
                    </tr>
                `;
            <?php endforeach; ?>

            // Agregar promedios por materia
            tablaHTML += `
                    <tr class="total-row">
                        <td colspan="2" style="text-align: center; font-weight: bold;">TOTAL DE PUNTOS</td>
            `;
            
            <?php foreach ($materias_grupo as $materia): ?>
                tablaHTML += `<td style="font-weight: bold;"><?= number_format($sumatorias_materias[$materia->id], 2) ?></td>`;
            <?php endforeach; ?>
            
            tablaHTML += `
                        <td colspan="2" style="text-align: center;">-</td>
                    </tr>
                    <tr class="promedio-row">
                        <td colspan="2" style="text-align: center; font-weight: bold;">PROMEDIO POR MATERIA</td>
            `;
            
            <?php foreach ($materias_grupo as $materia): ?>
                tablaHTML += `<td style="font-weight: bold;"><?= number_format($promedios_materias[$materia->id], 2) ?></td>`;
            <?php endforeach; ?>
            
            tablaHTML += `
                        <td colspan="2" style="text-align: center;">-</td>
                    </tr>
                </table>

                <!-- TABLA ESTADÍSTICA EN EXCEL -->
                <table width="100%">
                    <tr>
                        <td colspan="6" class="section-title">ESTADÍSTICA GENERAL DEL GRUPO</td>
                    </tr>
                    <tr>
                        <th rowspan="2">Género</th>
                        <th colspan="4">Estadísticas</th>
                    </tr>
                    <tr>
                        <th>Matrícula Inicial</th>
                        <th>Retirados</th>
                        <th>Retenidos</th>
                        <th>Matrícula Final</th>
                    </tr>
                    <tr>
                        <td><strong>Masculino</strong></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td><strong>Femenino</strong></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td><strong>Total</strong></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                </table>
                </body>
                </html>
            `;

            // Crear y descargar archivo
            const blob = new Blob([tablaHTML], {
                type: 'application/vnd.ms-excel'
            });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            
            // Nombre del archivo profesional
            const fileName = `Reporte_<?= htmlspecialchars($info_grupo->grado) ?>_<?= htmlspecialchars($info_grupo->nombre) ?>_<?= date('Y-m-d') ?>.xls`;
            
            link.download = fileName;
            link.href = url;
            link.click();
            
            URL.revokeObjectURL(url);
        }

        // Función para imprimir MEJORADA - oculta URL
        function imprimirReporte() {
            // Ocultar elementos no deseados antes de imprimir
            const originalTitle = document.title;
            document.title = "Reporte Académico - " + new Date().toLocaleDateString();
            
            window.print();
            
            // Restaurar título después de imprimir
            setTimeout(() => {
                document.title = originalTitle;
            }, 1000);
        }

        // Prevenir que los parámetros de URL interfieran
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.pathname);
        }
    </script>
</body>
</html>