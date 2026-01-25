<main class="achievements">
    <section class="achievements__wrapper">

        <!-- Hero: título y subtítulo -->
        <header class="achievements__header">
            <h1 class="achievements__title"><?php echo $titulo; ?></h1>

            <p class="achievements__subtitle">
                Desbloquea todos los logros completando retos, lecciones y alcanzando objetivos<br>
                en tus habilidades blandas.
            </p>
        </header>
        <!-- Fin Hero -->

        <!-- Stats -->
        <section class="achievements__stats">
            <article class="achievements__stat achievements__stat--unlocked">
                <p class="achievements__stat-label">Logros Desbloqueados</p>
                <p class="achievements__stat-value"><?php echo (int)$totalDesbloqueados; ?></p>
            </article>

            <article class="achievements__stat achievements__stat--locked">
                <p class="achievements__stat-label">Logros Bloqueados</p>
                <p class="achievements__stat-value"><?php echo (int)$totalBloqueados; ?></p>
            </article>

            <article class="achievements__stat achievements__stat--total">
                <p class="achievements__stat-label">Total</p>
                <p class="achievements__stat-value"><?php echo (int)$totalLogros; ?></p>
            </article>
        </section>
        <!-- Fin Stats -->

        <!-- =================== DESBLOQUEADOS =================== -->
        <section class="achievements__section">
            <div class="achievements__section-head">
                <i class="fa-solid fa-medal"></i>
                <h2 class="achievements__section-title">
                    Logros Desbloqueados (<?php echo (int)$totalDesbloqueados; ?>)
                </h2>
            </div>

            <?php if (!empty($logrosDesbloqueados)) : ?>
                <div class="achievements__grid">
                    <?php foreach ($logrosDesbloqueados as $logro) : ?>
                        <article class="achievement-card achievement-card--unlocked">
                            <header class="achievement-card__header">
                                <div class="achievement-card__icon">
                                    <img
                                    class="achievement-card__icon-img"
                                    src="<?php echo $_ENV['HOST'] . '/build/img/logros/' . s($logro->icono) . '.svg'; ?>"
                                    alt="Icono del logro" loading="lazy" />
                                </div>

                                <div class="achievement-card__meta">
                                    <h3 class="achievement-card__title"><?php echo s($logro->nombre); ?></h3>
                                    <p class="achievement-card__tag">General</p>
                                </div>
                            </header>

                            <p class="achievement-card__description">
                                <?php echo s($logro->descripcion); ?>
                            </p>

                            <div class="achievement-card__objective">
                                <span class="achievement-card__objective-label">Objetivo</span>
                                <span class="achievement-card__objective-value"><?php echo (int)$logro->valor_objetivo; ?></span>
                            </div>

                            <?php
                            // Formateo tipo "10 oct 2025"
                            $fechaFormateada = '';
                            if (!empty($logro->fecha_obtenido)) {
                                $timestamp = strtotime($logro->fecha_obtenido);
                                if ($timestamp) {
                                    $meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
                                    $dia = (int)date('d', $timestamp);
                                    $mes = $meses[(int)date('m', $timestamp) - 1] ?? date('m', $timestamp);
                                    $anio = date('Y', $timestamp);
                                    $fechaFormateada = "{$dia} {$mes} {$anio}";
                                }
                            }
                            ?>

                            <footer class="achievement-card__footer">
                                <div class="achievement-card__date">
                                    <span class="achievement-card__date-icon" aria-hidden="true">📅</span>
                                    <span class="achievement-card__date-text">
                                        Desbloqueado <?php echo s($fechaFormateada); ?>
                                    </span>
                                </div>
                            </footer>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="achievements__empty">
                    Aún no has desbloqueado logros. Completa retos y lecciones para comenzar.
                </p>
            <?php endif; ?>
        </section>
        <!-- =================== FIN DESBLOQUEADOS =================== -->

        <!-- =================== BLOQUEADOS =================== -->
        <section class="achievements__section achievements__section--locked">
            <div class="achievements__section-head">
                <h2 class="achievements__section-title">
                    Logros Bloqueados (<?php echo (int)$totalBloqueados; ?>)
                </h2>
            </div>

            <?php if (!empty($logrosBloqueados)) : ?>
                <div class="achievements__grid achievements__grid--locked">
                    <?php foreach ($logrosBloqueados as $logro) : ?>
                        <article class="achievement-card achievement-card--locked">
                            <header class="achievement-card__header">
                                <div class="achievement-card__icon">
                                    <img
                                    class="achievement-card__icon-img"
                                    src="<?php echo $_ENV['HOST'] . '/build/img/logros/' . s($logro->icono) . '.svg'; ?>"
                                    alt="Icono del logro" loading="lazy" />
                                </div>

                                <div class="achievement-card__meta">
                                    <h3 class="achievement-card__title"><?php echo s($logro->nombre); ?></h3>
                                    <p class="achievement-card__tag">General</p>
                                </div>
                            </header>

                            <p class="achievement-card__description">
                                <?php echo s($logro->descripcion); ?>
                            </p>

                            <div class="achievement-card__objective">
                                <span class="achievement-card__objective-label">Objetivo</span>
                                <span class="achievement-card__objective-value"><?php echo (int)$logro->valor_objetivo; ?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="achievements__empty">
                    No hay logros bloqueados por ahora.
                </p>
            <?php endif; ?>
        </section>
        <!-- =================== FIN BLOQUEADOS =================== -->

    </section>
</main>
