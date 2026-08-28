<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * El componente lleva bastante JavaScript embebido en el Blade, donde una
 * llave mal cerrada o una directiva mal escrita no se nota hasta abrir la
 * pantalla. Estos tests solo verifican que compile y que quede apuntado al
 * endpoint correcto.
 */
class ScaleReaderComponentTest extends TestCase
{
    public function test_it_renders_for_a_single_scale(): void
    {
        $view = $this->blade('<x-scale-reader connection="main" label="Mostrador 1" />');

        $view->assertSee('Mostrador 1');
        $view->assertSee('/scale/weights?connections=', false);

        // @js() de un string lo emite entre comillas simples, apto para vivir
        // dentro del atributo x-data sin cerrarlo.
        $view->assertSee("window.scaleHub.readerData('main'", false);
        $view->assertSee('decimals: 2', false);
        $view->assertSee('divisor: 100', false);
    }

    public function test_two_scales_share_a_single_poller(): void
    {
        $rendered = (string) $this->blade(
            '<x-scale-reader connection="main" label="Mostrador 1" />'.
            '<x-scale-reader connection="scale_2" label="Mostrador 2" />'
        );

        $this->assertStringContainsString('Mostrador 1', $rendered);
        $this->assertStringContainsString('Mostrador 2', $rendered);

        // El poller compartido tiene que declararse una sola vez aunque el
        // componente se use dos veces: si se duplicara, habría dos setInterval
        // consultando el mismo endpoint.
        $this->assertSame(1, substr_count($rendered, 'window.scaleHub = window.scaleHub ||'));
    }

    public function test_the_stability_settings_reach_the_client(): void
    {
        config()->set('scale.stability.samples', 6);
        config()->set('scale.stability.tolerance_divisions', 3);

        // Sin daemon la estabilidad se estima en el navegador, así que la
        // configuración tiene que viajar hasta ahí o el fallback usaría
        // valores distintos a los del servidor.
        $rendered = (string) $this->blade('<x-scale-reader connection="main" />');

        $this->assertStringContainsString('stabilitySamples: 6', $rendered);
        $this->assertStringContainsString('toleranceDivisions: 3', $rendered);
    }

    public function test_extra_attributes_reach_the_root_element(): void
    {
        // Así la pantalla de venta puede hacer la tarjeta seleccionable sin
        // que el componente sepa nada de la página que lo usa.
        $rendered = (string) $this->blade(
            '<x-scale-reader connection="main" wire:click="seleccionarBalanza(\'main\')" />'
        );

        $this->assertStringContainsString('seleccionarBalanza', $rendered);
        $this->assertStringContainsString('<button', $rendered);
    }

    public function test_the_selected_scale_is_labeled_in_use(): void
    {
        $selected = (string) $this->blade(
            '<x-scale-reader connection="main" label="Mostrador 1" :selected="true" />'
        );
        $idle = (string) $this->blade(
            '<x-scale-reader connection="main" label="Mostrador 1" :selected="false" />'
        );

        // "En uso" es el único marcador de selección: no puede depender de un
        // borde rojo que además es el color del error.
        $this->assertStringContainsString('En uso', $selected);
        $this->assertStringNotContainsString('En uso', $idle);
    }

    public function test_the_display_decimals_follow_the_scale_divisor(): void
    {
        config()->set('scale.connections.main.divisor', 1000);
        config()->set('scale.connections.main.unit', 'kg');

        // Divisor 1000 son milésimas: el display tiene que pedir 3 decimales
        // o 1,250 kg se vería 1,25 y no coincidiría con la balanza.
        $rendered = (string) $this->blade('<x-scale-reader connection="main" />');

        $this->assertStringContainsString('decimals: 3', $rendered);
        $this->assertStringContainsString('divisor: 1000', $rendered);
        $this->assertStringContainsString('>kg<', $rendered);
        $this->assertStringContainsString('0,000', $rendered);
        $this->assertStringContainsString('scale-lcd', $rendered);
        $this->assertStringContainsString('bg-gray-950', $rendered);
        $this->assertStringContainsString('ring-danger-500', $rendered);
    }
}
