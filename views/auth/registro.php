<div class="register">
  <div class="register__card">

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
    require_once __DIR__ . '/../templates/alertas.php';
    ?>

    <form class="register__form" action="/registro" method="POST">

      <!-- Nombres -->
       <div class="register__field--nombres">
           <label class="register__label" for="nombres">Nombres</label>
           <input 
             class="register__input"
             type="text"
             placeholder="Juan Sebastian"
             id="nombres"
             name="nombres"
             value="<?php echo $usuario->nombres; ?>"
           >
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
        value="<?php echo $usuario->apellidos; ?>"
      >
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
        value="<?php echo $usuario->edad; ?>"
      >
      </div>
      
      <!-- Sexo -->
      <div class="register__field--sexo">
      <label class="register__label" for="sexo">Sexo</label>
      <select class="register__input" id="sexo" name="sexo">
        <option selected value="">Selecciona</option>
        <option value="0">Masculino</option>
        <option value="1">Femenino</option>
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
        value="<?php echo $usuario->universidad; ?>"
      >
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
        value="<?php echo $usuario->carrera; ?>"
      >
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
      >
      </div>
      
      <!-- Password -->
      <div class="register__field--contraseña">
      <label class="register__label" for="password">Contraseña</label>
      <input 
        class="register__input"
        type="password"
        placeholder="••••••••"
        id="password"
        name="password"
      >
    </div>
    
    <!-- Password 2 -->
    <div class="register__field--contraseña2">    
    <label class="register__label" for="password2">Repetir contraseña</label>
      <input 
        class="register__input"
        type="password"
        placeholder="••••••••"
        id="password2"
        name="password2"
      >
    </div>  
      <!-- Submit -->
      <button class="register__button" type="submit">Crear Cuenta</button>

    </form>

    <p class="register__footer">
      ¿Ya tienes cuenta? <a href="/" class="register__link">Inicia Sesión</a>
    </p>

  </div>
</div>
