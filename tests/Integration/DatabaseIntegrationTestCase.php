<?php

declare(strict_types=1);

namespace Tests\Integration;

use Dotenv\Dotenv;
use mysqli;
use Model\ActiveRecord;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Clase base para las pruebas de integración que utilizan MySQL.
 *
 * Responsabilidades:
 * - Cargar las variables del entorno.
 * - Crear la conexión con MySQL.
 * - Impedir pruebas sobre una base distinta de skillview_test.
 * - Iniciar una transacción antes de cada prueba.
 * - Revertir todos los cambios después de cada prueba.
 */
abstract class DatabaseIntegrationTestCase extends TestCase
{
    protected const TEST_DATABASE = 'skillview_test';

    protected static mysqli $db;

    /**
     * Se ejecuta una sola vez antes de comenzar
     * las pruebas de cada clase de integración.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $rutaProyecto = dirname(__DIR__, 2);

        /*
         * La aplicación carga el archivo .env desde
         * la carpeta includes, por lo que las pruebas
         * utilizan la misma ubicación.
         */
        $dotenv = Dotenv::createImmutable(
            $rutaProyecto . '/includes'
        );

        $dotenv->safeLoad();

        /*
         * Hace que los errores de MySQL produzcan
         * excepciones visibles en PHPUnit.
         */
        mysqli_report(
            MYSQLI_REPORT_ERROR
            | MYSQLI_REPORT_STRICT
        );

        /*
         * Este archivo crea la variable local $db.
         */
        require $rutaProyecto
            . '/includes/database.php';

        if (
            !isset($db)
            || !$db instanceof mysqli
        ) {
            throw new RuntimeException(
                'No fue posible crear la conexión '
                . 'con la base de datos de pruebas.'
            );
        }

        self::$db = $db;

        /*
         * Entregar la misma conexión al ORM utilizado
         * por los modelos del proyecto.
         */
        ActiveRecord::setDB(self::$db);

        /*
         * Protección crítica:
         * ninguna prueba puede continuar si la base
         * conectada no es skillview_test.
         */
        $baseActual = self::nombreBaseDatosActual();

        if ($baseActual !== self::TEST_DATABASE) {
            self::$db->close();

            throw new RuntimeException(
                'Ejecución cancelada. Las pruebas de '
                . 'integración solo pueden utilizar '
                . self::TEST_DATABASE
                . ". Base detectada: {$baseActual}"
            );
        }
    }

    /**
     * Antes de cada prueba se inicia una transacción.
     */
    protected function setUp(): void
    {
        parent::setUp();

        self::$db->begin_transaction();
    }

    /**
     * Después de cada prueba se revierten todos
     * los INSERT, UPDATE y DELETE efectuados.
     */
    protected function tearDown(): void
    {
        self::$db->rollback();

        parent::tearDown();
    }

    /**
     * Cierra la conexión después de terminar
     * todas las pruebas de la clase.
     */
    public static function tearDownAfterClass(): void
    {
        if (isset(self::$db)) {
            self::$db->close();
        }

        parent::tearDownAfterClass();
    }

    /**
     * Obtiene el nombre de la base seleccionada
     * en la conexión activa.
     */
    protected static function nombreBaseDatosActual(): string
    {
        $resultado = self::$db->query(
            'SELECT DATABASE() AS database_name'
        );

        $fila = $resultado->fetch_assoc();

        $resultado->free();

        return (string)(
            $fila['database_name']
            ?? ''
        );
    }
}