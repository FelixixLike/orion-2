<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

/**
 * ORION - Prototipo Funcional
 * 
 * @author    Andrés Felipe Martínez González <felixix-like@outlook.es>
 * @author    Nelson Steven Reina Moreno <nelson.reinamoreno@unimeta.edu.co>
 * @author    Gissel Tatiana Parrado Moreno <parradogiselltatiana@gmail.com>
 * @copyright 2025 Todos los derechos reservados.
 * @license   PRIVADA - PROHIBIDO SU USO SIN AUTORIZACIÓN EXPRESA.
 *            Este software es propiedad intelectual exclusiva de los autores.
 *            Cualquier uso, copia o distribución sin consentimiento continuo y monetizado es un crimen.
 */

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__ . '/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__ . '/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__ . '/../bootstrap/app.php';

$app->handleRequest(Request::capture());
