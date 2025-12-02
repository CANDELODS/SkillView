<main class="learning">
  <section class="learning__wrapper">

    <!-- Hero: título y subtítulo -->
    <header class="learning__header">
      <h1 class="learning__title"><?php echo $titulo; ?></h1>
      <p class="learning__subtitle">
        Tu ruta hacia el crecimiento personal empieza aquí. Completa cada módulo para desbloquear nuevas habilidades.
      </p>
    </header>

    <!-- Resumen general de progreso -->
    <section class="learning-summary">
      <div class="learning-summary__top">
        <div class="learning-summary__label">
          <i class="fa-solid fa-trophy learning-summary__icon"></i>
          <div class="learning-summary__text">
            <span class="learning-summary__title">Progreso general</span>
            <span class="learning-summary__percentage">
              <?php echo round($porcentajeProgreso); ?>%
            </span>
          </div>
        </div>

        <div class="learning-summary__meta">
          <span class="learning-summary__meta-label">Lecciones completadas</span>
          <span class="learning-summary__meta-value">
            <?php echo $leccionesCompletadasUsuario; ?> / <?php echo $totalLeccionesSistema; ?>
          </span>
        </div>
      </div>

      <div class="progress">
        <div class="progress__bar">
          <!-- este width vendrá calculado en PHP -->
          <div class="progress__fill" min="<?php $porcentajeProgreso; ?>, 100" style="width: <?php echo $porcentajeProgreso; ?>%;"></div>
        </div>
      </div>
    </section>

<!-- CAMINO / ROADMAP -->
    <section class="learning-roadmap">
      <div class="learning-roadmap__line"></div>

      <ul class="learning-roadmap__list">
        <?php foreach ($habilidades as $index => $habilidad): ?>
          <?php
          $estado   = $habilidad->estado;
          $posicion = ($index % 2 === 0) ? 'left' : 'right';

          $porcentaje = $habilidad->total_lecciones > 0
            ? ($habilidad->lecciones_completadas / $habilidad->total_lecciones) * 100
            : 0;
          ?>
          <li class="learning-roadmap__item learning-roadmap__item--<?= $posicion ?> learning-roadmap__item--<?= $estado ?>">

            <!-- Nodo central -->
            <div class="learning-roadmap__node">
              <span class="learning-roadmap__status-icon">
                <?php if ($estado === 'completed'): ?>
                  <i class="fa-solid fa-check learning-roadmap__i"></i>
                <?php elseif ($estado === 'current'): ?>
                  <i class="fa-solid fa-users learning-roadmap__i"></i>
                <?php else: ?>
                  <i class="fa-solid fa-brain learning-roadmap__i"></i>
                <?php endif; ?>
              </span>

              <?php if ($estado === 'current'): ?>
                <span class="learning-roadmap__badge">Actual</span>
              <?php endif; ?>
            </div>

            <!-- Card del módulo / habilidad -->
            <article class="module-card">
              <h2 class="module-card__title">
                <?= htmlspecialchars($habilidad->nombre); ?>
              </h2>

              <p class="module-card__meta">
                <?= $habilidad->lecciones_completadas; ?> de <?= $habilidad->total_lecciones; ?> lecciones completadas
              </p>

              <div class="progress progress--module">
                <div class="progress__bar">
                  <div class="progress__fill"
                    style="width: <?= $porcentaje; ?>%;"></div>
                </div>
              </div>

              <div class="module-card__actions">
                <?php if ($estado === 'completed'): ?>
                  <a href="/aprendizaje/habilidad?id=<?= $habilidad->id; ?>"
                    class="module-card__btn module-card__btn--outline">
                    Revisar
                  </a>
                <?php elseif ($estado === 'current'): ?>
                  <a href="/aprendizaje/habilidad?id=<?= $habilidad->id; ?>"
                    class="module-card__btn">
                    Continuar
                  </a>
                <?php elseif ($estado === 'upcoming'): ?>
                  <a href="/aprendizaje/habilidad?id=<?= $habilidad->id; ?>"
                    class="module-card__btn">
                    Empezar lección
                  </a>
                <?php else: ?>
                  <button class="module-card__btn module-card__btn--disabled" disabled>
                    Bloqueado
                  </button>
                <?php endif; ?>
              </div>
            </article>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
<!-- FIN CAMINO / ROADMAP -->

<!-- Cards inferiores: Pon a prueba tus habilidades / Tus logros -->
    <section class="learning-cta">
      <article class="learning-cta__card">
        <div class="learning-cta__content">
          <i class="fa-solid fa-bullseye learning-cta__icon"></i>
          <h2 class="learning-cta__title">Pon a prueba tus habilidades</h2>
          <p class="learning-cta__text">
            Aplica lo aprendido en retos prácticos y situaciones reales.
          </p>
          <a href="/retos" class="learning-cta__btn">
            Ir a Retos
          </a>
        </div>
      </article>

      <article class="learning-cta__card">
        <div class="learning-cta__content">
          <i class="fa-solid fa-trophy learning-cta__icon"></i>
          <h2 class="learning-cta__title">Tus logros</h2>
          <p class="learning-cta__text">
            Revisa tu progreso, insignias y certificados obtenidos.
          </p>
          <a href="/perfil" class="learning-cta__btn">
            Ver Perfil
          </a>
        </div>
      </article>
    </section>
  </section>
</main>