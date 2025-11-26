<div 
  class="register register--edit"
  <?php echo (!empty($alertasExito)) ? 'data-registro-exitoso="1"' : ''; ?>>

  <div class="register__card register__card--create">

    <div class="register__icon">
      <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a 2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
      </svg>
    </div>

    <h1 class="register__title">SkillView</h1>
    <p class="register__subtitle">
      Crea las habilidades blandas de la app SkillView
    </p>

    <?php
    require_once __DIR__ . '/../../templates/alertas.php';
    ?>
    
    <form class="login__form" action="/admin/habilidades/crear" method="POST">

      <!-- Nombre -->
        <label class="login__label" for="nombre">Nombre</label>
        <input
          class="login__input"
          type="text"
          placeholder="Comunicación efectiva"
          id="nombre"
          name="nombre"
          value="<?php echo $habilidad->nombre; ?>">

      <!-- Descripcion -->
        <label class="login__label" for="descripcion">Descripcion</label>
        <textarea
        class="login__input--textarea"
        name="descripcion"
        id="descripcion"
        placeholder="Descripción de la habilidad"><?php echo htmlspecialchars($habilidad->descripcion); ?></textarea>

      <!-- tags -->
        <label class="login__label" for="tags_input">Tags de la habilidad (Separados por coma)</label>
        <input
          class="login__input"
          type="text"
          placeholder="Ej. Liderazgo, Trabajo en equipo, Comunicación, Empatía"
          id="tags_input">
    
      <div class="login__list" id="tags"></div>
      <input type="hidden" name="tag" value="<?php echo $habilidad->tag ?? ''; ?>">

      <!-- habilitado -->
       <div class="login__check">
        <label class="login__label" for="habilitado">Habilidad Habilitada</label>
            <input type="hidden" name="habilitado"
                value="0"
                id="habilitado">
        <input type="checkbox"
            id="habilitado"
            class="login__check--check"
            name="habilitado"
            value="1"
            <?php if ($habilidad->habilitado === '1') { ?>
            checked>
            <?php } else { ?>
                >
            <?php } ?>
       </div>
<!-- Si la habilidad en su atributo 'habilitado' es = 1 (Si) entonces agregamos el atributo checked 
 al checkbox, de lo contrario no lo ponemos (Edición)-->
      <!-- Submit -->
      <button class="login__button" type="submit">Crear Habilidad</button>
      <a href="/admin/habilidades" class="login__button--back">Volver</a>
    </form>
    <?php
    // Tomamos el primer mensaje de éxito (si existe) para mostrarlo en el modal
    $mensajeExito = '';
    if (!empty($alertasExito)) {
        // Tomamos el primer mensaje de éxito
        $mensajeExito = current($alertasExito);
    }
    ?>
    <!--HTML PARA EL MODAL-->
    <div id="modal-registro-exitoso" class="modal">
      <div class="modal__backdrop"></div>
      <div class="modal__content" role="dialog" aria-modal="true" aria-labelledby="modal-registro-title">

        <div class="modal__icon">
          <!-- Un ícono simple de check -->
          <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M5 13l4 4L19 7" />
          </svg>
        </div>

        <h2 id="modal-registro-title" class="modal__title">Habilidad Creada</h2>

        <p class="modal__message">
          <?php echo $mensajeExito ? htmlspecialchars($mensajeExito, ENT_QUOTES, 'UTF-8') : 'La habilidad se creó correctamente.'; ?>
        </p>

        <button type="button" class="modal__button" data-modal-close>
          Ir al dashboard nuevamente
        </button>

      </div> <!--FIN modal__content-->
    </div><!--FIN HTML PARA EL MODAL-->
  </div> <!--FIN register__card-->
</div> <!--FIN register-->