<div class="login__card">

    <div class="login__icon-container">
        <div class="login__icon-circle">
            <svg class="login__icon" fill="none" stroke="white" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
            </svg>
        </div>
    </div>

    <h1 class="login__title">Crea una nueva contraseña</h1>
    <p class="login__subtitle"> La contraseña proporcionada por el administrador es temporal.
        Para continuar en SkillView debes establecer una nueva contraseña.</p>
    <?php
    require_once __DIR__ . '/../templates/alertas.php';
    ?>

    <form class="login__form" action="/cambiar-password" method="POST">
        <label class="login__label" for="password">Contraseña</label>
        <input
            type="password"
            class="login__input"
            placeholder="••••••••"
            id="password"
            name="password"
            maxlength="16"
            minlength="6"
            required>

        <label class="login__label" for="password2">Repetir contraseña</label>
        <input
            class="login__input"
            type="password"
            placeholder="••••••••"
            id="password2"
            name="password2"
            maxlength="16"
            minlength="6"
            required>

<input class="login__button" type="submit" value="Continuar">
</form>

<p class="login__register">
    <a href="/" class="login__register-link">Volver al inicio</a>
</p>

</div>