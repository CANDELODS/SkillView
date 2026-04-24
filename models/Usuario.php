<?php

namespace Model;

class Usuario extends ActiveRecord
{
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

    // Constantes de validación centralizadas.
    // Se usan para evitar números "quemados" dentro de los métodos y facilitar cambios futuros.
    private const EDAD_MINIMA = 15;
    private const EDAD_MAXIMA = 35;
    private const PASSWORD_MIN = 6;
    private const PASSWORD_MAX = 16;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->nombres = $args['nombres'] ?? '';
        $this->apellidos = $args['apellidos'] ?? '';
        $this->edad = $args['edad'] ?? 0;
        // IMPORTANTE:
        // El sexo inicia como cadena vacía para que, en el formulario de registro,
        // no se seleccione automáticamente Masculino.
        // Así, si el usuario no elige una opción, se mantiene "Selecciona"
        // y la validación puede detectar correctamente que falta este dato.
        $this->sexo = $args['sexo'] ?? '';
        $this->correo = $args['correo'] ?? '';
        $this->password = $args['password'] ?? '';
        $this->password2 = $args['password2'] ?? '';
        $this->universidad = $args['universidad'] ?? '';
        $this->carrera = $args['carrera'] ?? '';
        $this->admin = $args['admin'] ?? 0;
    }

    // Validar el Login de Usuarios
    public function validarLogin()
    {
        if (!$this->correo) {
            self::$alertas['error'][] = 'El correo del Usuario es Obligatorio';
        }
        if (!filter_var($this->correo, FILTER_VALIDATE_EMAIL)) {
            self::$alertas['error'][] = 'correo no válido';
        }
        if (!$this->password) {
            self::$alertas['error'][] = 'La contraseña no puede ir vacia';
        }
        return self::$alertas;
    }

    // Validación para cuentas nuevas
    public function validar_cuenta()
    {
        // Reiniciamos las alertas antes de validar.
        // Esto evita que errores de validaciones anteriores se mezclen con la validación actual.
        self::$alertas = [];
        // Normalizamos los textos eliminando espacios al inicio y al final.
        // Esto evita que un usuario envíe campos aparentemente llenos usando solo espacios.
        $this->nombres     = trim($this->nombres ?? '');
        $this->apellidos   = trim($this->apellidos ?? '');
        $this->universidad = trim($this->universidad ?? '');
        $this->carrera     = trim($this->carrera ?? '');
        $this->correo      = trim($this->correo ?? '');

        // Validamos campos de texto que no deben contener números ni caracteres especiales.
        // Además, se controla la longitud máxima según el tamaño permitido por la BD.
        $this->validarTextoSinNumeros($this->nombres, 'El nombre', 25);
        $this->validarTextoSinNumeros($this->apellidos, 'El apellido', 25);
        // Valida que la edad esté dentro del rango definido para la población objetivo.
        // En SkillView se limita entre 15 y 35 años.
        $this->validarEdad();
        // Valida que el sexo corresponda a una opción permitida:
        // 0 = Masculino
        // 1 = Femenino
        // 3 = Prefiero no decirlo
        $this->validarSexo();
        $this->validarTextoSinNumeros($this->universidad, 'La universidad', 45);
        $this->validarTextoSinNumeros($this->carrera, 'La carrera', 45);

        if (!$this->correo) {
            self::setAlerta('error', 'El correo es obligatorio');
        }

        if (!filter_var($this->correo, FILTER_VALIDATE_EMAIL)) {
            self::setAlerta('error', 'Correo no válido');
        }
        // Valida la fortaleza mínima de la contraseña.
        // La contraseña debe tener entre 6 y 16 caracteres,
        // al menos una mayúscula, un número y un carácter especial.
        $this->validarFortalezaPassword($this->password);

        if ($this->password !== $this->password2) {
            self::setAlerta('error', 'Las contraseñas no coinciden');
        }

        return self::$alertas;
    }

    public function validar_edicion()
    {
        self::$alertas = [];

        $this->nombres     = trim($this->nombres ?? '');
        $this->apellidos   = trim($this->apellidos ?? '');
        $this->universidad = trim($this->universidad ?? '');
        $this->carrera     = trim($this->carrera ?? '');
        $this->correo      = trim($this->correo ?? '');

        $this->validarTextoSinNumeros($this->nombres, 'El nombre', 25);
        $this->validarTextoSinNumeros($this->apellidos, 'El apellido', 25);
        $this->validarEdad();
        $this->validarSexo();
        $this->validarTextoSinNumeros($this->universidad, 'La universidad', 45);
        $this->validarTextoSinNumeros($this->carrera, 'La carrera', 45);

        if (!$this->correo) {
            self::setAlerta('error', 'El correo es obligatorio');
        }

        if (!filter_var($this->correo, FILTER_VALIDATE_EMAIL)) {
            self::setAlerta('error', 'Correo no válido');
        }

        // En edición de usuario, la contraseña es opcional.
        // Solo se valida si el administrador escribió algo en password o password2.
        // Si ambos campos quedan vacíos, se conserva la contraseña actual.
        if ($this->password || $this->password2) {
            $this->validarFortalezaPassword($this->password);

            if ($this->password !== $this->password2) {
                self::setAlerta('error', 'Las contraseñas no coinciden');
            }
        }

        return self::$alertas;
    }

    // Valida un correo
    public function validarcorreo()
    {
        if (!$this->correo) {
            self::$alertas['error'][] = 'El correo es Obligatorio';
        }
        if (!filter_var($this->correo, FILTER_VALIDATE_EMAIL)) {
            self::$alertas['error'][] = 'correo no válido';
        }
        return self::$alertas;
    }

    // Valida el Password 
    public function validarPassword()
    {
        if (!$this->password) {
            self::$alertas['error'][] = 'La contraseña no puede ir vacia';
        }
        if (strlen($this->password) < 6) {
            self::$alertas['error'][] = 'La contraseña debe contener al menos 6 caracteres';
        }
        return self::$alertas;
    }

    public function nuevo_password(): array
    {
        if (!$this->password_actual) {
            self::$alertas['error'][] = 'La contraseña Actual no puede ir vacio';
        }
        if (!$this->password_nuevo) {
            self::$alertas['error'][] = 'La contraseña Nuevo no puede ir vacia';
        }
        if (strlen($this->password_nuevo) < 6) {
            self::$alertas['error'][] = 'La contraseña debe contener al menos 6 caracteres';
        }
        return self::$alertas;
    }

    // Comprobar el password
    public function comprobar_password(): bool
    {
        return password_verify($this->password_actual, $this->password);
    }

    // Hashea el password
    public function hashPassword(): void
    {
        $this->password = password_hash($this->password, PASSWORD_BCRYPT);
    }

    // Busca y devuelve los usuarios que coincidan con el término de búsqueda
    public static function buscarUsuarios($termino)
    { //$termino es la cadena a buscar
        // Utilizamos el método buscar de la clase ActiveRecord, enviandole la cadena a buscar y los campos donde buscar
        return static::buscar($termino, ['nombres', 'apellidos', 'correo']);
    }

    // Total de usuarios que coinciden con la búsqueda
    public static function totalBusquedaUsuarios($termino)
    {
        return static::totalBusqueda($termino, ['nombres', 'apellidos', 'correo']);
    }

    // Usuarios paginados que coinciden con la búsqueda
    public static function paginarBusquedaUsuarios($termino, $porPagina, $offset, $ordenar = 'nombres')
    {
        return static::paginarBusqueda($termino, ['nombres', 'apellidos', 'correo'], $ordenar, $porPagina, $offset);
    }

    // Esta validación se usa en el perfil del usuario.
    // No valida edad, sexo, correo ni contraseña, porque desde perfil
    // el usuario solo puede actualizar nombres, apellidos, universidad y carrera.
    public function validar_edicion_perfil(): array
    {
        self::$alertas = [];

        $this->nombres     = trim($this->nombres ?? '');
        $this->apellidos   = trim($this->apellidos ?? '');
        $this->universidad = trim($this->universidad ?? '');
        $this->carrera     = trim($this->carrera ?? '');

        $this->validarTextoSinNumeros($this->nombres, 'El nombre', 25);
        $this->validarTextoSinNumeros($this->apellidos, 'Los apellidos', 25);
        $this->validarTextoSinNumeros($this->universidad, 'La universidad', 45);
        $this->validarTextoSinNumeros($this->carrera, 'La carrera', 45);

        return self::$alertas;
    }

    // Helpers de mejora para las validaciones de registro, edición de usuario y perfil.

    // Verifica que un texto contenga únicamente letras y espacios.
    // \p{L} permite letras con tildes y ñ.
    // La bandera "u" permite trabajar correctamente con caracteres UTF-8.
    private function textoValido(string $valor): bool
    {
        return preg_match('/^[\p{L}\s]+$/u', $valor) === 1;
    }

    // Helper reutilizable para validar campos de texto como nombres,
    // apellidos, universidad y carrera.
    // Valida tres cosas:
    // 1. Que el campo no esté vacío.
    // 2. Que solo tenga letras y espacios.
    // 3. Que no supere la longitud máxima permitida.
    private function validarTextoSinNumeros(string $campo, string $nombreCampo, int $maxCaracteres): void
    {
        if ($campo === '') {
            self::setAlerta('error', "{$nombreCampo} es obligatorio y no puede estar vacío");
            return;
        }

        if (!$this->textoValido($campo)) {
            self::setAlerta('error', "{$nombreCampo} solo puede contener letras y espacios");
        }

        if (mb_strlen($campo, 'UTF-8') > $maxCaracteres) {
            self::setAlerta('error', "{$nombreCampo} no puede superar {$maxCaracteres} caracteres");
        }
    }

    // Convierte la edad a entero válido.
    // Si no es un número entero o está fuera del rango permitido,
    // se agrega una alerta de error.
    private function validarEdad(): void
    {
        $edad = filter_var($this->edad, FILTER_VALIDATE_INT);

        if ($edad === false || $edad < self::EDAD_MINIMA || $edad > self::EDAD_MAXIMA) {
            self::setAlerta('error', 'La edad debe estar entre 15 y 35 años');
        }
    }

    // Verifica que el valor de sexo enviado desde el formulario
    // exista dentro de las opciones permitidas por el sistema.
    private function validarSexo(): void
    {
        if (!in_array((string)$this->sexo, ['0', '1', '3'], true)) {
            self::setAlerta('error', 'El sexo es obligatorio');
        }
    }

    // Valida las reglas de seguridad de la contraseña:
    // - No puede estar vacía.
    // - Debe tener entre 6 y 16 caracteres.
    // - Debe incluir al menos una mayúscula.
    // - Debe incluir al menos un número.
    // - Debe incluir al menos un carácter especial.
    private function validarFortalezaPassword(string $password): void
    {
        if ($password === '') {
            self::setAlerta('error', 'La contraseña no puede ir vacía');
            return;
        }

        if (strlen($password) < self::PASSWORD_MIN || strlen($password) > self::PASSWORD_MAX) {
            self::setAlerta('error', 'La contraseña debe tener entre 6 y 16 caracteres');
        }

        if (!preg_match('/[A-Z]/', $password)) {
            self::setAlerta('error', 'La contraseña debe contener al menos una letra mayúscula');
        }

        if (!preg_match('/[0-9]/', $password)) {
            self::setAlerta('error', 'La contraseña debe contener al menos un número');
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            self::setAlerta('error', 'La contraseña debe contener al menos un carácter especial');
        }
    }
}
