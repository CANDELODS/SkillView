<main class="learning">
  <section class="learning__wrapper">

    <!-- Hero: título y subtítulo -->
    <header class="learning__header">
      <h1 class="learning__title"><?php echo $titulo;?></h1>
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
            <span class="learning-summary__percentage">24%</span>
          </div>
        </div>

        <div class="learning-summary__meta">
          <span class="learning-summary__meta-label">Lecciones completadas</span>
          <span class="learning-summary__meta-value">8 / 34</span>
        </div>
      </div>

      <div class="progress">
        <div class="progress__bar">
          <!-- este width vendrá calculado en PHP -->
          <div class="progress__fill" style="width: 24%;"></div>
        </div>
      </div>
    </section>


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
