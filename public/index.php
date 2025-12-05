<?php 

require_once __DIR__ . '/../includes/app.php';

use Controllers\AprendizajeController;
use MVC\Router;
use Controllers\AuthController;
use Controllers\DashboardController;
use Controllers\PrincipalController;
use Controllers\RetosController;

$router = new Router();


// Login
$router->get('/', [AuthController::class, 'login']);
$router->post('/', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);

// Crear Cuenta
$router->get('/registro', [AuthController::class, 'registro']);
$router->post('/registro', [AuthController::class, 'registro']);

//--------------------ÁREA DE ADMINISTRACIÓN--------------------
$router->get('/admin/dashboard', [DashboardController::class, 'index']);
//ADMINISTRAR USUARIOS
$router->get('/admin/usuarios', [DashboardController::class, 'indexUsuarios']);
$router->get('/admin/usuarios/editar', [DashboardController::class, 'editarUsuarios']);
$router->post('/admin/usuarios/editar', [DashboardController::class, 'editarUsuarios']);
$router->post('/admin/usuarios/eliminar', [DashboardController::class, 'eliminarUsuarios']);
//ADMINISTRAR HABILIDADES
$router->get('/admin/habilidades', [DashboardController::class, 'indexHabilidades']);
$router->get('/admin/habilidades/crear', [DashboardController::class, 'crearHabilidades']);
$router->post('/admin/habilidades/crear', [DashboardController::class, 'crearHabilidades']);
$router->get('/admin/habilidades/editar', [DashboardController::class, 'editarHabilidades']);
$router->post('/admin/habilidades/editar', [DashboardController::class, 'editarHabilidades']);
$router->post('/admin/habilidades/eliminar', [DashboardController::class, 'eliminarHabilidades']);
//--------------------FIN ÁREA DE ADMINISTRACIÓN--------------------

//Página Principal y 404
$router->get('/principal', [PrincipalController::class, 'index']);
$router->get('/404', [PrincipalController::class, 'notFound']);

//--------------------APRENDIZAJE--------------------
$router->get('/aprendizaje', [AprendizajeController::class, 'index']);
$router->post('/aprendizaje', [AprendizajeController::class, 'index']);
//--------------------FIN APRENDIZAJE--------------------

//--------------------RETOS--------------------
$router->get('/retos', [RetosController::class, 'index']);
$router->post('/retos', [RetosController::class, 'index']);
//--------------------FIN RETOS--------------------
$router->comprobarRutas();