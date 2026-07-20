<div class="login__card">

    <div class="login__icon-container">
        <div class="login__icon-circle">
            <svg class="login__icon" fill="none" stroke="white" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
            </svg>
        </div>
    </div>

    <h1 class="login__title">Recuperar Contraseña</h1>
    <p class="login__subtitle">Ingresa el correo electrónico asociado a tu cuenta</p>
    <?php
    require_once __DIR__ . '/../templates/alertas.php';
    ?>

    <?php if ($mensajeRecuperacion): ?>
        <div class="alerta alerta__exito">
            <?php echo s($mensajeRecuperacion); ?>
        </div>
    <?php endif; ?>

    <form class="login__form" action="/recuperar-password" method="POST">
        <label class="login__label" for="correo">Correo Electrónico</label>
        <input
            type="email"
            class="login__input"
            placeholder="Tu@email.com"
            id="correo"
            name="correo"
            value="<?php echo s($correo); ?>"
            required>

        <input class="login__button" type="submit" value="Continuar">
    </form>

    <p class="login__register">
        <a href="/" class="login__register-link">Volver a iniciar sesión</a>
    </p>

</div>