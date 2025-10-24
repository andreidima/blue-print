<?php

namespace Tests\Unit\WooCommerce;

use App\Models\Produs;
use App\Models\ProductSkuAlias;
use App\Services\WooCommerce\Client;
use App\Services\WooCommerce\ProductInventoryService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class ProductInventoryServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('woocommerce.url', 'https://example.test');
        config()->set('woocommerce.consumer_key', 'ck');
        config()->set('woocommerce.consumer_secret', 'cs');
    }

    public function test_it_sends_update_for_primary_and_alias_skus(): void
    {
        $client = Mockery::mock(Client::class);
        $service = new ProductInventoryService($client);

        $produs = new Produs([
            'id' => 42,
            'sku' => 'PRIMARY',
            'cantitate' => 7,
        ]);

        $produs->setRelation('skuAliases', new EloquentCollection([
            new ProductSkuAlias(['sku' => 'ALIAS-1']),
            new ProductSkuAlias(['sku' => 'PRIMARY']),
            new ProductSkuAlias(['sku' => '']),
        ]));

        $client->shouldReceive('updateProductStock')
            ->once()
            ->with('PRIMARY', 7)
            ->andReturnTrue();

        $client->shouldReceive('updateProductStock')
            ->once()
            ->with('ALIAS-1', 7)
            ->andReturnTrue();

        $service->syncStock($produs);
    }

    public function test_it_skips_when_configuration_is_missing(): void
    {
        config()->set('woocommerce.url', null);

        $client = Mockery::mock(Client::class);
        $service = new ProductInventoryService($client);

        $produs = new Produs([
            'id' => 99,
            'sku' => 'PRIMARY',
            'cantitate' => 4,
        ]);

        $client->shouldReceive('updateProductStock')->never();

        $service->syncStock($produs);
    }

    public function test_it_passes_non_negative_quantity_to_client(): void
    {
        $client = Mockery::mock(Client::class);
        $service = new ProductInventoryService($client);

        $produs = new Produs([
            'id' => 101,
            'sku' => 'PRIMARY',
            'cantitate' => -5,
        ]);

        $produs->setRelation('skuAliases', new EloquentCollection());

        $client->shouldReceive('updateProductStock')
            ->once()
            ->with('PRIMARY', 0)
            ->andReturnTrue();

        $service->syncStock($produs);
    }
}
