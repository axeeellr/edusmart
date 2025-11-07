<?php
// Configuración básica
define('DB_HOST', 'nozomi.proxy.rlwy.net'); // Host de la base de datos
define('DB_USER', 'root'); // Usuario de la base de datos
define('DB_PORT', 15239); 
define('DB_PASS', 'mmuRZpkKwmDPhNEoLoWmSNvlulhjSSIH'); // Contraseña de la base de datos
define('DB_NAME', 'smartedu'); // Nombre de la base de datos
define('BASE_PATH', __DIR__ . '/'); // Ruta base del proyecto

// Configuración de la aplicación
define('APP_NAME', 'SmartEdu'); // Nombre de la aplicación
define('APP_URL', 'https://edusmart.onrender.com/'); // URL base de la aplicación
define('ALERTIFY', '../alertify/alertify.min.js'); // url alertify waza
define('ALERTIFY_CSS', '../alertify/css/alertify.min.css'); // url alertify css waza

// Iniciar sesión
ob_start();
session_start(); // Inicia la sesión para manejar variables de sesión

// Incluir funciones
require_once 'functions.php'; // Archivo que contiene funciones auxiliares

// Conectar a la base de datos
require_once 'db.php'; // Archivo que maneja la conexión a la base de datos
$db = new Database(); // Crear una instancia de la clase Database

// Opciones para la conexión PDO
$options = array(
    PDO::ATTR_PERSISTENT => true, // Conexión persistente para mejorar el rendimiento
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Modo de errores para lanzar excepciones
    PDO::ATTR_EMULATE_PREPARES   => false
);