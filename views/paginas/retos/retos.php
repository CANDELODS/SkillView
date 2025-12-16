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
            <div class="challenges__filter-flex">
                <select name="" id="" class="challenges__select--skills">
                    <option selected value="">Todas las habilidades</option>
                    <!-- Iterar las habilidades-->
                    <option class="challenges__option" value="">Comunicación efectiva</option>
                </select>
                <select name="" id="" class="challenges__select--difficults">
                    <option selected value="">Todas las dificultades</option>
                    <!-- Iterar los niveles de la tabla retos-->
                    <option class="challenges__option" value="">Básico</option>
                </select>
            </div>
        </section>
        <!-- Fin Filtros -->

    </section>

    <section class="challenges-cards">
        <div class="challenges-cards__container">
            <div class="challenges-cards__grid">
                <?php foreach($retos as $reto) : ?>
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
                            <!-- La cantidad de tags y su texto será dinámica -->
                            <p class="challenges-card__tag"><?php echo $reto->tag; ?></p>
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