<?php

namespace App\Support;

/**
 * Lista oficial de productos de la carnicería (nombre, categoría, precio).
 *
 * Fuente única para el seeder (instalación nueva) y para el comando
 * `products:sync-price-list` (poner en caja una base ya existente, ej.
 * producción). Si cambia un precio o se agrega un producto, se edita acá
 * y los dos quedan al día.
 */
class ButcherPriceList
{
    /** Categorías que se venden envasadas (bolsa/paquete cerrado), no pesadas sueltas. */
    private const PACKAGED_CATEGORIES = ['Otros'];

    /**
     * [categoría, producto, precio, precio por 2kg (null si no aplica)]
     */
    public const PRODUCTS = [
        // ── TERNERA ──────────────────────────────────────────────────────
        ['Ternera', 'Costilla', 22000, null],
        ['Ternera', 'Vacío', 22000, null],
        ['Ternera', 'Matambre', 24000, null],
        ['Ternera', 'Tapa de asado', 23000, null],
        ['Ternera', 'Tapa de nalga', 22000, null],
        ['Ternera', 'Falda', 16000, null],
        ['Ternera', 'Falda especial', 18000, null],
        ['Ternera', 'Entrecot', 18000, null],
        ['Ternera', 'Bocado ancho', 16000, null],
        ['Ternera', 'Bocado fino', 13000, null],
        ['Ternera', 'Costeletas', 16000, null],
        ['Ternera', 'Agujas', 13000, 20000],
        ['Ternera', 'Jamón cuadrado', 20000, null],
        ['Ternera', 'Bola de lomo', 20000, null],
        ['Ternera', 'Nalga', 22000, null],
        ['Ternera', 'Cuadril', 22000, null],
        ['Ternera', 'Peceto', 21000, null],
        ['Ternera', 'Paleta', 16000, null],
        ['Ternera', 'Palomita', 15000, null],
        ['Ternera', 'Tortuguita', 15000, null],

        // ── PREPARADOS Y OTROS ──────────────────────────────────────────
        ['Preparados y otros', 'Molida común', 12000, null],
        ['Preparados y otros', 'Molida especial', 15000, null],
        ['Preparados y otros', 'Osobuco', 12000, null],
        ['Preparados y otros', 'Puchero común', 7000, null],
        ['Preparados y otros', 'Cogotera', 12000, null],
        ['Preparados y otros', 'Milanesas de carne', 15000, 24000],
        ['Preparados y otros', 'Milanesas de pollo', 10000, 18000],
        ['Preparados y otros', 'Milanesas de cerdo', 7000, 13000],
        ['Preparados y otros', 'Milanesas de soja', 11000, null],
        ['Preparados y otros', 'Hamburguesas de carne', 14000, null],
        ['Preparados y otros', 'Hamburguesas de cerdo', 10000, 18000],
        ['Preparados y otros', 'Medallones de espinaca', 12000, null],

        // ── POLLO ────────────────────────────────────────────────────────
        ['Pollo', 'Medallones de pollo', 10500, null],
        ['Pollo', 'Patitas de pollo', 10000, null],
        ['Pollo', 'Pata muslo', 6500, 12000],
        ['Pollo', 'Pollo entero', 6500, null],

        // ── ACHURAS ──────────────────────────────────────────────────────
        ['Achuras', 'Chinchulín', 12000, null],
        ['Achuras', 'Riñón', 12000, null],
        ['Achuras', 'Hígado', 6500, null],
        ['Achuras', 'Mondongo', 11000, null],
        ['Achuras', 'Lengua', 12000, null],
        ['Achuras', 'Morcilla', 12000, null],

        // ── CERDO ────────────────────────────────────────────────────────
        ['Cerdo', 'Chorizo de cerdo premium', 15000, null],
        ['Cerdo', 'Chorizo de cerdo económico', 12000, null],
        ['Cerdo', 'Costilla', 8000, null],
        ['Cerdo', 'Matambre', 14500, null],
        ['Cerdo', 'Entrecot', 10000, null],
        ['Cerdo', 'Vacío', 10000, null],
        ['Cerdo', 'Bocado', 7000, 12000],
        ['Cerdo', 'Agujas', 8000, 15000],
        ['Cerdo', 'Costillitas', 8000, 15000],
        ['Cerdo', 'Osobuco', 5000, null],
        ['Cerdo', 'Pulpa', 8600, null],

        // ── OTROS (se venden por bolsa, no por peso) ────────────────────
        ['Otros', 'Carbón', 4700, null],
        ['Otros', 'Leña', 4500, null],
    ];

    public static function unitFor(string $category): string
    {
        return in_array($category, self::PACKAGED_CATEGORIES, true) ? 'unidad' : 'kg';
    }
}
