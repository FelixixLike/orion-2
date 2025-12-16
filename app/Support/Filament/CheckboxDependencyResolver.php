<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
declare(strict_types=1);

namespace App\Support\Filament;

/**
 * Resuelve dependencias de un CheckboxList a partir de un delta (prev vs current).
 *
 * @param array<int,string>              $previous   Estado anterior
 * @param array<int,string>              $current    Estado actual
 * @param array<string, array<int,string>> $requires Mapa hijo => [padres...]
 * @return array<int,string>                         Estado final resuelto
 */
final class CheckboxDependencyResolver
{
    public static function resolve(array $previous, array $current, array $requires): array
    {
        // Normaliza a sets para O(1)
        $curSet  = self::toSet($current);
        $prevSet = self::toSet($previous);

        // Delta
        $added   = array_keys(array_diff_key($curSet, $prevSet));
        $removed = array_keys(array_diff_key($prevSet, $curSet));

        // Grafo inverso (padre => [hijos...])
        $dependents = self::invert($requires);

        // 1) Removidos: quitar dependientes recursivamente
        foreach ($removed as $parent) {
            self::removeDependents($curSet, $dependents, $parent);
        }

        // 2) Agregados: añadir requisitos recursivamente
        foreach ($added as $child) {
            self::addRequirements($curSet, $requires, $child);
        }

        // Devuelve como lista sin tocar el orden del usuario (no sort)
        return array_keys($curSet);
    }

    /** @param array<int,string> $list @return array<string,bool> */
    private static function toSet(array $list): array
    {
        $set = [];
        foreach ($list as $k) { $set[$k] = true; }
        return $set;
    }

    /** @param array<string, array<int,string>> $requires @return array<string, array<int,string>> */
    private static function invert(array $requires): array
    {
        $deps = [];
        foreach ($requires as $child => $parents) {
            foreach ($parents as $p) {
                $deps[$p][] = $child;
            }
        }
        return $deps;
    }

    /** @param array<string,bool> $curSet */
    private static function removeDependents(array &$curSet, array $dependents, string $parent): void
    {
        if (empty($dependents[$parent])) return;

        $queue = $dependents[$parent];
        while ($queue) {
            $child = array_pop($queue);
            if (isset($curSet[$child])) {
                unset($curSet[$child]);
                if (!empty($dependents[$child])) {
                    foreach ($dependents[$child] as $gChild) {
                        $queue[] = $gChild;
                    }
                }
            }
        }
    }

    /**
     * @param array<string,bool> $curSet
     * @param-out array<string,bool> $curSet
     * @param array<string, array<int,string>> $requires
     */
    private static function addRequirements(array &$curSet, array $requires, string $child): void
    {
        if (empty($requires[$child])) return;

        $stack = [$child];
        $seen  = [];
        while ($stack) {
            $node = array_pop($stack);
            if (isset($seen[$node])) continue;
            $seen[$node] = true;

            foreach ($requires[$node] ?? [] as $parent) {
                $curSet[$parent] = true;
                // Un padre podría tener sus propios requisitos
                $stack[] = $parent;
            }
        }
    }
}

