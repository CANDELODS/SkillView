<header class="users-admin__header">
        <h1 class="users-admin__title"><?php echo $titulo; ?></h1>
        <div class="users-admin__container-search--skill">
            <div class="users-admin__form-input">
                <form action="/admin/habilidades" method="GET" class="users-admin__search">
                        <input
                            type="text"
                            class="users-admin__search-input"
                            placeholder="Buscar por nombre o tag..."
                            name="busqueda"
                            id="busqueda"
                            value="<?php echo isset($_GET['busqueda']) ? htmlspecialchars($_GET['busqueda']) : ''; ?>"
                        >
                    <input type="submit" class="users-admin__search-button" value="Buscar">
                </form>
            </div>
            <!--Este enlace nos servirá para volver a ver todos los resultados cuando el usuario haya filtrado los datos-->
            <a href="/admin/habilidades?page=1" class="users-admin__search-button--bf">Borrar Filtro</a>
            <!-- Enlace para ir a /admin/habilidades/crear -->
             <a href="/admin/habilidades/crear" class="users-admin__search-button--cs">Crear Habilidad</a>
        </div>
</header>

        <!-- Verificamos si hay habilidades para mostrar -->
        <?php if (!empty($habilidades)) { ?>
            <table class="table">
                <thead class="table__thead">
                    <tr class="table__trhead">
                        <th scope="col" class="table__th">ID</th>
                        <th scope="col" class="table__th">Nombre</th>
                        <th scope="col" class="table__th"></th>
                    </tr>
                </thead>
                <tbody class="table__tbody">
                    <!-- Iteramos habilidad por habilidad -->
                    <?php foreach ($habilidades as $habilidad) { ?>
                        <tr class="table__tr">
                            <td data-label="ID" class="table__td">
                                <!-- Mostramos los ID de cada habilidad -->
                                <?php echo $habilidad->id; ?>
                            </td>
                            <td data-label="Nombre" class="table__td">
                                <!-- Mostramos los nombres de cada habilidad -->
                                <?php echo $habilidad->nombre; ?>
                            </td>
                            <td class="table__td--acciones">
                                <!-- Enlace para redirigir al usuario a la vista de admin/habilidades/editar, además se manda el id de la habilidad a editar
                                 por medio de la URL -->
                                <a class="table__accion table__accion--editar" href="/admin/habilidades/editar?id=<?php echo $habilidad->id; ?>">Editar</a>
                                <!-- Botón para eliminar una habilidad, además tiene un input de tipo hidden el cual manda el id de la habilidad
                                 al servidor y así poder eliminar la habilidad -->
                                <!--Enviamos el event en la funcion confirmDelete para poder leerlo con JS y pausar el envío automático del Form-->
                                <form method="POST" action="/admin/habilidades/eliminar" class="table__form" onsubmit="return confirmDelete(event, '¿Estás seguro de que deseas eliminar esta habilidad?.')">
                                    <input type="hidden" name="id" value="<?php echo $habilidad->id; ?>">
                                    <button class="table__accion table__accion--eliminar" type="submit">
                                        Eliminar
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?> <!--Fin foreach($habilidades as $habilidad)-->
                </tbody>
            </table>
        <?php } else { ?>
            <p class="text-center">No Hay Habilidades Para Listar</p>
        <?php } ?> <!--Fin if(!empty($habilidades))-->
    <!-- Mostramos los enlaces de la paginación -->
     <div class="dashboard__pagAlert">
        <?php
            if (!empty($habilidades)) {
            echo $paginacion;
            }
        ?>
        <?php
        require_once __DIR__ . '/../../templates/alertas.php';
        ?>
     </div>
</main>