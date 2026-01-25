<header class="site-header">
    <!-- Barra superior -->
    <div class="site-header__top">
        <div class="site-header__brand">
            <a href="/principal" class="site-header__brand-link">
                <span class="site-header__logo" aria-hidden="true">
                    <svg fill="none" stroke="white" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                    </svg>
                </span>
                <span class="site-header__brand-text">
                    <span class="site-header__brand-name">SkillView</span>
                    <span class="site-header__brand-slogan">Desarrolla tus habilidades blandas</span>
                </span>
            </a>
        </div>

        <div class="site-header__right">
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

            <!-- Botón menú móvil -->
            <button class="site-header__toggle"
                    type="button"
                    aria-label="Abrir menú"
                    aria-expanded="false"
                    aria-controls="mobile-nav">
                <span class="site-header__toggle-line"></span>
                <span class="site-header__toggle-line"></span>
                <span class="site-header__toggle-line"></span>
            </button>
        </div>
    </div>

    <!-- Barra de navegación (desktop) -->
    <nav class="site-nav site-nav--desktop" aria-label="Navegación principal">
        <ul class="site-nav__list">
            <li class="site-nav__item">
                <a href="/principal"
                   class="site-nav__link <?php echo pagina_actual('/principal') ? 'site-nav__link--active' : ''; ?>">
                    <i class="fa-solid fa-house site-nav__icon" aria-hidden="true"></i>
                    <span>Inicio</span>
                </a>
            </li>
            <li class="site-nav__item">
                <a href="/aprendizaje"
                   class="site-nav__link <?php echo pagina_actual('/aprendizaje') ? 'site-nav__link--active' : ''; ?>">
                    <i class="fa-solid fa-book-open site-nav__icon" aria-hidden="true"></i>
                    <span>Aprendizaje</span>
                </a>
            </li>
            <li class="site-nav__item">
                <a href="/retos"
                   class="site-nav__link <?php echo pagina_actual('/retos') ? 'site-nav__link--active' : ''; ?>">
                    <i class="fa-solid fa-trophy site-nav__icon" aria-hidden="true"></i>
                    <span>Retos</span>
                </a>
            </li>
            <li class="site-nav__item">
                <a href="/blog"
                   class="site-nav__link <?php echo pagina_actual('/blog') ? 'site-nav__link--active' : ''; ?>">
                    <i class="fa-solid fa-file-lines site-nav__icon" aria-hidden="true"></i>
                    <span>Blog</span>
                </a>
            </li>
            <li class="site-nav__item">
                <a href="/perfil"
                   class="site-nav__link <?php echo pagina_actual('/perfil') ? 'site-nav__link--active' : ''; ?>">
                    <i class="fa-solid fa-user site-nav__icon" aria-hidden="true"></i>
                    <span>Perfil</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Navegación móvil (panel rojo) -->
    <nav class="site-nav site-nav--mobile" id="mobile-nav" aria-label="Navegación móvil">
        <div class="site-nav__mobile-header">
            <span class="site-nav__mobile-brand">SkillView</span>
            <button class="site-nav__mobile-close" type="button" aria-label="Cerrar menú">
                <span class="site-nav__mobile-close-icon">&times;</span>
            </button>
        </div>
        <ul class="site-nav__list site-nav__list--mobile">
            <li class="site-nav__item">
                <a href="/principal"
                   class="site-nav__link <?php echo pagina_actual('/principal') ? 'site-nav__link--active' : ''; ?>">
                    <i class="fa-solid fa-house site-nav__icon" aria-hidden="true"></i>
                    <span>Inicio</span>
                </a>
            </li>
            <li class="site-nav__item">
                <a href="/aprendizaje"
                   class="site-nav__link <?php echo pagina_actual('/aprendizaje') ? 'site-nav__link--active' : ''; ?>">
                    <i class="fa-solid fa-book-open site-nav__icon" aria-hidden="true"></i>
                    <span>Aprendizaje</span>
                </a>
            </li>
            <li class="site-nav__item">
                <a href="/retos"
                   class="site-nav__link <?php echo pagina_actual('/retos') ? 'site-nav__link--active' : ''; ?>">
                    <i class="fa-solid fa-trophy site-nav__icon" aria-hidden="true"></i>
                    <span>Retos</span>
                </a>
            </li>
            <li class="site-nav__item">
                <a href="/blog"
                   class="site-nav__link <?php echo pagina_actual('/blog') ? 'site-nav__link--active' : ''; ?>">
                    <i class="fa-solid fa-file-lines site-nav__icon" aria-hidden="true"></i>
                    <span>Blog</span>
                </a>
            </li>
            <li class="site-nav__item">
                <a href="/perfil"
                   class="site-nav__link <?php echo pagina_actual('/perfil') ? 'site-nav__link--active' : ''; ?>">
                    <i class="fa-solid fa-user site-nav__icon" aria-hidden="true"></i>
                    <span>Perfil</span>
                </a>
            </li>
        </ul>
    </nav>
</header>
