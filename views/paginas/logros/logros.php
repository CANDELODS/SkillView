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
                <i class="fa-solid fa-medal" style="color: #eb4328;"></i>
                <h2 class="achievements__section-title">
                    Logros Desbloqueados (<?php echo (int)$totalDesbloqueados; ?>)
                </h2>
            </div>

            <?php if (!empty($logrosDesbloqueados)) : ?>
                <div class="achievements__grid">
                    <?php foreach ($logrosDesbloqueados as $logro) : ?>
                        <article
                            class="achievement-card achievement-card--unlocked js-achievement-modal-open"
                            data-achievement-modal-id="sv-achievement-modal"
                            data-achievement-nombre="<?php echo s($logro->nombre); ?>"
                            data-achievement-descripcion="<?php echo s($logro->descripcion); ?>"
                            data-achievement-icono="<?php echo $_ENV['HOST'] . '/build/img/logros/' . s($logro->icono) . '.svg'; ?>"
                            data-achievement-tipo="<?php echo s($logro->tag_texto); ?>"
                            data-achievement-objetivo="<?php echo (int)$logro->valor_objetivo; ?>"
                            data-achievement-desbloqueado="1"
                            data-achievement-fecha="<?php echo s($logro->fecha_formateada); ?>">
                            <header class="achievement-card__header">
                                <div class="achievement-card__icon">
                                    <img
                                        class="achievement-card__icon-img"
                                        src="<?php echo $_ENV['HOST'] . '/build/img/logros/' . s($logro->icono) . '.svg'; ?>"
                                        alt="Icono del logro" loading="lazy" />
                                </div>

                                <div class="achievement-card__meta">
                                    <h3 class="achievement-card__title"><?php echo s($logro->nombre); ?></h3>
                                    <p class="achievement-card__tag"><?php echo s($logro->tag_texto); ?></p>
                                </div>
                            </header>

                            <p class="achievement-card__description">
                                <?php echo s($logro->descripcion); ?>
                            </p>

                            <div class="achievement-card__objective">
                                <span class="achievement-card__objective-label">Objetivo</span>
                                <span class="achievement-card__objective-value"><?php echo (int)$logro->valor_objetivo; ?></span>
                            </div>

                            <footer class="achievement-card__footer">
                                <div class="achievement-card__date">
                                    <span class="achievement-card__date-icon" aria-hidden="true">📅</span>
                                    <span class="achievement-card__date-text">
                                        Desbloqueado <?php echo s($logro->fecha_formateada); ?>
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
                <i class="fa-solid fa-lock" style="color: #413c3c;"></i>
                <h2 class="achievements__section-title">
                    Logros Bloqueados (<?php echo (int)$totalBloqueados; ?>)
                </h2>
            </div>

            <?php if (!empty($logrosBloqueados)) : ?>
                <div class="achievements__grid achievements__grid--locked">
                    <?php foreach ($logrosBloqueados as $logro) : ?>
                        <article
                            class="achievement-card achievement-card--locked js-achievement-modal-open"
                            data-achievement-modal-id="sv-achievement-modal"
                            data-achievement-nombre="<?php echo s($logro->nombre); ?>"
                            data-achievement-descripcion="<?php echo s($logro->descripcion); ?>"
                            data-achievement-icono="<?php echo $_ENV['HOST'] . '/build/img/logros/' . s($logro->icono) . '.svg'; ?>"
                            data-achievement-tipo="<?php echo s($logro->tag_texto); ?>"
                            data-achievement-objetivo="<?php echo (int)$logro->valor_objetivo; ?>"
                            data-achievement-desbloqueado="0"
                            data-achievement-fecha="">
                            <header class="achievement-card__header">
                                <div class="achievement-card__icon">
                                    <img
                                        class="achievement-card__icon-img"
                                        src="<?php echo $_ENV['HOST'] . '/build/img/logros/' . s($logro->icono) . '.svg'; ?>"
                                        alt="Icono del logro" loading="lazy" />
                                </div>

                                <div class="achievement-card__meta">
                                    <h3 class="achievement-card__title"><?php echo s($logro->nombre); ?></h3>
                                    <p class="achievement-card__tag"><?php echo s($logro->tag_texto); ?></p>
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

    <!-- =================== MODAL LOGRO =================== -->
    <div class="achievement-modal" id="sv-achievement-modal" aria-hidden="true">
        <div class="achievement-modal__overlay" data-achievement-modal-close></div>

        <div class="achievement-modal__content" role="dialog" aria-modal="true" aria-labelledby="sv-achievement-modal-title">
            <button class="achievement-modal__close" type="button" aria-label="Cerrar" data-achievement-modal-close>&times;</button>

            <div class="achievement-modal__icon">
                <img id="sv-achievement-modal-icon" src="" alt="Icono del logro" loading="lazy">
            </div>

            <h3 class="achievement-modal__title" id="sv-achievement-modal-title"></h3>
            <p class="achievement-modal__tag" id="sv-achievement-modal-tag"></p>
            <p class="achievement-modal__desc" id="sv-achievement-modal-desc"></p>

            <div class="achievement-modal__status" id="sv-achievement-modal-status">
                <!-- Se llena por JS -->
            </div>

            <button class="achievement-modal__btn" type="button" data-achievement-modal-close>Continuar</button>
        </div>
    </div>
    <!-- =================== FIN MODAL LOGRO =================== -->

</main>