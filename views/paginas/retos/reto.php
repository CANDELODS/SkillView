<main class="challenge" data-reto-id="<?php echo (int)$reto->id; ?>" data-habilidad-id="<?php echo (int)$reto->id_habilidades; ?>">
  <!-- Loader -->
  <div class="challenge-loader" id="challenge-loader" aria-hidden="false">
    <div class="challenge-loader__backdrop"></div>

    <div class="challenge-loader__content">
      <div class="challenge-loader__avatar">
        <img src="/build/img/blog/responsabilidad-1.webp" alt="Asistente SkillView">
      </div>

      <h2 class="challenge-loader__title">Asistente SkillView</h2>
      <p class="challenge-loader__text">Preparando tu reto...</p>

      <div class="challenge-loader__dots">
        <span></span>
        <span></span>
        <span></span>
      </div>
    </div>
  </div>
  <!-- FIN Loader -->

  <section class="challenge__wrap">
    <div class="challenge__stage">

      <!-- Asistente -->
      <aside class="challenge__assistant" aria-label="Asistente SkillView">
        <div class="challenge__assistantCard">
          <div class="challenge__avatar">
            <img
              class="challenge__avatarImg"
              src="/build/img/blog/responsabilidad-1.webp"
              alt="Asistente SkillView">
            <span class="challenge__status" title="En línea"></span>
          </div>

          <div class="challenge__assistantInfo">
            <h2 class="challenge__assistantName">Asistente SkillView</h2>
            <p class="challenge__assistantRole">Tu guía en el desarrollo de habilidades blandas</p>
          </div>

          <div class="challenge__prompt">
            <p class="challenge__promptText">
              Hola <?php echo $nombreUsuario ?? 'Juan Candelo'; ?>, bienvenido al reto:
              <?php echo $reto->nombre ?? 'Reto no encontrado'; ?>
            </p>
          </div>
        </div>
      </aside>

      <!-- Chat -->
      <section class="challenge__chat" aria-label="Conversación del reto">
        <div class="challenge__messages" id="challenge-messages"></div>

        <form class="challenge__composer" autocomplete="off">
          <button
            class="challenge__iconBtn"
            data-challenge-mic-btn
            type="button"
            aria-label="Responder por voz">
            <i class="fa-solid fa-microphone"></i>
          </button>

          <input
            class="challenge__input"
            type="text"
            name="message"
            placeholder="Escribe tu respuesta..."
            aria-label="Escribe tu respuesta" />

          <button
            class="challenge__sendBtn"
            type="submit"
            aria-label="Enviar respuesta">
            <i class="fa-solid fa-paper-plane"></i>
          </button>
        </form>
      </section>

    </div>
  </section>
</main>

<section class="sv-challenge-result-modal" id="sv-challenge-result-modal" aria-hidden="true">
  <div class="sv-challenge-result-modal__backdrop"></div>

  <div
    class="sv-challenge-result-modal__dialog"
    role="dialog"
    aria-modal="true"
    aria-labelledby="sv-challenge-result-modal-title">
    <header class="sv-challenge-result-modal__header">
      <div class="sv-challenge-result-modal__heading">
        <h2 class="sv-challenge-result-modal__title" id="sv-challenge-result-modal-title">
          Resultado del reto
        </h2>
      </div>
    </header>
    <div class="sv-challenge-result-modal__score" data-sv-challenge-result-score hidden></div>
    <div class="sv-challenge-result-modal__body" data-sv-challenge-result-body>
      <!-- Aquí se inyectan los mensajes -->
    </div>

    <footer class="sv-challenge-result-modal__footer">
      <button
        type="button"
        class="sv-challenge-result-modal__btn sv-challenge-result-modal__btn--primary"
        data-sv-challenge-result-continue>
        Continuar
      </button>
    </footer>
  </div>
</section>