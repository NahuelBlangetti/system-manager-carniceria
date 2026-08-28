<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Scale\ScaleReading;
use App\Services\Scale\ScaleReadingStore;
use App\Services\ScaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cubre el camino de lectura sin tocar hardware: se siembra en el store lo
 * que habría publicado el daemon y se verifica que el servicio y el endpoint
 * lo usen en vez de abrir una conexión TCP.
 *
 * Todos los tests siembran un latido para cada balanza configurada. Si no lo
 * hicieran, el servicio caería al camino directo e intentaría conectarse a
 * las IP reales de la configuración, lo que volvería la suite lenta y
 * dependiente de que la balanza esté o no en la red.
 */
class ScaleReadingTest extends TestCase
{
    use RefreshDatabase;

    private ScaleReadingStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = app(ScaleReadingStore::class);
    }

    public function test_the_watcher_heartbeat_is_tracked_per_scale(): void
    {
        $this->store->heartbeat('main');

        $this->assertTrue($this->store->watcherIsAlive('main'));

        // Vigilar una balanza no puede hacer que la otra parezca vigilada, o
        // nunca se intentaría leerla por el camino directo.
        $this->assertFalse($this->store->watcherIsAlive('scale_2'));
    }

    public function test_it_serves_the_reading_published_by_the_watcher(): void
    {
        $this->watch('main');
        $this->store->put('main', ScaleReading::success(1.25, '125', 'kg', stable: true));

        $result = app(ScaleService::class)->readWeight('main');

        $this->assertTrue($result['success']);
        $this->assertSame(1.25, $result['weight']);
        $this->assertTrue($result['stable']);
    }

    public function test_it_refuses_to_serve_a_stale_reading(): void
    {
        $this->watch('main');

        // El vigilante late pero la última lectura quedó vieja: perdió la
        // balanza. Devolver ese peso congelado sería peor que fallar, porque
        // se cobraría un peso que ya no está en el plato.
        $this->store->put('main', ScaleReading::success(
            1.25,
            '125',
            'kg',
            readAt: microtime(true) - 30,
        ));

        $result = app(ScaleService::class)->readWeight('main');

        $this->assertFalse($result['success']);
    }

    public function test_it_propagates_the_reason_the_watcher_reported(): void
    {
        $this->watch('main');
        $this->store->put('main', ScaleReading::failure(
            'No fue posible conectar con la balanza.',
            readAt: microtime(true) - 30,
        ));

        $result = app(ScaleService::class)->readWeight('main');

        $this->assertFalse($result['success']);
        $this->assertSame('No fue posible conectar con la balanza.', $result['message']);
    }

    public function test_an_unknown_connection_fails_without_touching_the_network(): void
    {
        $result = app(ScaleService::class)->readWeight('no_existe');

        $this->assertFalse($result['success']);
        $this->assertSame('Balanza no configurada.', $result['message']);
    }

    public function test_the_weights_endpoint_returns_every_configured_scale(): void
    {
        $this->watchAll();
        $this->store->put('main', ScaleReading::success(1.25, '125', 'kg', stable: true));
        $this->store->put('scale_2', ScaleReading::failure('No fue posible conectar con la balanza.'));

        $response = $this->actingAs(User::factory()->create())
            ->getJson('/scale/weights');

        // 200 aunque una balanza falle: el request se atendió bien, y un 503
        // haría que el cliente descarte también la lectura de la que sí anda.
        $response->assertOk()
            ->assertJsonPath('scales.main.success', true)
            ->assertJsonPath('scales.main.weight', 1.25)
            ->assertJsonPath('scales.main.watched', true)
            ->assertJsonPath('scales.scale_2.success', false);
    }

    public function test_the_weights_endpoint_ignores_connections_that_are_not_configured(): void
    {
        $this->watchAll();
        $this->store->put('main', ScaleReading::success(1.25, '125', 'kg'));

        $response = $this->actingAs(User::factory()->create())
            ->getJson('/scale/weights?connections=main,cache.default,../../etc/passwd');

        $response->assertOk()
            ->assertJsonPath('scales.main.success', true)
            ->assertJsonMissingPath('scales.cache\.default')
            ->assertJsonCount(1, 'scales');
    }

    public function test_the_weights_endpoint_requires_authentication(): void
    {
        $this->getJson('/scale/weights')->assertUnauthorized();
    }

    /** Simula que hay un daemon vigilando esa balanza. */
    private function watch(string $connection): void
    {
        $this->store->heartbeat($connection);
    }

    private function watchAll(): void
    {
        foreach (array_keys(config('scale.connections')) as $connection) {
            $this->watch($connection);
        }
    }
}
