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

                <select name="habilidad" class="challenges__select challenges__select--skills" onchange="this.form.submit()">
                    <option value="">Todas las habilidades</option>

                    <?php foreach ($habilidadesFiltro as $hab) : ?>
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
                <?php foreach ($retos as $reto) : ?>
                    <!-- tu card -->
                <?php endforeach; ?>
            <?php endif; ?>
            <div class="challenges-cards__grid">
                <?php foreach ($retos as $reto) : ?>
                    <article class="challenges-card">
                        <div class="challenges-card__top">
                            <span class="challenges-card__spanTop">
                                <i class="fa-regular fa-message challenges-card__iTop"></i>
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
                            <!-- Condición: Si el reto no se ha completado entonces: -->
                            <button type="button"
                                class="challenges-card__button">
                                Iniciar Reto
                            </button>
                            <!-- Si ya se completó entonces deshabilitar botón -->
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</main>