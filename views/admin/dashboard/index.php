<section class="admin-panel">
  <header class="admin-panel__header">
    <h1 class="admin-panel__title">
      Bienvenido al Panel de Administración
    </h1>
    <p class="admin-panel__subtitle">
      Administra usuarios y habilidades blandas de la plataforma SkillView
    </p>
  </header>

  <div class="admin-panel__cards">
    <!-- Card Usuarios -->
    <article class="admin-card admin-card--users">
      <div class="admin-card__header">
        <div class="admin-card__icon-circle">
          <i class="fa-solid fa-user-group admin-card__icon"></i>
        </div>
        <h2 class="admin-card__title">Usuarios</h2>
      </div>

      <p class="admin-card__description">
        Gestiona todos los usuarios registrados en la plataforma.
      </p>

      <a href="/admin/usuarios" class="admin-card__button">
        Ir a Usuarios
      </a>
    </article>

    <!-- Card Habilidades -->
    <article class="admin-card admin-card--skills">
      <div class="admin-card__header">
        <div class="admin-card__icon-circle--yellow">
          <i class="fa-regular fa-star admin-card__icon"></i>
        </div>
        <h2 class="admin-card__title">Habilidades</h2>
      </div>

      <p class="admin-card__description">
        Administra las habilidades blandas del sistema.
      </p>

      <a href="/admin/habilidades" class="admin-card__button">
        Ir a Habilidades
      </a>
    </article>
  </div>
</section>
