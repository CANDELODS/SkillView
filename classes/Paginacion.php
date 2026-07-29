<?php

declare(strict_types=1);

namespace Classes;

use InvalidArgumentException;

/**
 * Calcula y genera los elementos necesarios para
 * paginar los listados de SkillView.
 */
final class Paginacion
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
        $this->pagina_actual =
            (int) $pagina_actual;

        $this->registros_por_pagina =
            (int) $registros_por_pagina;

        $this->total_registros =
            (int) $total_registros;

        $this->validarConfiguracion();

        $this->extraQuery =
            $this->normalizarExtraQuery(
                (string) $extraQuery
            );
    }

    /**
     * Calcula cuántos registros deben omitirse
     * antes de consultar la página actual.
     */
    public function offset(): int
    {
        return $this->registros_por_pagina
            * ($this->pagina_actual - 1);
    }

    /**
     * Calcula el total de páginas necesarias.
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
     * Devuelve la página anterior o false
     * cuando el usuario está en la primera.
     */
    public function paginaAnterior(): int|false
    {
        $anterior = $this->pagina_actual - 1;

        return $anterior >= 1
            ? $anterior
            : false;
    }

    /**
     * Devuelve la página siguiente o false
     * cuando el usuario está en la última.
     */
    public function paginaSiguiente(): int|false
    {
        $siguiente = $this->pagina_actual + 1;

        return $siguiente <= $this->totalPaginas()
            ? $siguiente
            : false;
    }

    /**
     * Genera el enlace para retroceder.
     */
    public function enlaceAnterior(): string
    {
        $anterior = $this->paginaAnterior();

        if ($anterior === false) {
            return '';
        }

        return
            '<a class="paginacion__enlace '
            . 'paginacion__enlace--texto" '
            . 'href="?page='
            . $anterior
            . $this->extraQuery
            . '">&laquo; Anterior</a>';
    }

    /**
     * Genera el enlace para avanzar.
     */
    public function enlaceSiguiente(): string
    {
        $siguiente = $this->paginaSiguiente();

        if ($siguiente === false) {
            return '';
        }

        return
            '<a class="paginacion__enlace '
            . 'paginacion__enlace--texto" '
            . 'href="?page='
            . $siguiente
            . $this->extraQuery
            . '">Siguiente &raquo;</a>';
    }

    /**
     * Genera los enlaces numéricos de las páginas.
     * La página actual se representa mediante un span.
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
                $html .=
                    '<span class="paginacion__enlace '
                    . 'paginacion__enlace--actual">'
                    . $pagina
                    . '</span>';

                continue;
            }

            $html .=
                '<a class="paginacion__enlace '
                . 'paginacion__enlace--numero" '
                . 'href="?page='
                . $pagina
                . $this->extraQuery
                . '">'
                . $pagina
                . '</a>';
        }

        return $html;
    }

    /**
     * Genera el componente completo únicamente
     * cuando existen al menos dos páginas.
     */
    public function paginacion(): string
    {
        if ($this->totalPaginas() <= 1) {
            return '';
        }

        return
            '<div class="paginacion">'
            . $this->enlaceAnterior()
            . $this->numerosPagina()
            . $this->enlaceSiguiente()
            . '</div>';
    }

    /**
     * Valida los valores utilizados para calcular
     * la paginación.
     */
    private function validarConfiguracion(): void
    {
        if ($this->pagina_actual < 1) {
            throw new InvalidArgumentException(
                'La página actual debe ser mayor '
                . 'o igual a uno.'
            );
        }

        if ($this->registros_por_pagina <= 0) {
            throw new InvalidArgumentException(
                'La cantidad de registros por página '
                . 'debe ser mayor que cero.'
            );
        }

        if ($this->total_registros < 0) {
            throw new InvalidArgumentException(
                'El total de registros no puede ser negativo.'
            );
        }
    }

    /**
     * Convierte valores como:
     *
     * busqueda=ana
     * ?busqueda=ana
     * &busqueda=ana
     *
     * en:
     *
     * &busqueda=ana
     */
    private function normalizarExtraQuery(
        string $extraQuery
    ): string {
        $extraQuery = trim($extraQuery);

        if ($extraQuery === '') {
            return '';
        }

        $extraQuery = ltrim(
            $extraQuery,
            '?&'
        );

        return $extraQuery === ''
            ? ''
            : '&' . $extraQuery;
    }
}