<?php

namespace Controllers;

use MVC\Router;
use Model\Retos;
use Model\HabilidadesBlandas;

class RetosController {

    public static function index (Router $router){
    // Verificamos si el usuario está autenticado
        if (!isAuth()) {
            header('Location: /');
            exit;
        }

        $login = false;
        $datosUsuario = obtenerDatosUsuarioHeader($_SESSION['id']);
        // Id del usuario logueado
        $idUsuario = $_SESSION['id'] ?? null;

        // 1) Traer los retos habilitados
        $retos = Retos::habilitadas(); 

        
    // Leer filtros desde GET (cuando el user cambie selects)
    //¿Existe la clave habilidad dentro de $_GET? y “Existe habilidad y además no está vacío”
    //Si el usuario selecciona "Todas las habilidades" entonces isset($_GET['habilidad']) es true (porque existe), pero pero $_GET['habilidad'] !== '' es false (porque está vacío)
    $idHabilidad = isset($_GET['habilidad']) && $_GET['habilidad'] !== '' ? (int)$_GET['habilidad'] : null;
    $dificultad  = isset($_GET['dificultad']) && $_GET['dificultad'] !== '' ? (int)$_GET['dificultad'] : null;

    //Habilidades para el select (solo las que tienen retos habilitados)
    $habilidadesFiltro = HabilidadesBlandas::conRetosHabilitados();

    //Retos filtrados (combinable)
    $retos = Retos::filtrar($idHabilidad, $dificultad);

        if(!empty($retos)){
            foreach($retos as $reto){
                //Creamos la llave tags y la llenamos con el array con los tag separados
                //Explode nos permite convertir un string en array separándolo por un carácter específico, en este caso (,)
                //Array_map recorre un array y aplica una función a cada elemento, devolviendo un array nuevo, en este caso
                //se usó trim para limpiar espacios al inicio y al final, es una forma de evitar confictos.
                //Array_filter elimina elementos vacíos o falsos del array, ya que si en la BD hay "comunicación,confianza,"
                //Esto produciría esto: ["comunicación", "confianza", ""], por lo cual se crearía un <p> vacío en la vista.
                $reto->tags = array_filter(array_map('trim', explode(',', (string)$reto->tag)));
                //Cambiamos los valores de la llave dificultad dependiendo de su valor para poder mostrarlo en la vista
                if($reto->dificultad === '1'){
                    $reto->dificultad = 'Básico';
                }
                elseif($reto->dificultad === '2'){
                    $reto->dificultad = 'Intermedio';
                }
                elseif($reto->dificultad === '3'){
                    $reto->dificultad = 'Avanzado';
                }
            }
        }
        // Render a la vista 
        $router->render('paginas/retos/retos', [
            'titulo' => 'Pon a prueba tus habilidades',
            'login' => $login,
            'retos' => $retos,
            //Filtros
            'habilidadesFiltro' => $habilidadesFiltro,
            'filtroHabilidad'   => $idHabilidad,
            'filtroDificultad'  => $dificultad,
            //Header
            'nombreUsuario'    => $datosUsuario['nombreUsuario'],
            'inicialesUsuario' => $datosUsuario['inicialesUsuario']
        ]);
    }
}