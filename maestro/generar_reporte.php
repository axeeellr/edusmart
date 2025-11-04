<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'functions.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Asegúrate de tener instalado composer y TCPDF

use TCPDF as PDF;

protegerPagina([3]); // Solo maestros

$db = new Database();
$maestro_id = $_SESSION['user_id'];
$materia_id = intval($_POST['materia_id']);
$grupo_id = intval($_POST['grupo_id']);
$trimestre = intval($_POST['trimestre']);

// Verificar permisos
$db->query("SELECT mm.id FROM maestros_materias mm 
           JOIN grupos g ON g.maestro_id = mm.maestro_id
           WHERE mm.materia_id = :materia_id 
           AND mm.maestro_id = :maestro_id
           AND g.id = :grupo_id");
$db->bind(':materia_id', $materia_id);
$db->bind(':maestro_id', $maestro_id);
$db->bind(':grupo_id', $grupo_id);
$permiso = $db->single();

if (!$permiso) {
    die('No tienes permiso para generar este reporte');
}

// Obtener información del grupo y materia
$db->query("SELECT g.nombre as grupo_nombre, g.grado, m.nombre as materia_nombre 
           FROM grupos g, materias m 
           WHERE g.id = :grupo_id AND m.id = :materia_id");
$db->bind(':grupo_id', $grupo_id);
$db->bind(':materia_id', $materia_id);
$info = $db->single();

// Obtener estudiantes del grupo
$db->query("SELECT id, nombre_completo FROM estudiantes 
           WHERE grupo_id = :grupo_id 
           ORDER BY nombre_completo");
$db->bind(':grupo_id', $grupo_id);
$estudiantes = $db->resultSet();

// Crear PDF
$pdf = new PDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('SmartEdu');
$pdf->SetAuthor('SmartEdu');
$pdf->SetTitle('Reporte de Calificaciones');
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 15);

// Primera página
$pdf->AddPage();
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'Reporte de Calificaciones', 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 10, 'Materia: ' . $info->materia_nombre, 0, 1);
$pdf->Cell(0, 10, 'Grupo: ' . $info->grado . ' - ' . $info->grupo_nombre, 0, 1);
$pdf->Cell(0, 10, 'Trimestre: ' . ($trimestre == 0 ? 'Promedio Final' : "Trimestre $trimestre"), 0, 1);
$pdf->Ln(10);

// Cabecera de la tabla
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetFillColor(220, 220, 220);
$pdf->Cell(100, 7, 'Estudiante', 1, 0, 'L', 1);
$pdf->Cell(30, 7, 'Calificación', 1, 0, 'C', 1);
$pdf->Cell(30, 7, 'Estado', 1, 1, 'C', 1);

// Datos de la tabla
$pdf->SetFont('helvetica', '', 10);
foreach ($estudiantes as $estudiante) {
    $calificacion = 0;

    if ($trimestre == 0) {
        // Promedio final (promedio de los 3 trimestres)
        $db->query("
        SELECT AVG(trimestre_promedio) AS promedio
        FROM ( SELECT SUM(n.calificacion * a.porcentaje / 100) / SUM(a.porcentaje / 100) AS trimestre_promedio FROM actividades a
        JOIN notas n ON n.actividad_id = a.id
        WHERE n.estudiante_id = :estudiante_id
        AND a.materia_id = :materia_id
        GROUP BY a.trimestre ) t");

        $db->bind(':estudiante_id', $estudiante->id);
        $db->bind(':materia_id', $materia_id);
    } else {
        // Calificación por trimestre
        $db->query("SELECT SUM(n.calificacion * a.porcentaje / 100) / SUM(a.porcentaje / 100) AS promedio FROM notas n JOIN actividades a ON a.id = n.actividad_id WHERE n.estudiante_id = :estudiante_id AND a.materia_id = :materia_id AND a.trimestre = :trimestre");
        $db->bind(':estudiante_id', $estudiante->id);
        $db->bind(':materia_id', $materia_id);
        $db->bind(':trimestre', $trimestre);
    }

    $result = $db->single();
    $calificacion = round($result->promedio ?? 0, 2);
    $estado = $calificacion >= 6 ? 'Aprobado' : 'Reprobado';

    $pdf->Cell(100, 7, $estudiante->nombre_completo, 1, 0, 'L');
    $pdf->Cell(30, 7, $calificacion, 1, 0, 'C');
    $pdf->Cell(30, 7, $estado, 1, 1, 'C');
}

// Pie de página
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 10, 'Generado el ' . date('d/m/Y H:i'), 0, 0, 'R');

// Salida del PDF
$pdf->Output('reporte_calificaciones.pdf', dest: 'I');