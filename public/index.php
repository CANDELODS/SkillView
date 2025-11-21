<?php 

require_once __DIR__ . '/../includes/app.php';

use MVC\Router;
use Controllers\AuthController;
use Controllers\DashboardController;
use Controllers\PrincipalController;

$router = new Router();


// Login
$router->get('/', [AuthController::class, 'login']);
$router->post('/', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);

// Crear Cuenta
$router->get('/registro', [AuthController::class, 'registro']);
$router->post('/registro', [AuthController::class, 'registro']);

//área de administración
$router->get('/admin/dashboard', [DashboardController::class, 'index']);

//Página Principal y 404
$router->get('/principal', [PrincipalController::class, 'index']);
$router->get('/404', [PrincipalController::class, 'notFound']);

$router->comprobarRutas();