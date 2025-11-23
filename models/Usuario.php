<?php

namespace Model;

class Usuario extends ActiveRecord {
    protected static $tabla = 'usuarios';
    protected static $columnasDB = ['id', 'nombres', 'apellidos', 'edad', 'sexo', 'correo', 'password', 'universidad', 'carrera', 'admin'];

    public $id;
    public $nombres;
    public $apellidos;
    public $edad;
    public $sexo;
    public $correo;
    public $password;
    public $password2;
    public $universidad;
    public $carrera;
    public $admin;

    public $password_actual;
    public $password_nuevo;

    
    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->nombres = $args['nombres'] ?? '';
        $this->apellidos = $args['apellidos'] ?? '';
        $this->edad = $args['edad'] ?? 0;
        $this->sexo = $args['sexo'] ?? 0;
        $this->correo = $args['correo'] ?? '';
        $this->password = $args['password'] ?? '';
        $this->password2 = $args['password2'] ?? '';
        $this->universidad = $args['universidad'] ?? '';
        $this->carrera = $args['carrera'] ?? '';
        $this->admin = $args['admin'] ?? 0;
    }

    // Validar el Login de Usuarios
    public function validarLogin() {
        if(!$this->correo) {
            self::$alertas['error'][] = 'El correo del Usuario es Obligatorio';
        }
        if(!filter_var($this->correo, FILTER_VALIDATE_EMAIL)) {
            self::$alertas['error'][] = 'correo no válido';
        }
        if(!$this->password) {
            self::$alertas['error'][] = 'La contraseña no puede ir vacia';
        }
        return self::$alertas;

    }

    // Validación para cuentas nuevas
    public function validar_cuenta() {
        if(!$this->nombres) {
            self::$alertas['error'][] = 'El nombre es Obligatorio';
        }
        if(!$this->apellidos) {
            self::$alertas['error'][] = 'El apellido es Obligatorio';
        }
        if($this->edad < 0 || $this->edad > 30 ) {
            self::$alertas['error'][] = 'La edad debe ser mayor a 0 y menor o igual a 30';
        }
        if($this->sexo !== '0' && $this->sexo !== '1') {
            self::$alertas['error'][] = 'El sexo es Obligatorio';
        }
        if(!$this->universidad) {
            self::$alertas['error'][] = 'La universidad es Obligatoria';
        }
        if(!$this->carrera) {
            self::$alertas['error'][] = 'La carrera es Obligatoria';
        }
        if(!$this->correo) {
            self::$alertas['error'][] = 'El correo es Obligatorio';
        }
        if(!$this->password) {
            self::$alertas['error'][] = 'La contraseña no puede ir vacio';
        }
        if(strlen($this->password) < 6) {
            self::$alertas['error'][] = 'La contraseña debe contener al menos 6 caracteres';
        }
        if($this->password !== $this->password2) {
            self::$alertas['error'][] = 'Las contraseñas no coinciden';
        }
        return self::$alertas;
    }

    // Valida un correo
    public function validarcorreo() {
        if(!$this->correo) {
            self::$alertas['error'][] = 'El correo es Obligatorio';
        }
        if(!filter_var($this->correo, FILTER_VALIDATE_EMAIL)) {
            self::$alertas['error'][] = 'correo no válido';
        }
        return self::$alertas;
    }

    // Valida el Password 
    public function validarPassword() {
        if(!$this->password) {
            self::$alertas['error'][] = 'La contraseña no puede ir vacia';
        }
        if(strlen($this->password) < 6) {
            self::$alertas['error'][] = 'La contraseña debe contener al menos 6 caracteres';
        }
        return self::$alertas;
    }

    public function nuevo_password() : array {
        if(!$this->password_actual) {
            self::$alertas['error'][] = 'La contraseña Actual no puede ir vacio';
        }
        if(!$this->password_nuevo) {
            self::$alertas['error'][] = 'La contraseña Nuevo no puede ir vacia';
        }
        if(strlen($this->password_nuevo) < 6) {
            self::$alertas['error'][] = 'La contraseña debe contener al menos 6 caracteres';
        }
        return self::$alertas;
    }

    // Comprobar el password
    public function comprobar_password() : bool {
        return password_verify($this->password_actual, $this->password );
    }

    // Hashea el password
    public function hashPassword() : void {
        $this->password = password_hash($this->password, PASSWORD_BCRYPT);
    }

    // Busca y devuelve los usuarios que coincidan con el término de búsqueda
    public static function buscarUsuarios($termino) { //$termino es la cadena a buscar
    // Utilizamos el método buscar de la clase ActiveRecord, enviandole la cadena a buscar y los campos donde buscar
    return static::buscar($termino, ['nombres', 'apellidos', 'correo']);
    }

    // Total de usuarios que coinciden con la búsqueda
    public static function totalBusquedaUsuarios($termino) {
    return static::totalBusqueda($termino, ['nombres', 'apellidos', 'correo']);
    }

    // Usuarios paginados que coinciden con la búsqueda
    public static function paginarBusquedaUsuarios($termino, $porPagina, $offset, $ordenar = 'nombres') {
    return static::paginarBusqueda($termino, ['nombres', 'apellidos', 'correo'], $ordenar, $porPagina, $offset);
    }


}