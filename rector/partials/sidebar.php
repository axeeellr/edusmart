<?php
// Obtener el nombre del archivo actual sin la extensión
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<script src="https://kit.fontawesome.com/7bcd40cb83.js" crossorigin="anonymous"></script>
<div class="bg-blue-800 text-white w-64 min-h-screen p-4">
    <h1 class="text-2xl font-bold mb-6"><?php echo APP_NAME; ?></h1>
    <p class="text-blue-200 mb-6">Bienvenido, <?php echo $_SESSION['nombre']; ?></p>

    <nav>
        <ul class="space-y-2">
            <li>
                <a href="dashboard.php"
                    class="block px-4 py-2 rounded-lg <?php echo ($current_page == 'dashboard') ? 'bg-blue-700' : 'hover:bg-blue-700'; ?>">
                    <i class="fa-solid fa-bars"></i>
                    Dashboard
                </a>
            </li>
            <li>
                <a href="maestros.php"
                    class="block px-4 py-2 rounded-lg <?php echo ($current_page == 'maestros') ? 'bg-blue-700' : 'hover:bg-blue-700'; ?>">
                    <i class="fa-solid fa-person-chalkboard"></i>
                    Maestros
                </a>
            </li>
            <li>
                <a href="grupos.php"
                    class="block px-4 py-2 rounded-lg <?php echo ($current_page == 'grupos') ? 'bg-blue-700' : 'hover:bg-blue-700'; ?>">
                    <i class="fa-solid fa-users-rays"></i>
                    Grupos
                </a>
            </li>
            <li>
                <a href="reportes.php"
                    class="block px-4 py-2 rounded-lg <?php echo ($current_page == 'reportes') ? 'bg-blue-700' : 'hover:bg-blue-700'; ?>">
                    <i class="fa-solid fa-file-circle-plus"></i>
                    Reportes
                </a>
            </li>
            <li>
                <a href="solicitudes.php"
                    class="block px-4 py-2 rounded-lg <?php echo ($current_page == 'solicitudes') ? 'bg-blue-700' : 'hover:bg-blue-700'; ?>">
                    <i class="fa-solid fa-bell"></i>
                    Solicitudes
                </a>
            </li>
            <li>
                <a href="<?php echo APP_URL; ?>/logout.php" class="block px-4 py-2 rounded-lg hover:bg-red-700">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    Cerrar Sesión
                </a>
            </li>
        </ul>
    </nav>
</div>