<main class="profile">
    <section class="profile__wrapper">

        <!-- Header del perfil: tarjeta de usuario + progreso general -->
        <section class="profile__header">

            <!-- Tarjeta de usuario -->
            <article class="profile-card">
                <div class="profile-card__avatar" aria-hidden="true">
                    <span class="profile-card__initials"><?php echo s($inicialesUsuario); ?></span>
                </div>

                <div class="profile-card__content">
                    <h2 class="profile-card__name">
                        <?php echo s(trim($usuario->nombres . ' ' . $usuario->apellidos)); ?>
                    </h2>

                    <p class="profile-card__role">
                        <?php echo ($usuario->admin) ? 'Administrador' : 'Estudiante'; ?>
                    </p>

                    <ul class="profile-card__info">
                        <li class="profile-card__info-item">
                            <i class="fa-regular fa-envelope"></i>
                            <span><?php echo s($usuario->correo); ?></span>
                        </li>

                        <li class="profile-card__info-item">
                            <i class="fa-solid fa-building-columns" style="color: #9a7e7e;"></i>
                            <span><?php echo s($usuario->universidad); ?></span>
                        </li>

                        <li class="profile-card__info-item">
                            <i class="fa-solid fa-graduation-cap"></i>
                            <span><?php echo s($usuario->carrera); ?></span>
                        </li>
                    </ul>

                    <div class="profile-card__actions">
                        <!-- A futuro puedes convertir esto en link /perfil/editar -->
                        <button
                            class="profile-card__btn profile-card__btn--primary js-profile-modal-open"
                            type="button"
                            data-profile-modal-id="profile-edit-modal">
                            <i class="fa-solid fa-pen-to-square"></i>
                            Editar perfil
                        </button>

                        <form method="POST" action="<?php echo $_ENV['HOST'] . '/logout'; ?>" class="profile-card__logout">
                            <button class="profile-card__btn profile-card__btn--outline" type="submit">
                                <i class="fa-solid fa-right-from-bracket"></i>
                                Cerrar sesión
                            </button>
                        </form>
                    </div>
                </div>
            </article>

            <!-- Progreso general -->
            <aside class="profile-progress">
                <header class="profile-progress__header">
                    <i class="fa-solid fa-arrow-trend-up"></i>
                    <h3 class="profile-progress__title">Progreso General</h3>
                </header>

                <div class="profile-progress__container">
                    <p class="profile-progress__value"><?php echo (int)$progresoGeneral; ?>%</p>

                    <div class="profile-progress__bar" role="progressbar"
                        aria-valuenow="<?php echo (int)$progresoGeneral; ?>"
                        aria-valuemin="0"
                        aria-valuemax="100">
                        <span class="profile-progress__fill" style="width: <?php echo (int)$progresoGeneral; ?>%"></span>
                    </div>

                    <p class="profile-progress__hint">¡Sigue así! Estás progresando muy bien</p>
                </div>
            </aside>

        </section>

        <!-- Puntos totales -->
        <section class="profile-points">
            <div class="profile-points__icon" aria-hidden="true">
                <i class="fa-solid fa-certificate" style="color: #ffffff;"></i>
            </div>

            <div class="profile-points__content">
                <p class="profile-points__label">Puntos Totales</p>
                <p class="profile-points__value"><?php echo (int)$puntosTotales; ?></p>
            </div>
        </section>

        <!-- Progreso por habilidad -->
        <section class="profile-section">
            <header class="profile-section__header">
                <div class="profile-section__title">
                    <i class="fa-solid fa-arrow-trend-up"></i>
                    <h3>Progreso por Habilidad</h3>
                </div>
            </header>

            <div class="profile-table">
                <div class="profile-table__head">
                    <div class="profile-table__col profile-table__col--skill">Habilidad</div>
                    <div class="profile-table__col profile-table__col--level">Nivel</div>
                    <div class="profile-table__col profile-table__col--progress">Progreso</div>
                    <div class="profile-table__col profile-table__col--date">Última actualización</div>
                </div>

                <div class="profile-table__body">
                    <?php if (!empty($progresoPorHabilidad)) : ?>
                        <?php foreach ($progresoPorHabilidad as $fila) : ?>
                            <?php
                            $nivel = $fila['nivel'] ?? 'Básico';
                            $progreso = (int)($fila['progreso'] ?? 0);
                            $fecha = $fila['ultima_actualizacion'] ?? null;

                            // Clase de badge por nivel (mínima lógica de presentación)
                            $nivelClass = 'profile-badge--basic';
                            if ($nivel === 'Intermedio') $nivelClass = 'profile-badge--mid';
                            if ($nivel === 'Avanzado')   $nivelClass = 'profile-badge--adv';

                            // Formato simple de fecha (si viene YYYY-MM-DD)
                            $fechaTxt = $fecha ? date('d M \d\e Y', strtotime($fecha)) : '—';
                            ?>

                            <article class="profile-table__row">
                                <div class="profile-table__cell profile-table__cell--skill">
                                    <?php echo s($fila['habilidad']); ?>
                                </div>

                                <div class="profile-table__cell profile-table__cell--level">
                                    <span class="profile-badge <?php echo $nivelClass; ?>">
                                        <?php echo s($nivel); ?>
                                    </span>
                                </div>

                                <div class="profile-table__cell profile-table__cell--progress">
                                    <div class="profile-mini-progress" aria-hidden="true">
                                        <span class="profile-mini-progress__fill" style="width: <?php echo $progreso; ?>%"></span>
                                    </div>
                                    <span class="profile-mini-progress__text"><?php echo $progreso; ?>%</span>
                                </div>

                                <div class="profile-table__cell profile-table__cell--date">
                                    <i class="fa-regular fa-calendar"></i>
                                    <span><?php echo s($fechaTxt); ?></span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <p class="profile-empty">
                            Aún no tienes progreso registrado. Empieza una ruta en <strong>Aprendizaje</strong> para ver tu avance aquí.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Logros y medallas -->
        <section class="profile-section">
            <header class="profile-section__header profile-section__header--between">
                <div class="profile-section__title">
                    <i class="fa-solid fa-medal"></i>
                    <h3>Logros y Medallas</h3>
                </div>

                <a class="profile-section__link" href="/logros">
                    Ver Todo
                </a>
            </header>

            <div class="profile-achievements">
                <?php if (!empty($medallas)) : ?>
                    <?php foreach ($medallas as $medalla) : ?>
                        <?php
                        $desbloqueado = (bool)($medalla->desbloqueado ?? false);
                        $fecha = $medalla->fecha_obtenido ?? null;
                        $fechaTxt = $fecha ? date('d M \d\e Y', strtotime($fecha)) : null;

                        $cardClass = $desbloqueado
                            ? 'profile-achievement'
                            : 'profile-achievement profile-achievement--locked';
                        ?>

                        <article class="<?php echo $cardClass; ?>">
                            <div class="profile-achievement__icon" aria-hidden="true">
                                <!-- Tu icono SVG dinámico (nombre en BD) -->
                                <img
                                    src="<?php echo $_ENV['HOST'] . '/build/img/logros/' . s($medalla->icono) . '.svg'; ?>"
                                    alt="" loading="lazy" />
                            </div>

                            <div class="profile-achievement__content">
                                <h4 class="profile-achievement__title"><?php echo s($medalla->nombre); ?></h4>
                                <p class="profile-achievement__desc"><?php echo s($medalla->descripcion); ?></p>

                                <?php if ($desbloqueado && $fechaTxt) : ?>
                                    <p class="profile-achievement__date">
                                        <i class="fa-regular fa-calendar"></i>
                                        <span><?php echo s($fechaTxt); ?></span>
                                    </p>
                                <?php else : ?>
                                    <p class="profile-achievement__date profile-achievement__date--muted">
                                        <i class="fa-solid fa-lock"></i>
                                        <span>Bloqueado</span>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p class="profile-empty">
                        Aún no hay logros para mostrar.
                    </p>
                <?php endif; ?>
            </div>
        </section>

        <!-- Modal editar perfil -->
        <div
            id="profile-edit-modal"
            class="profile-modal <?php echo (!empty($mostrarModalEditar)) ? 'profile-modal--visible' : ''; ?>"
            aria-hidden="<?php echo (!empty($mostrarModalEditar)) ? 'false' : 'true'; ?>">

            <div class="profile-modal__backdrop" data-profile-modal-close></div>

            <div class="profile-modal__content" role="dialog" aria-modal="true" aria-labelledby="profile-edit-title">
                <!-- Alertas (éxito / error) -->
                <?php if (isset($alertas) && !empty($alertas)) : ?>
                    <?php include __DIR__ . '/../../templates/alertas.php'; ?>
                <?php endif; ?>
                <header class="profile-modal__header">
                    <div>
                        <h2 id="profile-edit-title" class="profile-modal__title">Editar perfil</h2>
                        <p class="profile-modal__subtitle">Actualiza tu información personal</p>
                    </div>

                    <button type="button" class="profile-modal__close" data-profile-modal-close aria-label="Cerrar">
                        &times;
                    </button>
                </header>

                <form class="profile-form" method="POST" action="<?php echo $_ENV['HOST'] . '/perfil'; ?>">

                    <div class="profile-form__field">
                        <label class="profile-form__label" for="nombres">Nombres</label>
                        <input
                            class="profile-form__input"
                            type="text"
                            id="nombres"
                            name="nombres"
                            value="<?php echo s($usuarioForm->nombres ?? ''); ?>"
                            placeholder="Ej. Juan">
                    </div>

                    <div class="profile-form__field">
                        <label class="profile-form__label" for="apellidos">Apellidos</label>
                        <input
                            class="profile-form__input"
                            type="text"
                            id="apellidos"
                            name="apellidos"
                            value="<?php echo s($usuarioForm->apellidos ?? ''); ?>"
                            placeholder="Ej. Candelo">
                    </div>

                    <div class="profile-form__field">
                        <label class="profile-form__label" for="universidad">Universidad</label>
                        <input
                            class="profile-form__input"
                            type="text"
                            id="universidad"
                            name="universidad"
                            value="<?php echo s($usuarioForm->universidad ?? ''); ?>"
                            placeholder="Ej. Unicomfacauca">
                    </div>

                    <div class="profile-form__field">
                        <label class="profile-form__label" for="carrera">Carrera</label>
                        <input
                            class="profile-form__input"
                            type="text"
                            id="carrera"
                            name="carrera"
                            value="<?php echo s($usuarioForm->carrera ?? ''); ?>"
                            placeholder="Ej. Ingeniería de Sistemas">
                    </div>

                    <div class="profile-form__actions">
                        <button type="button" class="profile-form__btn profile-form__btn--ghost" data-profile-modal-close>
                            Cancelar
                        </button>

                        <button type="submit" class="profile-form__btn profile-form__btn--primary">
                            Guardar cambios
                        </button>
                    </div>

                </form>
            </div>
        </div>

    </section>
</main>