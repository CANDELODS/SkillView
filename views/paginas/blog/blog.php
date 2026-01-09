<main class="blog">
    <section class="blog__wrapper">
        <!-- Hero: título y subtítulo -->
        <header class="blog__header">
            <h1 class="blog__title"><?php echo $titulo; ?></h1>
            <p class="blog__subtitle">
                Descubre artículos, consejos y casos reales para fortalecer tus habilidades blandas
            </p>
        </header>
        <!-- Fin Hero: título y subtítulo -->

        <!-- Filtros -->
        <section class="blog__filter">
            <form method="GET" action="/blog" class="blog__filter-flex">
                <p class="blog__filter-p">Selecciona la habilidad de la cual quieres ver el artículo</p>
                <!-- onchange="this.form.submit()" nos permite enviar el formulario apenas cambie el select, por lo cual no se necesita botón "Filtrar" -->
                <select name="habilidad" class="blog__select blog__select--skills" onchange="this.form.submit()">
                    <option value="">Todas las habilidades</option>
                    <?php foreach ($habilidadesFiltro as $hab) : ?>
                        <!-- Si la habilidad coincide con el filtro entonces le agregamos el atributo selectd y mostramos su nombre -->
                        <option value="<?php echo (int)$hab->id; ?>"
                            <?php echo ($habilidadSeleccionada === (int)$hab->id) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($hab->nombre); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </section>
        <!-- Fin Filtros -->

        <!-- Grid de cards -->
        <section class="blog__content">
            <?php if (!empty($blogs)) : ?>
                <div class="blog__grid">
                    <?php foreach ($blogs as $blog) : ?>
                        <?php
                        $tags = $tagsPorBlog[$blog->id] ?? [];
                        // Convertimos los tags en un string para pasarlo al modal (separados por coma)
                        $tagsStr = implode(',', array_map(fn($t) => $t['nombre'], $tags));
                        ?>
                        <article class="blog-card">
                            <div class="blog-card__image-wrapper">
                                <picture>
                                    <source srcset="<?php
                                                    echo $_ENV['HOST'] . '/build/img/blog/' . $blog->imagen . '.webp'; ?>"
                                        type="image/webp">
                                    <source srcset="<?php
                                                    echo $_ENV['HOST'] . '/build/img/blog/' . $blog->imagen . '.webp'; ?>"
                                        type="image/webp">
                                    <img class="blog-card__image"
                                        src="<?php
                                                echo $_ENV['HOST'] . '/build/img/blog/' . $blog->imagen . '.jpg';
                                                ?>"
                                        alt="Imagen del artículo <?php echo htmlspecialchars($blog->titulo); ?>"
                                        loading="lazy">
                                </picture>
                            </div>

                            <div class="blog-card__body">
                                <h3 class="blog-card__title">
                                    <?php echo htmlspecialchars($blog->titulo); ?>
                                </h3>

                                <p class="blog-card__description">
                                    <?php echo htmlspecialchars($blog->descripcion_corta); ?>
                                </p>

                                <div class="blog-card__tags">
                                    <?php foreach ($tags as $tag) : ?>
                                        <span class="blog-card__tag">
                                            <?php echo htmlspecialchars($tag['nombre']); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>

                                <div class="blog-card__actions">
                                    <button class="blog-card__button"
                                        type="button"
                                        data-sv-blog-open
                                        data-id="<?php echo (int)$blog->id; ?>"
                                        data-title="<?php echo htmlspecialchars($blog->titulo); ?>"
                                        data-desc="<?php echo htmlspecialchars($blog->descripcion_corta); ?>"
                                        data-content="<?php echo htmlspecialchars($blog->contenido); ?>"
                                        data-tags="<?php echo htmlspecialchars($tagsStr); ?>"
                                        data-image-webp="<?php echo $_ENV['HOST'] . '/build/img/blog/' . $blog->imagen . '.webp'; ?>"
                                        data-image-jpg="<?php echo $_ENV['HOST'] . '/build/img/blog/' . $blog->imagen . '.jpg'; ?>">
                                        Leer más
                                    </button>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="blog__empty">
                    No hay artículos disponibles para este filtro.
                </p>
            <?php endif; ?>
        </section>

        <!-- Modal Blog (reutilizable) -->
        <section class="sv-blog-modal" id="sv-blog-modal" aria-hidden="true">
            <div class="sv-blog-modal__backdrop" data-sv-blog-close></div>

            <div class="sv-blog-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="sv-blog-modal-title">
                <button class="sv-blog-modal__close" type="button" data-sv-blog-close aria-label="Cerrar">
                    ✕
                </button>

                <div class="sv-blog-modal__media">
                    <picture>
                        <source id="sv-blog-modal-img-webp" srcset="" type="image/webp">
                        <img id="sv-blog-modal-img"
                            class="sv-blog-modal__img"
                            src=""
                            alt=""
                            loading="lazy">
                    </picture>
                </div>

                <div class="sv-blog-modal__content">
                    <h2 class="sv-blog-modal__title" id="sv-blog-modal-title" data-sv-blog-title></h2>

                    <p class="sv-blog-modal__desc" data-sv-blog-desc></p>

                    <div class="sv-blog-modal__tags" data-sv-blog-tags></div>

                    <div class="sv-blog-modal__text" data-sv-blog-content></div>

                    <div class="sv-blog-modal__actions">
                        <button class="sv-blog-modal__btn" type="button" data-sv-blog-close>Volver</button>
                    </div>
                </div>
            </div>
        </section>
        <!-- Fin Modal Blog (reutilizable) -->
    </section>
</main>
