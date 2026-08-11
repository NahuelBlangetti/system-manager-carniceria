<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            [
                'name'           => 'Frigorífico San Cayetano S.A.',
                'cuit'           => '30-58423110-7',
                'phone'          => '0800-222-2627',
                'email'          => 'ventas@frigorificosancayetano.com.ar',
                'contact_person' => 'Martín Rodríguez',
                'payment_terms'  => '30_dias',
                'notes'          => 'Media res de novillo y cortes al vacío. Entrega dos veces por semana.',
                'active'         => true,
            ],
            [
                'name'           => 'Granja Avícola Los Pinos',
                'cuit'           => '30-71234567-4',
                'phone'          => '(011) 4567-8900',
                'email'          => 'pedidos@granjalospinos.com.ar',
                'contact_person' => 'Roberto Díaz',
                'payment_terms'  => 'contado',
                'notes'          => 'Pollo entero y trozado, fresco. Entrega diaria en la madrugada.',
                'active'         => true,
            ],
            [
                'name'           => 'Chacinados y Fiambres del Sur S.A.',
                'cuit'           => '30-69812345-1',
                'phone'          => '(0291) 455-7800',
                'email'          => 'ventas@chacinadosdelsur.com.ar',
                'contact_person' => 'Pablo Sánchez',
                'payment_terms'  => '15_dias',
                'notes'          => 'Embutidos, fiambres y achuras. Lista de precios semanal.',
                'active'         => true,
            ],
            [
                'name'           => 'Cerdos del Norte S.R.L.',
                'cuit'           => '30-52345678-2',
                'phone'          => '(011) 5263-4000',
                'email'          => 'distribuidores@cerdosdelnorte.com.ar',
                'contact_person' => 'Laura Fernández',
                'payment_terms'  => '30_dias',
                'notes'          => 'Media res de cerdo y cortes especiales para parrilla.',
                'active'         => true,
            ],
            [
                'name'           => 'Distribuidora de Insumos Carnicero S.A.',
                'cuit'           => '30-61234512-9',
                'phone'          => '(011) 4890-1234',
                'email'          => 'ventas@insumoscarnicero.com.ar',
                'contact_person' => 'Carlos Méndez',
                'payment_terms'  => '30_dias',
                'notes'          => 'Bolsas de vacío, film, bandejas, etiquetas e insumos de mostrador.',
                'active'         => true,
            ],
        ];

        foreach ($suppliers as $data) {
            Supplier::firstOrCreate(['cuit' => $data['cuit']], $data);
        }
    }
}
