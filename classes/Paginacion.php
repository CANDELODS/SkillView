<?php

namespace Classes;

use InvalidArgumentException;

class Paginacion
{
    public int $pagina_actual;
    public int $registros_por_pagina;
    public int $total_registros;
    public string $extraQuery;

    public function __construct(
        $pagina_actual = 1,
        $registros_por_pagina = 10,
        $total_registros = 0,
        $extraQuery = ''
    ) {
        $paginaActual = (int) $pagina_actual;
        $registrosPorPagina = (int) $registros_por_pagina;
        $totalRegistros = (int) $total_registros;

        if ($paginaActual < 1) {
            throw new InvalidArgumentException(
                'La página actual debe ser mayor o igual a 1.'
            );
        }

        if ($registrosPorPagina <= 0) {
            throw new InvalidArgumentException(
                'La cantidad de registros por página debe ser mayor que 0.'
            );
        }

        if ($totalRegistros < 0) {
            throw new InvalidArgumentException(
                'El total de registros no puede ser negativo.'
            );
        }

        $this->pagina_actual = $paginaActual;
        $this->registros_por_pagina = $registrosPorPagina;
        $this->total_registros = $totalRegistros;
        $this->extraQuery = $this->normalizarExtraQuery(
            (string) $extraQuery
        );
    }

    /**
     * Calcula el desplazamiento que se utilizará en OFFSET.
     */
    public function offset(): int
    {
        return $this->registros_por_pagina
            * ($this->pagina_actual - 1);
    }

    /**
     * Calcula la cantidad total de páginas.
     */
    public function totalPaginas(): int
    {
        if ($this->total_registros === 0) {
            return 0;
        }

        return (int) ceil(
            $this->total_registros
            / $this->registros_por_pagina
        );
    }

    /**
     * Devuelve la página anterior o false cuando no existe.
     *
     * @return int|false
     */
    public function paginaAnterior()
    {
        if ($this->pagina_actual <= 1) {
            return false;
        }

        return $this->pagina_actual - 1;
    }

    /**
     * Devuelve la página siguiente o false cuando no existe.
     *
     * @return int|false
     */
    public function paginaSiguiente()
    {
        $paginaSiguiente = $this->pagina_actual + 1;

        if (
            $this->totalPaginas() === 0
            || $paginaSiguiente > $this->totalPaginas()
        ) {
            return false;
        }

        return $paginaSiguiente;
    }

    /**
     * Genera el enlace para regresar a la página anterior.
     */
    public function enlaceAnterior(): string
    {
        $paginaAnterior = $this->paginaAnterior();

        if ($paginaAnterior === false) {
            return '';
        }

        return sprintf(
            '<a class="paginacion__enlace paginacion__enlace--texto" href="?page=%d%s">&laquo; Anterior</a>',
            $paginaAnterior,
            $this->extraQuery
        );
    }

    /**
     * Genera el enlace para avanzar a la página siguiente.
     */
    public function enlaceSiguiente(): string
    {
        $paginaSiguiente = $this->paginaSiguiente();

        if ($paginaSiguiente === false) {
            return '';
        }

        return sprintf(
            '<a class="paginacion__enlace paginacion__enlace--texto" href="?page=%d%s">Siguiente &raquo;</a>',
            $paginaSiguiente,
            $this->extraQuery
        );
    }

    /**
     * Genera los enlaces numéricos e identifica la página actual.
     */
    public function numerosPagina(): string
    {
        $html = '';

        for (
            $pagina = 1;
            $pagina <= $this->totalPaginas();
            $pagina++
        ) {
            if ($pagina === $this->pagina_actual) {
                $html .= sprintf(
                    '<span class="paginacion__enlace paginacion__enlace--actual">%d</span>',
                    $pagina
                );

                continue;
            }

            $html .= sprintf(
                '<a class="paginacion__enlace paginacion__enlace--numero" href="?page=%d%s">%d</a>',
                $pagina,
                $this->extraQuery,
                $pagina
            );
        }

        return $html;
    }

    /**
     * Construye el componente completo de paginación.
     *
     * No se muestra cuando no hay registros o cuando todos los
     * registros caben en una sola página.
     */
    public function paginacion(): string
    {
        if ($this->totalPaginas() <= 1) {
            return '';
        }

        return '<div class="paginacion">'
            . $this->enlaceAnterior()
            . $this->numerosPagina()
            . $this->enlaceSiguiente()
            . '</div>';
    }

    /**
     * Normaliza parámetros adicionales como:
     *
     * busqueda=juan
     * ?busqueda=juan
     * &busqueda=juan
     */
    private function normalizarExtraQuery(
        string $extraQuery
    ): string {
        $extraQuery = ltrim(
            trim($extraQuery),
            '?&'
        );

        if ($extraQuery === '') {
            return '';
        }

        return '&' . $extraQuery;
    }
}