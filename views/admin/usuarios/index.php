<main class="main">
<h2 class="dashboard__heading"><?php echo $titulo;?></h2>

    <div class="tabla--scrollX">
        <!-- Verificamos si hay usuarios para mostrar -->
        <?php if (!empty($usuarios)) { ?>
            <table class="table">
                <thead class="table__thead">
                    <tr>
                        <th scope="col" class="table__th">Nombres</th>
                        <th scope="col" class="table__th">Apellidos</th>
                        <th scope="col" class="table__th">Edad</th>
                        <th scope="col" class="table__th">Sexo</th>
                        <th scope="col" class="table__th">Correo</th>
                        <th scope="col" class="table__th">Universidad</th>
                        <th scope="col" class="table__th"></th>
                    </tr>
                </thead>
                <tbody class="table__tbody">
                    <!-- Iteramos usuario por usuario -->
                    <?php foreach ($usuarios as $usuario) { ?>
                        <tr class="table__tr">
                            <td data-label="Nombre" class="table__td">
                                <!-- Mostramos los nombres de cada usuario -->
                                <?php echo $usuario->nombres; ?>
                            </td>
                            <td data-label="Apellidos" class="table__td">
                                <!-- Mostramos los apellidos de cada usuario -->
                                <?php echo $usuario->apellidos; ?>
                            </td>
                            <td data-label="Edad" class="table__td">
                                <!-- Mostramos la edad de cada usuario -->
                                <?php echo $usuario->edad; ?>
                            </td>
                            <td data-label="Sexo" class="table__td">
                                <!-- Mostramos el sexo de cada usuario -->
                                <?php echo $usuario->sexo; ?>
                            </td>
                            <td data-label="Correo" class="table__td">
                                <!-- Mostramos el correo de cada usuario -->
                                <?php echo $usuario->correo; ?>
                            </td>
                            <td data-label="Universidad" class="table__td">
                                <!-- Mostramos la universidad de cada usuario -->
                                <?php echo $usuario->universidad; ?>
                            </td>
                            <td class="table__td--acciones">
                                <!-- Enlace para redirigir al usuario a la vista de admin/usuarios/editar, además se manda el id del usuario a editar
                                 por medio de la URL -->
                                <a class="table__accion table__accion--editar" href="/admin/usuarios/editar?id=<?php echo $usuario->id; ?>">Editar</a>
                                <!-- Botón para eliminar un usuario, además tiene un input de tipo hidden el cual manda el id del usuario
                                 al servidor y así poder eliminar el usuario -->
                                <form method="post" action="/admin/usuarios/eliminar" class="table__form" onsubmit="return confirmDelete('¿Estás seguro de que deseas eliminar este usuario?.')">
                                    <input type="hidden" name="id" value="<?php echo $usuario->id; ?>">
                                    <button class="table__accion table__accion--eliminar" type="submit">
                                        Eliminar
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?> <!--Fin foreach($usuarios as $usuario)-->
                </tbody>
            </table>
        <?php } else { ?>
            <p class="text-center">No Hay Usuarios Para Listar</p>
        <?php } ?> <!--Fin if(!empty($usuarios))-->
    </div>
    <!-- Mostramos los enlaces de la paginación -->
    <?php
    if (!empty($equipos)) {
        echo $paginacion;
    }
    ?>
</main>