<?php
// Aseguramos que exista la variable $alertas como array
if (!isset($alertas) || !is_array($alertas)) {
    $alertas = [];
}

// Importamos alertas guardadas en sesión (por ejemplo después de un redirect)
if (isset($_SESSION['alertas']) && is_array($_SESSION['alertas'])) {

    // Fusionamos las alertas de sesión con las alertas locales
    foreach ($_SESSION['alertas'] as $tipo => $mensajes) {

        if (!isset($alertas[$tipo])) {
            $alertas[$tipo] = [];
        }

        // Unimos mensajes existentes con los de sesión
        $alertas[$tipo] = array_merge($alertas[$tipo], $mensajes);
    }

    // Limpiamos las alertas de sesión para que no se repitan
    unset($_SESSION['alertas']);
}

// Renderizamos las alertas normalmente
foreach ($alertas as $key => $alerta) {
    foreach ($alerta as $mensaje) {
        ?>
        <div class="alerta alerta__<?php echo $key; ?>">
            <?php echo $mensaje; ?>
        </div>
        <?php
    }
}
?>
