<main class="lesson" data-leccion-id="<?php echo (int)$leccion->id; ?>" data-habilidad-id="<?php echo (int)$leccion->id_habilidades; ?>">
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
              Hola <?php echo $nombreUsuario ?? 'Juan Candelo'; ?> bienvenido a la lección <?php echo $leccion->orden; ?>: <?php echo $leccion->titulo ?? 'Lección no encontrada'; ?>
            </p>
          </div>
        </div>
      </aside>

      <!-- Chat -->
      <section class="lesson__chat" aria-label="Conversación de la lección">

        <!-- Mensajes -->
        <div class="lesson__messages" id="lesson-messages"></div>

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
            aria-label="Escribe tu respuesta" />

          <button class="lesson__sendBtn" type="submit" aria-label="Enviar respuesta">
            <i class="fa-solid fa-paper-plane"></i>
          </button>
        </form>

      </section>

    </div>
  </section>
</main>
<section class="sv-lesson-result-modal" id="sv-lesson-result-modal" aria-hidden="true">
  <div class="sv-lesson-result-modal__backdrop"></div>

  <div
    class="sv-lesson-result-modal__dialog"
    role="dialog"
    aria-modal="true"
    aria-labelledby="sv-lesson-result-modal-title">
    <header class="sv-lesson-result-modal__header">
      <div class="sv-lesson-result-modal__heading">
        <h2 class="sv-lesson-result-modal__title" id="sv-lesson-result-modal-title">
          Resultado de la lección
        </h2>
      </div>
    </header>

    <div class="sv-lesson-result-modal__body" data-sv-lesson-result-body>
      <!-- Aquí se inyectan los mensajes -->
    </div>

    <footer class="sv-lesson-result-modal__footer">
      <button
        type="button"
        class="sv-lesson-result-modal__btn sv-lesson-result-modal__btn--primary"
        data-sv-lesson-result-continue>
        Continuar
      </button>
    </footer>
  </div>
</section>