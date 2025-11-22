<aside class="sidebar">
    <div class="sidebar__links-top">
        <div class="sidebar__top">
          <div class="sidebar__brand">
            <a href="/admin/dashboard" class="sidebar__brand--a">SkillView</a>
          </div>
      
          <button class="sidebar__close" type="button">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>
      
        <nav class="sidebar__nav">
            <!-- sidebar__link--active -->
          <a href="/admin/usuarios" class="sidebar__link">
            <span class="sidebar__icon">
              <i class="fa-solid fa-user-group"></i>
            </span>
            <span class="sidebar__text">Usuarios</span>
          </a>
      
          <a href="/admin/habilidades" class="sidebar__link">
            <span class="sidebar__icon">
              <i class="fa-regular fa-star"></i>
            </span>
            <span class="sidebar__text">Habilidades</span>
          </a>
        </nav>
    </div>

  <form class="sidebar__logout" action="/logout" method="POST">
    <button class="sidebar__logout-button" type="submit">
      <span class="sidebar__icon sidebar__icon--small">
        <i class="fa-solid fa-right-from-bracket"></i>
      </span>
      <span>Cerrar Sesión</span>
    </button>
  </form>
</aside>
