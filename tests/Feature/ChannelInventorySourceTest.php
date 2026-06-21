<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\InventorySource;
use App\Models\User;
use App\Services\InventoryRoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelInventorySourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_channel_can_bind_inventory_sources()
    {
        $channel = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create();
        $source2 = InventorySource::factory()->active()->create();

        $channel->syncInventorySources([
            ['id' => $source1->id, 'is_primary' => true, 'sort_order' => 0],
            ['id' => $source2->id, 'is_primary' => false, 'sort_order' => 1],
        ]);

        $this->assertCount(2, $channel->inventorySources);
        $this->assertTrue($channel->hasInventorySource($source1->id));
        $this->assertTrue($channel->hasInventorySource($source2->id));
    }

    public function test_sync_inventory_sources_sets_primary_correctly()
    {
        $channel = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create();
        $source2 = InventorySource::factory()->active()->create();
        $source3 = InventorySource::factory()->active()->create();

        $channel->syncInventorySources([
            ['id' => $source2->id, 'is_primary' => true],
            ['id' => $source1->id],
            ['id' => $source3->id],
        ]);

        $channel->load('inventorySources');
        $primarySource = $channel->inventorySources->firstWhere('pivot.is_primary', true);

        $this->assertEquals($source2->id, $primarySource->id);
    }

    public function test_sync_inventory_sources_auto_sets_first_as_primary()
    {
        $channel = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create();
        $source2 = InventorySource::factory()->active()->create();

        $channel->syncInventorySources([$source1->id, $source2->id]);

        $channel->load('inventorySources');
        $primarySource = $channel->inventorySources->firstWhere('pivot.is_primary', true);

        $this->assertEquals($source1->id, $primarySource->id);
    }

    public function test_sync_inventory_sources_only_one_primary()
    {
        $channel = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create();
        $source2 = InventorySource::factory()->active()->create();

        $channel->syncInventorySources([
            ['id' => $source1->id, 'is_primary' => true],
            ['id' => $source2->id, 'is_primary' => true],
        ]);

        $channel->load('inventorySources');
        $primaryCount = $channel->inventorySources->where('pivot.is_primary', true)->count();

        $this->assertEquals(1, $primaryCount);
    }

    public function test_sync_inventory_sources_maintains_sort_order()
    {
        $channel = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create();
        $source2 = InventorySource::factory()->active()->create();
        $source3 = InventorySource::factory()->active()->create();

        $channel->syncInventorySources([
            ['id' => $source3->id, 'sort_order' => 2],
            ['id' => $source1->id, 'sort_order' => 0],
            ['id' => $source2->id, 'sort_order' => 1],
        ]);

        $channel->load('inventorySources');
        $sortedIds = $channel->inventorySources->pluck('id')->toArray();

        $this->assertEquals([$source1->id, $source2->id, $source3->id], $sortedIds);
    }

    public function test_inventory_routing_service_gets_available_sources()
    {
        $channel = Channel::factory()->create();
        $activeSource = InventorySource::factory()->create(['is_active' => true]);
        $inactiveSource = InventorySource::factory()->create(['is_active' => false]);

        $channel->syncInventorySources([$activeSource->id, $inactiveSource->id]);

        $routingService = new InventoryRoutingService();
        $availableSources = $routingService->getAvailableSources($channel);

        $this->assertCount(1, $availableSources);
        $this->assertEquals($activeSource->id, $availableSources->first()->id);
    }

    public function test_inventory_routing_service_gets_primary_source()
    {
        $channel = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create();
        $source2 = InventorySource::factory()->active()->create();

        $channel->syncInventorySources([
            ['id' => $source2->id, 'is_primary' => true],
            ['id' => $source1->id],
        ]);

        $routingService = new InventoryRoutingService();
        $primary = $routingService->getPrimarySource($channel);

        $this->assertEquals($source2->id, $primary->id);
    }

    public function test_inventory_routing_service_routes_by_preferred_source()
    {
        $channel = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create();
        $source2 = InventorySource::factory()->active()->create();

        $channel->syncInventorySources([
            ['id' => $source1->id, 'is_primary' => true],
            ['id' => $source2->id],
        ]);

        $routingService = new InventoryRoutingService();
        $routed = $routingService->getRoutedSource($channel, [
            'preferred_source_id' => $source2->id,
        ]);

        $this->assertEquals($source2->id, $routed->id);
    }

    public function test_inventory_routing_service_routes_by_country()
    {
        $channel = Channel::factory()->create();
        $usSource = InventorySource::factory()->active()->create(['country' => 'US']);
        $brSource = InventorySource::factory()->active()->create(['country' => 'BR']);

        $channel->syncInventorySources([
            ['id' => $usSource->id, 'is_primary' => true],
            ['id' => $brSource->id],
        ]);

        $routingService = new InventoryRoutingService();
        $routed = $routingService->getRoutedSource($channel, [
            'country' => 'BR',
        ]);

        $this->assertEquals($brSource->id, $routed->id);
    }

    public function test_inventory_routing_service_country_takes_priority_over_preferred_source()
    {
        $channel = Channel::factory()->create();
        $usSource = InventorySource::factory()->active()->create(['country' => 'US']);
        $brSource = InventorySource::factory()->active()->create(['country' => 'BR']);

        $channel->syncInventorySources([
            ['id' => $usSource->id, 'is_primary' => true],
            ['id' => $brSource->id],
        ]);

        $routingService = new InventoryRoutingService();
        $routed = $routingService->getRoutedSource($channel, [
            'country' => 'BR',
            'preferred_source_id' => $usSource->id,
        ]);

        $this->assertEquals($brSource->id, $routed->id);
    }

    public function test_inventory_routing_service_falls_back_to_primary()
    {
        $channel = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create(['country' => 'US']);
        $source2 = InventorySource::factory()->active()->create(['country' => 'BR']);

        $channel->syncInventorySources([
            ['id' => $source1->id, 'is_primary' => true],
            ['id' => $source2->id],
        ]);

        $routingService = new InventoryRoutingService();
        $routed = $routingService->getRoutedSource($channel, [
            'country' => 'JP',
        ]);

        $this->assertEquals($source1->id, $routed->id);
    }

    public function test_inventory_routing_service_can_route_check()
    {
        $channel = Channel::factory()->create();
        $boundSource = InventorySource::factory()->active()->create();
        $unboundSource = InventorySource::factory()->active()->create();

        $channel->syncInventorySources([$boundSource->id]);

        $routingService = new InventoryRoutingService();

        $this->assertTrue($routingService->canRouteToSource($channel, $boundSource->id));
        $this->assertFalse($routingService->canRouteToSource($channel, $unboundSource->id));
    }

    public function test_inventory_routing_service_get_routing_order()
    {
        $channel = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create();
        $source2 = InventorySource::factory()->active()->create();
        $source3 = InventorySource::factory()->active()->create();

        $channel->syncInventorySources([
            ['id' => $source2->id, 'sort_order' => 1],
            ['id' => $source1->id, 'sort_order' => 0, 'is_primary' => true],
            ['id' => $source3->id, 'sort_order' => 2],
        ]);

        $routingService = new InventoryRoutingService();
        $order = $routingService->getRoutingOrder($channel);

        $this->assertCount(3, $order);
        $this->assertEquals($source1->id, $order[0]['id']);
        $this->assertTrue($order[0]['is_primary']);
        $this->assertEquals($source2->id, $order[1]['id']);
        $this->assertEquals($source3->id, $order[2]['id']);
    }

    public function test_channel_with_no_sources_returns_null_for_routing()
    {
        $channel = Channel::factory()->create();

        $routingService = new InventoryRoutingService();
        $routed = $routingService->getRoutedSource($channel);

        $this->assertNull($routed);
    }

    public function test_inactive_inventory_source_not_available_for_routing()
    {
        $channel = Channel::factory()->create();
        $inactiveSource = InventorySource::factory()->create(['is_active' => false]);

        $channel->syncInventorySources([$inactiveSource->id]);

        $routingService = new InventoryRoutingService();
        $available = $routingService->getAvailableSources($channel);

        $this->assertCount(0, $available);
    }

    public function test_remove_inactive_sources_recalculates_sort_order_continuously()
    {
        $channel = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create();
        $source2 = InventorySource::factory()->active()->create();
        $source3 = InventorySource::factory()->active()->create();
        $source4 = InventorySource::factory()->active()->create();
        $source5 = InventorySource::factory()->active()->create();

        $channel->syncInventorySources([
            ['id' => $source1->id, 'sort_order' => 0, 'is_primary' => true],
            ['id' => $source2->id, 'sort_order' => 1],
            ['id' => $source3->id, 'sort_order' => 2],
            ['id' => $source4->id, 'sort_order' => 3],
            ['id' => $source5->id, 'sort_order' => 4],
        ]);

        \DB::table('channel_inventory_source')
            ->where('channel_id', $channel->id)
            ->where('inventory_source_id', $source2->id)
            ->delete();
        \DB::table('channel_inventory_source')
            ->where('channel_id', $channel->id)
            ->where('inventory_source_id', $source4->id)
            ->delete();

        $channel->removeInactiveInventorySources();
        $channel = $channel->fresh()->load('inventorySources');

        $this->assertCount(3, $channel->inventorySources);

        $sortOrders = $channel->inventorySources->pluck('pivot.sort_order')->toArray();
        $this->assertEquals([0, 1, 2], $sortOrders);

        $ids = $channel->inventorySources->pluck('id')->toArray();
        $this->assertEquals([$source1->id, $source3->id, $source5->id], $ids);

        $primarySource = $channel->inventorySources->firstWhere('pivot.is_primary', true);
        $this->assertEquals($source1->id, $primarySource->id);
    }

    public function test_remove_inactive_sources_maintains_primary_when_primary_is_active()
    {
        $channel = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create();
        $source2 = InventorySource::factory()->active()->create();
        $source3 = InventorySource::factory()->active()->create();

        $channel->syncInventorySources([
            ['id' => $source1->id, 'sort_order' => 0, 'is_primary' => true],
            ['id' => $source2->id, 'sort_order' => 1],
            ['id' => $source3->id, 'sort_order' => 2],
        ]);

        \DB::table('channel_inventory_source')
            ->where('channel_id', $channel->id)
            ->where('inventory_source_id', $source2->id)
            ->delete();

        $channel->removeInactiveInventorySources();
        $channel = $channel->fresh()->load('inventorySources');

        $primarySource = $channel->inventorySources->firstWhere('pivot.is_primary', true);
        $this->assertEquals($source1->id, $primarySource->id);

        $sortOrders = $channel->inventorySources->pluck('pivot.sort_order')->toArray();
        $this->assertEquals([0, 1], $sortOrders);
    }

    public function test_remove_inactive_sources_sets_new_primary_when_primary_was_removed()
    {
        $channel = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create();
        $source2 = InventorySource::factory()->active()->create();
        $source3 = InventorySource::factory()->active()->create();

        $channel->syncInventorySources([
            ['id' => $source1->id, 'sort_order' => 0, 'is_primary' => true],
            ['id' => $source2->id, 'sort_order' => 1],
            ['id' => $source3->id, 'sort_order' => 2],
        ]);

        \DB::table('channel_inventory_source')
            ->where('channel_id', $channel->id)
            ->where('inventory_source_id', $source1->id)
            ->delete();

        $channel->removeInactiveInventorySources();
        $channel = $channel->fresh()->load('inventorySources');

        $this->assertCount(2, $channel->inventorySources);

        $primarySource = $channel->inventorySources->firstWhere('pivot.is_primary', true);
        $this->assertEquals($source2->id, $primarySource->id);

        $sortOrders = $channel->inventorySources->pluck('pivot.sort_order')->toArray();
        $this->assertEquals([0, 1], $sortOrders);
    }

    public function test_inventory_source_deactivation_recalculates_sort_order()
    {
        $channel = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create();
        $source2 = InventorySource::factory()->active()->create();
        $source3 = InventorySource::factory()->active()->create();
        $source4 = InventorySource::factory()->active()->create();
        $source5 = InventorySource::factory()->active()->create();

        $channel->syncInventorySources([
            ['id' => $source1->id, 'sort_order' => 0, 'is_primary' => true],
            ['id' => $source2->id, 'sort_order' => 1],
            ['id' => $source3->id, 'sort_order' => 2],
            ['id' => $source4->id, 'sort_order' => 3],
            ['id' => $source5->id, 'sort_order' => 4],
        ]);

        $source2->update(['is_active' => false]);
        $source4->update(['is_active' => false]);

        $channel = $channel->fresh()->load('inventorySources');

        $this->assertCount(3, $channel->inventorySources);

        $sortOrders = $channel->inventorySources->pluck('pivot.sort_order')->toArray();
        $this->assertEquals([0, 1, 2], $sortOrders);

        $ids = $channel->inventorySources->pluck('id')->toArray();
        $this->assertEquals([$source1->id, $source3->id, $source5->id], $ids);
    }

    public function test_inventory_source_deactivation_maintains_primary_when_primary_is_active()
    {
        $channel = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create();
        $source2 = InventorySource::factory()->active()->create();
        $source3 = InventorySource::factory()->active()->create();

        $channel->syncInventorySources([
            ['id' => $source1->id, 'sort_order' => 0, 'is_primary' => true],
            ['id' => $source2->id, 'sort_order' => 1],
            ['id' => $source3->id, 'sort_order' => 2],
        ]);

        $source2->update(['is_active' => false]);

        $channel = $channel->fresh()->load('inventorySources');

        $primarySource = $channel->inventorySources->firstWhere('pivot.is_primary', true);
        $this->assertEquals($source1->id, $primarySource->id);

        $sortOrders = $channel->inventorySources->pluck('pivot.sort_order')->toArray();
        $this->assertEquals([0, 1], $sortOrders);
    }

    public function test_inventory_source_deactivation_sets_new_primary_when_primary_was_deactivated()
    {
        $channel = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create();
        $source2 = InventorySource::factory()->active()->create();
        $source3 = InventorySource::factory()->active()->create();

        $channel->syncInventorySources([
            ['id' => $source1->id, 'sort_order' => 0, 'is_primary' => true],
            ['id' => $source2->id, 'sort_order' => 1],
            ['id' => $source3->id, 'sort_order' => 2],
        ]);

        $source1->update(['is_active' => false]);

        $channel = $channel->fresh()->load('inventorySources');

        $this->assertCount(2, $channel->inventorySources);

        $primarySource = $channel->inventorySources->firstWhere('pivot.is_primary', true);
        $this->assertEquals($source2->id, $primarySource->id);

        $sortOrders = $channel->inventorySources->pluck('pivot.sort_order')->toArray();
        $this->assertEquals([0, 1], $sortOrders);
    }
}
