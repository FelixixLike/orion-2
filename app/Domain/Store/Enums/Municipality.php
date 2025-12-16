<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
declare(strict_types=1);

namespace App\Domain\Store\Enums;

enum Municipality: string
{
    // Valores de ejemplo originales (otras ciudades)
    case BOGOTA = 'bogota';
    case MEDELLIN = 'medellin';
    case CALI = 'cali';
    case BARRANQUILLA = 'barranquilla';
    case BUCARAMANGA = 'bucaramanga';
    case PEREIRA = 'pereira';
    case MANIZALES = 'manizales';
    case CUCUTA = 'cucuta';
    case IBAGUE = 'ibague';
    case CARTAGENA = 'cartagena';

    // Municipios del Meta (referencia principal para la Ubicacion de tiendas)
    case VILLAVICENCIO = 'villavicencio';
    case ACACIAS = 'acacias';
    case BARRANCA_DE_UPIA = 'barranca_de_upia';
    case CABUYARO = 'cabuyaro';
    case CASTILLA_LA_NUEVA = 'castilla_la_nueva';
    case CUBARRAL = 'cubarral';
    case CUMARAL = 'cumaral';
    case EL_CALVARIO = 'el_calvario';
    case EL_CASTILLO = 'el_castillo';
    case EL_DORADO = 'el_dorado';
    case FUENTE_DE_ORO = 'fuente_de_oro';
    case GRANADA = 'granada';
    case GUAMAL = 'guamal';
    case LA_MACARENA = 'la_macarena';
    case LEJANIAS = 'lejanias';
    case MAPIRIPAN = 'mapiripan';
    case MESETAS = 'mesetas';
    case PUERTO_CONCORDIA = 'puerto_concordia';
    case PUERTO_GAITAN = 'puerto_gaitan';
    case PUERTO_LLERAS = 'puerto_lleras';
    case PUERTO_LOPEZ = 'puerto_lopez';
    case PUERTO_RICO = 'puerto_rico_meta';
    case RESTREPO = 'restrepo_meta';
    case SAN_CARLOS_DE_GUAROA = 'san_carlos_de_guaroa';
    case SAN_JUAN_DE_ARAMA = 'san_juan_de_arama';
    case SAN_JUANITO = 'san_juanito';
    case SAN_MARTIN = 'san_martin_meta';
    case URIBE = 'uribe_meta';
    case VISTA_HERMOSA = 'vista_hermosa';

    public function label(): string
    {
        return match ($this) {
            self::BARRANCA_DE_UPIA => 'Barranca de Upía',
            self::CASTILLA_LA_NUEVA => 'Castilla la Nueva',
            self::EL_CALVARIO => 'El Calvario',
            self::EL_CASTILLO => 'El Castillo',
            self::EL_DORADO => 'El Dorado',
            self::FUENTE_DE_ORO => 'Fuente de Oro',
            self::LA_MACARENA => 'La Macarena',
            self::PUERTO_CONCORDIA => 'Puerto Concordia',
            self::PUERTO_GAITAN => 'Puerto Gaitán',
            self::PUERTO_LLERAS => 'Puerto Lleras',
            self::PUERTO_LOPEZ => 'Puerto López',
            self::PUERTO_RICO => 'Puerto Rico',
            self::RESTREPO => 'Restrepo',
            self::SAN_CARLOS_DE_GUAROA => 'San Carlos de Guaroa',
            self::SAN_JUAN_DE_ARAMA => 'San Juan de Arama',
            self::SAN_JUANITO => 'San Juanito',
            self::SAN_MARTIN => 'San Martín',
            self::URIBE => 'Uribe',
            self::VISTA_HERMOSA => 'Vista Hermosa',
            default => ucfirst(str_replace('_', ' ', $this->value)),
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $municipality) => [$municipality->value => $municipality->label()])
            ->toArray();
    }

    /**
     * Opciones pensadas para la ubicacion de tiendas.
     * Solo incluye municipios del Meta (sin ciudades genéricas como Bogota o Medellin).
     *
     * @return array<string, string>
     */
    public static function metaOptions(): array
    {
        $metaCases = [
            self::VILLAVICENCIO,
            self::ACACIAS,
            self::BARRANCA_DE_UPIA,
            self::CABUYARO,
            self::CASTILLA_LA_NUEVA,
            self::CUBARRAL,
            self::CUMARAL,
            self::EL_CALVARIO,
            self::EL_CASTILLO,
            self::EL_DORADO,
            self::FUENTE_DE_ORO,
            self::GRANADA,
            self::GUAMAL,
            self::LA_MACARENA,
            self::LEJANIAS,
            self::MAPIRIPAN,
            self::MESETAS,
            self::PUERTO_CONCORDIA,
            self::PUERTO_GAITAN,
            self::PUERTO_LLERAS,
            self::PUERTO_LOPEZ,
            self::PUERTO_RICO,
            self::RESTREPO,
            self::SAN_CARLOS_DE_GUAROA,
            self::SAN_JUAN_DE_ARAMA,
            self::SAN_JUANITO,
            self::SAN_MARTIN,
            self::URIBE,
            self::VISTA_HERMOSA,
        ];

        return collect($metaCases)
            ->mapWithKeys(fn (self $municipality) => [$municipality->value => $municipality->label()])
            ->toArray();
    }
}
