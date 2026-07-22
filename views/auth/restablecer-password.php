<div class="login__card">

    <div class="login__icon-container">
        <div class="login__icon-circle">
            <svg class="login__icon" fill="none" stroke="white"viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"
                />
            </svg>
        </div>
    </div>

    <h1 class="login__title">Restablecer contraseña</h1>

    <p class="login__subtitle login__subtitle--nMargin">Crea una nueva contraseña para tu cuenta de SkillView.</p>

    <?php
    require_once __DIR__ . '/../templates/alertas.php';
    ?>

    <p class="login__subtitle login__subtitle--nMargin">
        La contraseña debe tener entre 6 y 16 caracteres,
        incluir una mayúscula, un número y un carácter especial.
    </p>

    <form class="login__form" action="/restablecer-password" method="POST">
        <input type="hidden" name="token" value="<?php echo s($token); ?>">

        <label class="login__label" for="password">Nueva contraseña</label>

        <input type="password" class="login__input" placeholder="••••••••"
            id="password"
            name="password"
            minlength="6"
            maxlength="16"
            autocomplete="new-password"
            required
        >

        <label class="login__label" for="password2">Repetir nueva contraseña</label>

        <input type="password" class="login__input" placeholder="••••••••"
            id="password2"
            name="password2"
            minlength="6"
            maxlength="16"
            autocomplete="new-password"
            required
        >

        <input
            class="login__button"
            type="submit"
            value="Restablecer contraseña"
        >
    </form>

    <p class="login__register">
        <a href="/" class="login__register-link">Volver al inicio de sesión</a>
    </p>

</div>