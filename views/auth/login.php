<div class="login__card">

    <div class="login__icon-container">
        <div class="login__icon-circle">
            <svg class="login__icon" fill="none" stroke="white" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
            </svg>
        </div>
    </div>

    <h1 class="login__title">SkillView</h1>
    <p class="login__subtitle">Desarrolla tus habilidades<br>blandas para el éxito profesional</p>
    <?php
        require_once __DIR__ . '/../templates/alertas.php';
    ?>

    <form class="login__form" action="/" method="POST">
        <label class="login__label" for="correo">Correo Electrónico</label>
        <input
            type="email"
            class="login__input"
            placeholder="Tu@email.com"
            id="correo"
            name="correo">

        <label class="login__label" for="password">Contraseña</label>
        <input
            type="password"
            class="login__input"
            placeholder="••••••••"
            id="password"
            name="password">

        <input class="login__button" type="submit" value="Iniciar Sesión">
    </form>

    <p class="login__register">
        ¿No tienes cuenta?
        <a href="/registro" class="login__register-link">Crea una</a>
    </p>

</div>