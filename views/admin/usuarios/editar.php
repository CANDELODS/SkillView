<div 
  class="register register--edit"
  <?php echo (!empty($alertasExito)) ? 'data-registro-exitoso="1"' : ''; ?>>

  <div class="register__card register__card--edit">

    <div class="register__icon">
      <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a 2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
      </svg>
    </div>

    <h1 class="register__title">SkillView</h1>
    <p class="register__subtitle">
      Únete y comienza tu camino hacia el éxito profesional
    </p>

    <?php
    require_once __DIR__ . '/../../templates/alertas.php';
    ?>
    <!--No necesitamos action ya que nos borraría el id que traemos desde la URL-->
    <form class="register__form--edit" method="POST">

      <!-- Nombres -->
      <div class="register__field--nombres">
        <label class="register__label" for="nombres">Nombres</label>
        <input
          class="register__input"
          type="text"
          placeholder="Juan Sebastian"
          id="nombres"
          name="nombres"
          value="<?php echo $usuario->nombres; ?>">
      </div>

      <!-- Apellidos -->
      <div class="register__field--apellidos">
        <label class="register__label" for="apellidos">Apellidos</label>
        <input
          class="register__input"
          type="text"
          placeholder="Pérez"
          id="apellidos"
          name="apellidos"
          value="<?php echo $usuario->apellidos; ?>">
      </div>

      <!-- Edad -->
      <div class="register__field--edad">
        <label class="register__label" for="edad">Edad</label>
        <input
          class="register__input"
          type="number"
          min="1"
          max="30"
          placeholder="25"
          id="edad"
          name="edad"
          value="<?php echo $usuario->edad; ?>">
      </div>

      <!-- Sexo -->
      <div class="register__field--sexo">
        <label class="register__label" for="sexo">Sexo</label>
        <select class="register__input" id="sexo" name="sexo">
            <!-- El value será 0 si la validación es correcta, además agregamos el atributo selected
             y mostramos en pantalla el nombre al que hace referencia 0 (Masculino) 1 (Femenino) -->
            <option value="0" <?php echo ($usuario->sexo == '0') ? 'selected' : ''; ?>>
                Masculino
            </option>

            <option value="1" <?php echo ($usuario->sexo == '1') ? 'selected' : ''; ?>>
                Femenino
            </option>
        </select>

      </div>

      <!-- Universidad -->
      <div class="register__field--universidad">
        <label class="register__label" for="universidad">Universidad</label>
        <input
          class="register__input"
          type="text"
          placeholder="Unicomfacauca"
          id="universidad"
          name="universidad"
          value="<?php echo $usuario->universidad; ?>">
      </div>

      <!-- Carrera -->
      <div class="register__field--carrera">
        <label class="register__label" for="carrera">Carrera</label>
        <input
          class="register__input"
          type="text"
          placeholder="Ingeniería de Sistemas"
          id="carrera"
          name="carrera"
          value="<?php echo $usuario->carrera; ?>">
      </div>

      <!-- Correo -->
      <div class="register__field--correo">
        <label class="register__label" for="correo">Correo electrónico</label>
        <input
          class="register__input"
          type="email"
          placeholder="tu@email.com"
          id="correo"
          name="correo"
          value="<?php echo $usuario->correo; ?>">
      </div>

      <!-- Password -->
      <div class="register__field--contraseña">
        <label class="register__label" for="password">Contraseña</label>
        <input
          class="register__input"
          type="password"
          placeholder="••••••••"
          id="password"
          name="password">
      </div>

      <!-- Password 2 -->
      <div class="register__field--contraseña2">
        <label class="register__label" for="password2">Repetir contraseña</label>
        <input
          class="register__input"
          type="password"
          placeholder="••••••••"
          id="password2"
          name="password2">
      </div>
      <!-- Submit -->
      <button class="register__button" type="submit">Editar Usuario</button>
      <a href="/admin/usuarios" class="register__button--back">Volver</a>
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

        <h2 id="modal-registro-title" class="modal__title">Usuario Actualizado</h2>

        <p class="modal__message">
          <?php echo $mensajeExito ? htmlspecialchars($mensajeExito, ENT_QUOTES, 'UTF-8') : 'El usuario se actualizó correctamente.'; ?>
        </p>

        <button type="button" class="modal__button" data-modal-close>
          Ir al dashboard nuevamente
        </button>

      </div> <!--FIN modal__content-->
    </div><!--FIN HTML PARA EL MODAL-->
  </div> <!--FIN register__card-->
</div> <!--FIN register-->