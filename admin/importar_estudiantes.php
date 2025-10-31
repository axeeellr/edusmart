<?php
// Incluir el archivo de configuración para acceder a las configuraciones globales y proteger la página
require_once __DIR__ . '/../includes/config.php';
protegerPagina([1]); // Solo administradores

// Crear una instancia de la base de datos
$db = new Database();

// Verificar si se envió un archivo mediante el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo'])) {
    $grupo_id = intval($_POST['grupo_id']); // ID del grupo al que se asignarán los estudiantes
    $archivo = $_FILES['archivo']; // Archivo subido por el usuario

    // Verificar la extensión del archivo
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

    if ($extension !== 'xlsx' && $extension !== 'xls') {
        // Si la extensión no es válida, establecer un mensaje de error y redirigir
        $_SESSION['error'] = "Solo se permiten archivos Excel (.xlsx, .xls)";
        header("Location: grupos.php");
        exit;
    }

    try {
        // Incluir la librería PhpSpreadsheet para procesar archivos Excel
        require '../vendor/autoload.php';
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($archivo['tmp_name']);
        $sheet = $spreadsheet->getActiveSheet();

        // Convertir los datos de la hoja activa a un array
        $data = $sheet->toArray();

        // Buscar la fila donde comienza la lista de estudiantes
        $startRow = 0;
        foreach ($data as $index => $row) {
            if (isset($row[0]) && trim($row[0]) === 'No.') {
                $startRow = $index + 1;
                break;
            }
        }

        if ($startRow === 0) {
            // Si no se encuentra el inicio de la lista, lanzar una excepción
            throw new Exception("No se encontró el inicio de la lista de estudiantes");
        }

        // Iniciar una transacción para insertar los datos
        $db->beginTransaction();
        $insertados = 0; // Contador de estudiantes insertados

        //contadores de inserciones
        $insertados = 0;
        $duplicados = 0;

        // Recorrer las filas de datos a partir de la fila de inicio
        for ($i = $startRow; $i < count($data); $i++) {
            $row = $data[$i];

            // Verificar que las columnas necesarias no estén vacías
            if (empty($row[1]) || empty($row[2]) || empty($row[3])) {
                continue;
            }
            $nie = trim($row[1]);
            $nombre = trim($row[2]);
            $birth = trim($row[3]);

            //cambiamso el formato de fecha dia-mes-año a año-mes-dia
            $dateObj = DateTime::createFromFormat('d/m/Y', $birth);

            // Validar formato de fecha
            $dateObj = DateTime::createFromFormat('d/m/Y', $birth);
            if (!$dateObj) {
                // Si la fecha no tiene el formato esperado, la ignoramos o lanzamos excepción
                throw new Exception("Formato de fecha inválido en fila " . ($i + 1));
            }

            $birth = $dateObj->format('Y-m-d');

            // Verificar si el estudiante ya existe
            $db->query("SELECT COUNT(*) as total FROM estudiantes WHERE id = :nie");
            $db->bind(':nie', $nie);
            $db->execute();
            $existe = $db->single()->total;

            if ($existe == 0) {

                // Insertar solo si no existe
                $db->query("INSERT INTO estudiantes (id, nombre_completo, fecha_nacimiento,grupo_id) 
                VALUES (:nie, :nombre,:fecha_nacimiento, :grupo_id)");
                $db->bind(':nie', $nie);
                $db->bind(':nombre', $nombre);
                $db->bind(':fecha_nacimiento', $birth);
                $db->bind(':grupo_id', $grupo_id);

                if ($db->execute()) {
                    $insertados++;
                }
            } else {
                // Puedes contar los duplicados si quieres
                $duplicados++;
            }

        }

        // Confirmar la transacción
        $db->commit();
        $_SESSION['success'] = "Se importaron $insertados estudiantes correctamente, $duplicados registros ya existian";

        //validacion de errores
    } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
        $_SESSION['error'] = "El archivo no es un Excel válido o está dañado.";
    } catch (Exception $e) {
        if ($db->inTransaction())
            $db->rollBack();
        $_SESSION['error'] = "Error al importar: " . $e->getMessage();
    }

    // Redirigir a la página de grupos
    header("Location: grupos.php");
    exit;
}