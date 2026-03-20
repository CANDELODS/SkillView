<header class="site-header">
    <!-- Barra superior -->
    <div class="site-header__top">
        <div class="site-header__brand--lesson">
            <a href="/retos" class="site-header__back">
             &larr;
            </a>
            <a class="site-header__brand-link">
                <span class="site-header__logo" aria-hidden="true">
                    <svg fill="none" stroke="white" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                    </svg>
                </span>
                <span class="site-header__brand-text">
                    <span class="site-header__brand-name"><?php echo $reto->nombreHabilidad ?? 'Habilidad no encontrada'; ?></span>
                    <span class="site-header__brand-slogan">Reto: <?php echo $reto->nombre ?? 'Lección no encontrada'; ?></span>
                </span>
            </a>
        </div>

        <div class="site-header__right site-header__right--lesson">
            <!-- Info usuario -->
            <div class="site-header__user">
                <div class="site-header__user-info">
                    <span class="site-header__user-name">
                        <?php echo $nombreUsuario ?? 'Juan Candelo'; ?>
                    </span>
                    <span class="site-header__user-role">Estudiante</span>
                </div>
                <div class="site-header__user-avatar" aria-hidden="true">
                    <?php echo $inicialesUsuario ?? 'JC'; ?>
                </div>
            </div>
        </div>
    </div>
</header>
