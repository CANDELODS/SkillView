<?php

namespace Model;

class Usuario extends ActiveRecord
{
    protected static $tabla = 'usuarios';
    protected static $columnasDB = ['id', 'nombres', 'apellidos', 'edad', 'sexo', 'correo', 'password', 'universidad', 'carrera', 'admin', 'debe_cambiar_password', 'habilitado', 'token_recuperacion', 'token_expiracion', 'autoriza_tratamiento_datos'];

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
    public $debe_cambiar_password;
    public $habilitado;
    public $token_recuperacion;
    public $token_expiracion;
    public $autoriza_tratamiento_datos;

    public $password_actual;
    public $password_nuevo;

    // Constantes de validación centralizadas.
    // Se usan para evitar números "quemados" dentro de los métodos y facilitar cambios futuros.
    private const EDAD_MINIMA = 18;
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
        $this->debe_cambiar_password = $args['debe_cambiar_password'] ?? 0;
        $this->habilitado = $args['habilitado'] ?? null;
        $this->token_recuperacion = $args['token_recuperacion'] ?? '';
        $this->token_expiracion = $args['token_expiracion'] ?? 0;
        $this->autoriza_tratamiento_datos = $args['autoriza_tratamiento_datos'] ?? 0;
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
        // En SkillView se limita entre 18 y 35 años.
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

        if ((string) $this->autoriza_tratamiento_datos !== '1') {
            self::setAlerta(
                'error',
                'Debes autorizar el tratamiento de tus datos personales para crear una cuenta'
            );
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

        if (
            !in_array(
                (string) $this->habilitado,
                ['0', '1'],
                true
            )
        ) {
            self::$alertas['error'][] =
                'El estado seleccionado no es válido';
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
    public function validarCorreo(): array
    {
        self::$alertas = [];

        $this->correo = strtolower(
            trim($this->correo ?? '')
        );

        if ($this->correo === '') {
            self::setAlerta(
                'error',
                'El correo es obligatorio'
            );

            return self::$alertas;
        }

        if (!filter_var($this->correo, FILTER_VALIDATE_EMAIL)) {
            self::setAlerta(
                'error',
                'Correo no válido'
            );
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
            self::$alertas['error'][] = 'La contraseña Nueva no puede ir vacia';
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
            self::setAlerta('error', 'La edad debe estar entre 18 y 35 años');
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

    public function validarCambioPassword(): array
    {
        // Limpiamos alertas de validaciones anteriores.
        self::$alertas = [];

        // Aplicamos las reglas de fortaleza a la nueva contraseña.
        $this->validarFortalezaPassword($this->password);

        // Validamos la confirmación de la contraseña.
        if ($this->password2 === '') {
            self::setAlerta(
                'error',
                'Debes confirmar la nueva contraseña'
            );
        } elseif ($this->password !== $this->password2) {
            self::setAlerta(
                'error',
                'Las contraseñas no coinciden'
            );
        }

        return self::$alertas;
    }

    /**
     * Genera el token que se enviará por correo.
     *
     * El token original se devuelve al controlador,
     * pero en la base de datos se almacena solamente
     * su hash SHA-256.
     */
    public function crearTokenRecuperacion(int $duracionMinutos = 30): string
    {
        //Random_bytes 32 produce una cadena hexadecimal de 64 caracteres (Cada byte está entre 0 y 255).
        //bin2hex convierte los datos binarios de random_bytes en una cadena hexadecimal fácil de transportar.
        //Ejemplo: 0 1 2 3 4 5 6 7 8 9 a b c d e f Entonces... 32 bytes * 2 = 64 caracteres = 66f7e754934c02e3b5f4733b68c8451bc00df6202e743017c1c67695f5eb2c15
        //Ese $tokenPlano se envía en el enlace
        $tokenPlano = bin2hex(random_bytes(32));

        // Guardamos solo el hash en la base de datos.
        //hash() calcula un resumen del contenido utilizando el algoritmo SHA-256, el resultado también tiene 64 caracteres hexadecimales
        $this->token_recuperacion = hash('sha256', $tokenPlano);
        //Ejemplo: Token enviado abc123..., Hash almacenado: 9f86d081884c7d659a2feaa0c55ad015...
        //Cuando llega el enlace, el sistema vuelve a aplicar: $tokenHash = hash('sha256', $token); y busca el resultado en la BD
        // hash('sha256', 'mismo valor') Siempre produce el mismo resultado

        // Momento exacto en el que vencerá el token.
        //time() devuelve el momento actual expresado en segundos, ejemplo: 1784635200
        //Em étodo recibe por ejemplo $duracionMinutos = 30, cada minuto tiene 60 segundos: 30 * 60 = 1800 segundos
        //Entonces: 1784635200 + 1800 = 1784637000, Esto es igual a 30 minutos después
        $this->token_expiracion = time() + ($duracionMinutos * 60);

        // Este es el token que viajará en el enlace del correo, no se devuelve el hash porque el usuario debe recibir el valor original.
        return $tokenPlano;
    }

    /**
     * Comprueba que el token todavía no haya expirado.
     */
    public function tokenRecuperacionVigente(): bool
    {
        if (
            $this->token_recuperacion === '' ||
            (int) $this->token_expiracion <= 0
        ) {
            return false;
        }

        //Ejemplo: $tokenExpiración = 2000 y $tiempoActual = 1500, Entonces: 2000 >= 1500 = true (El token no ha vencido)
        return (int) $this->token_expiracion >= time();
    }

    /**
     * Invalida el enlace después de usarlo o expirar.
     */
    public function limpiarTokenRecuperacion(): void
    {
        $this->token_recuperacion = '';
        $this->token_expiracion = 0;
    }

    /**
     * Busca un usuario por correo de forma controlada.
     */
    //?self representa la clase donde está declarado el método, en este caso self = Usuario... o sea, Devuelve un Usuario o null
    public static function buscarPorCorreoRecuperacion(string $correo): ?self
    {
        $correo = strtolower(trim($correo));
        $correo = self::$db->escape_string($correo);

        $query = "
        SELECT *
        FROM " . static::$tabla . "
        WHERE correo = '{$correo}'
        LIMIT 1
    ";

        $resultado = self::consultarSQL($query);

        return array_shift($resultado) ?: null;
    }

    /**
     * Busca al usuario propietario del hash del token.
     */
    public static function buscarPorTokenRecuperacion(string $tokenHash): ?self
    {
        // Un hash SHA-256 hexadecimal debe tener 64 caracteres.
        //La expresión /^[a-f0-9]{64}$/ se interpreta así:
        /*
         ^           inicio del texto
         [a-f0-9]    solo letras de a hasta f o números de 0 hasta 9
         {64}        exactamente 64 caracteres
         $           final del texto
        */
        if (!preg_match('/^[a-f0-9]{64}$/', $tokenHash)) {
            return null;
        }

        //Capa adicional de protección para una consulta SQL construida mediante concatenación
        $tokenHash = self::$db->escape_string($tokenHash);

        $query = "
        SELECT *
        FROM " . static::$tabla . "
        WHERE token_recuperacion = '{$tokenHash}'
        LIMIT 1
    ";

        $resultado = self::consultarSQL($query);

        //Devuelve el primer resultado si es verdadero, de lo contrario devuelve null.
        return array_shift($resultado) ?: null;
    }
}
