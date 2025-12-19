<main class="challenges">
    <section class="challenges__wrapper">
        <!-- Hero: título y subtítulo -->
        <header class="challenges__header">
            <h1 class="challenges__title"><?php echo $titulo; ?></h1>
            <p class="challenges__subtitle">
                Aplica lo aprendido enfrentando situaciones reales y demuestra tu crecimiento en habilidades blandas.
            </p>
        </header>
        <!-- Fin Hero: título y subtítulo -->

        <!-- Filtros -->
        <section class="challenges__filter">
            <form method="GET" action="/retos" class="challenges__filter-flex">
                <!-- onchange="this.form.submit()" nos permite enviar el formulario apenas cambie el select, por lo cual no se necesita botón "Filtrar" -->
                <select name="habilidad" class="challenges__select challenges__select--skills" onchange="this.form.submit()">
                    <option value="">Todas las habilidades</option>

                    <?php foreach ($habilidadesFiltro as $hab) : ?>
                        <!-- Si la habilidad coincide con el filtro entonces le agregamos el atributo selectd y mostramos su nombre -->
                        <option value="<?php echo (int)$hab->id; ?>"
                            <?php echo ($filtroHabilidad === (int)$hab->id) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($hab->nombre); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="dificultad" class="challenges__select challenges__select--difficults" onchange="this.form.submit()">
                    <option value="">Todas las dificultades</option>
                    <option value="1" <?php echo ($filtroDificultad === 1) ? 'selected' : ''; ?>>Básico</option>
                    <option value="2" <?php echo ($filtroDificultad === 2) ? 'selected' : ''; ?>>Intermedio</option>
                    <option value="3" <?php echo ($filtroDificultad === 3) ? 'selected' : ''; ?>>Avanzado</option>
                </select>

            </form>
        </section>
        <!-- Fin Filtros -->

    </section>

    <section class="challenges-cards">
        <div class="challenges-cards__container">
            <?php if (empty($retos)) : ?>
                <p class="challenges-cards__empty">
                    No se encontraron retos con los filtros seleccionados.
                </p>
            <?php else : ?>
                <div class="challenges-cards__grid">
                    <?php foreach ($retos as $reto) : ?>
                        <article class="challenges-card">
                            <div class="challenges-card__top">
                                <span class="challenges-card__spanTop">
                                    <i class="<?php echo $reto->icono; ?> challenges-card__iTop"></i>
                                </span>
                                <!-- Este valor será dinámico -->
                                <p class="challenges-card__difficult"><?php echo $reto->dificultad; ?></p>
                            </div>

                            <div class="challenges-card__middle">
                                <!-- Titulo Dinámico <?php echo $titulo; ?> -->
                                <h2 class="challenges-card__tittle"><?php echo $reto->nombre; ?></h2>
                                <!-- Descripción Dinámica -->
                                <p class="challenges-card__descripcion"><?php echo $reto->descripcion; ?></p>

                                <div class="challenges-card__tagContainer">
                                    <?php foreach ($reto->tags as $tag) : ?>
                                        <p class="challenges-card__tag"><?php echo htmlspecialchars($tag); ?></p>
                                    <?php endforeach; ?>
                                </div>
                                <div class="challenges-card__timePoints">
                                    <span class="challenges-card__time">
                                        <i class="fa-regular fa-clock challenges-card__iTime"></i>
                                        <!-- Tiempo dinámico <?php echo $retos->tiempo_min; ?> - <?php echo $retos->tiempo_max; ?> minutos -->
                                        <p class="challenges-card__pTime"><?php echo $reto->tiempo_min; ?> - <?php echo $reto->tiempo_max; ?> minutos</p>
                                    </span>

                                    <span class="challenges-card__points">
                                        <i class="fa-solid fa-trophy challenges-card__iPoints"></i>
                                        <!-- Cantidad de puntos dinámica <?php echo $retos->puntos; ?> puntos -->
                                        <p class="challenges-card__pPoints"><?php echo $reto->puntos; ?> puntos</p>
                                    </span>
                                </div>
                            </div>

                            <div class="challenges-card__bottom">
                                <?php if (!empty($reto->completado)) : ?>
                                    <button type="button" class="challenges-card__button challenges-card__button--completed" disabled>
                                        Completado
                                    </button>
                                <?php else : ?>
                                    <button type="button" class="challenges-card__button"
                                        data-sv-challenge-open
                                        data-id="<?php echo (int)$reto->id; ?>"
                                        data-title="<?php echo htmlspecialchars($reto->nombre); ?>"
                                        data-desc="<?php echo htmlspecialchars($reto->descripcion); ?>"
                                        data-difficulty="<?php echo htmlspecialchars($reto->dificultad); ?>"
                                        data-time-min="<?php echo (int)$reto->tiempo_min; ?>"
                                        data-time-max="<?php echo (int)$reto->tiempo_max; ?>"
                                        data-points="<?php echo (int)$reto->puntos; ?>"
                                        data-tags="<?php echo htmlspecialchars(implode(',', $reto->tags ?? [])); ?>"
                                        data-start-url="/retos/iniciar?id=<?php echo (int)$reto->id; ?>"
                                        >
                                        Iniciar Reto
                                    </button>
                                <?php endif; ?>
                            </div>

                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
                </div>
        </div>
    </section>
    <!-- ===================== CHALLENGE MODAL (RETOS) ===================== -->
    <section class="sv-challenge-modal" id="sv-challenge-modal" aria-hidden="true">
        <div class="sv-challenge-modal__backdrop" data-sv-challenge-close></div>

        <div class="sv-challenge-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="sv-challenge-modal-title">
            <header class="sv-challenge-modal__header">
                <div class="sv-challenge-modal__heading">
                    <h2 class="sv-challenge-modal__title" id="sv-challenge-modal-title">Título del reto</h2>
                    <span class="sv-challenge-modal__badge" data-sv-challenge-badge>Intermedio</span>
                </div>

                <button type="button" class="sv-challenge-modal__close" aria-label="Cerrar" data-sv-challenge-close>
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </header>

            <p class="sv-challenge-modal__subtitle" data-sv-challenge-desc>
                Descripción del reto...
            </p>

            <div class="sv-challenge-modal__section">
                <div class="sv-challenge-modal__section-title">
                    <i class="fa-solid fa-bullseye"></i>
                    <h3>En qué consiste</h3>
                </div>
                <p class="sv-challenge-modal__text" data-sv-challenge-consiste>
                    Este reto te presentará...
                </p>
            </div>

            <div class="sv-challenge-modal__section">
                <h3 class="sv-challenge-modal__small-title">Habilidades que pondrás a prueba:</h3>
                <div class="sv-challenge-modal__tags" data-sv-challenge-tags>
                    <!-- tags dinámicos -->
                </div>
            </div>

            <div class="sv-challenge-modal__cards">
                <div class="sv-challenge-modal__info-card">
                    <span class="sv-challenge-modal__info-icon">
                        <i class="fa-regular fa-clock"></i>
                    </span>
                    <div class="sv-challenge-modal__info-body">
                        <p class="sv-challenge-modal__info-label">Duración</p>
                        <p class="sv-challenge-modal__info-value" data-sv-challenge-time>10-15 minutos</p>
                    </div>
                </div>

                <div class="sv-challenge-modal__info-card">
                    <span class="sv-challenge-modal__info-icon sv-challenge-modal__info-icon--trophy">
                        <i class="fa-solid fa-trophy"></i>
                    </span>
                    <div class="sv-challenge-modal__info-body">
                        <p class="sv-challenge-modal__info-label">Recompensa</p>
                        <p class="sv-challenge-modal__info-value" data-sv-challenge-points>75 puntos</p>
                    </div>
                </div>
            </div>

            <div class="sv-challenge-modal__alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <p>Una vez iniciado el reto, no podrás pausarlo. Asegúrate de tener tiempo suficiente para completarlo.</p>
            </div>

            <footer class="sv-challenge-modal__footer">
                <button type="button" class="sv-challenge-modal__btn sv-challenge-modal__btn--ghost" data-sv-challenge-close>
                    Cancelar
                </button>

                <a class="sv-challenge-modal__btn sv-challenge-modal__btn--primary" href="#" data-sv-challenge-start>
                    Comenzar reto
                </a>
            </footer>
        </div>
    </section>
    <!-- ===================== /CHALLENGE MODAL ===================== -->

</main>