<main class="lesson">
  <section class="lesson__wrap">
    
    <!-- Escenario -->
    <div class="lesson__stage">
      
      <!-- Avatar / Asistente -->
      <aside class="lesson__assistant" aria-label="Asistente SkillView">
        <div class="lesson__assistantCard">
          <div class="lesson__avatar">
            <!-- Aquí luego montas el avatar IA (img/video/canvas) -->
            <img class="lesson__avatarImg" src="/build/img/blog/responsabilidad-1.webp" alt="Asistente SkillView">
            <span class="lesson__status" title="En línea"></span>
          </div>

          <div class="lesson__assistantInfo">
            <h2 class="lesson__assistantName">Asistente SkillView</h2>
            <p class="lesson__assistantRole">Tu guía en el desarrollo de habilidades blandas</p>
          </div>

          <!-- Bubble destacada (tipo “pregunta actual”) -->
          <div class="lesson__prompt">
            <p class="lesson__promptText">
              Hola <?php echo $nombreUsuario ?? 'Juan Candelo'; ?> bienvenido a la lección <?php echo $leccion->orden;?>: <?php echo $leccion->titulo ?? 'Lección no encontrada'; ?>
            </p>
          </div>
        </div>
      </aside>

      <!-- Chat -->
      <section class="lesson__chat" aria-label="Conversación de la lección">
        
        <!-- Mensajes -->
        <div class="lesson__messages" id="lesson-messages">
          
          <!-- Mensaje del asistente -->
          <article class="lesson__msg lesson__msg--assistant">
            <div class="lesson__bubble lesson__bubble--assistant">
              <p class="lesson__text">
                Hola, bienvenido a la lección <strong><?php echo $leccion->titulo ?? 'Lección' ?></strong>.
                Hoy vamos a trabajar la habilidad <strong><?php echo $leccion->nombreHabilidad ?? 'Habilidad' ?></strong>.
              </p>
            </div>
          </article>

          <!-- <article class="lesson__msg lesson__msg--assistant">
            <div class="lesson__bubble lesson__bubble--assistant">
              <p class="lesson__text">
                La autoconfianza no es “sentirse invencible”, es confiar en que puedes manejar lo que venga.
              </p>
            </div>
          </article> -->

          <!-- Mensaje del usuario -->
          <article class="lesson__msg lesson__msg--user">
            <div class="lesson__bubble lesson__bubble--user">
              <p class="lesson__text">
                Para mí es creer en mis capacidades incluso cuando tengo dudas.
              </p>
            </div>
          </article>

          <!-- Tip (estado) -->
          <div class="lesson__hint" aria-live="polite">
            <span class="lesson__hintDot"></span>
            <span class="lesson__hintText">El asistente está analizando tu respuesta…</span>
          </div>

        </div>

        <!-- Composer -->
        <form class="lesson__composer" autocomplete="off">
          <button class="lesson__iconBtn" type="button" aria-label="Responder por voz">
            <i class="fa-solid fa-microphone"></i>
          </button>

          <input
            class="lesson__input"
            type="text"
            name="message"
            placeholder="Escribe tu respuesta..."
            aria-label="Escribe tu respuesta"
          />

          <button class="lesson__sendBtn" type="submit" aria-label="Enviar respuesta">
            <i class="fa-solid fa-paper-plane"></i>
          </button>
        </form>

      </section>

    </div>
  </section>
</main>