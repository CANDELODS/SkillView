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


                </select>
            </form>
        </section>
        <!-- Fin Filtros -->
    </section>
</main>