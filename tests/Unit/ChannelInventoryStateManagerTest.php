<?php

namespace Tests\Unit;

use App\Exceptions\StateTransitionException;
use App\Models\Channel;
use App\Models\InventorySource;
use App\Services\ChannelInventoryStateManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelInventoryStateManagerTest extends TestCase
{
    use RefreshDatabase;

    protected ChannelInventoryStateManager $stateManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stateManager = new ChannelInventoryStateManager();
    }

    public function test_sync_sources_with_simple_id_array(): void
    {
        $channel = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create();
        $source2 = InventorySource::factory()->active()->create();

        $this->stateManager->syncSources($channel, [$source1->id, $source2->id]);

        $channel->load('inventorySources');
        $this->assertCount(2, $channel->inventorySources);

        $primary = $channel->inventorySources->firstWhere('pivot.is_primary', true);
        $this->assertEquals($source1->id, $primary->id);

        $sortOrders = $channel->inventorySources->pluck('pivot.sort_order')->toArray();
        $this->assertEquals([0, 1], $sortOrders);
    }

    public function test_sync_sources_with_detailed_entries(): void
    {
        $channel = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create();
        $source2 = InventorySource::factory()->active()->create();
        $source3 = InventorySource::factory()->active()->create();

        $this->stateManager->syncSources($channel, [
            ['id' => $source3->id, 'is_primary' => true, 'sort_order' => 5],
            ['id' => $source1->id, 'is_primary' => false, 'sort_order' => 3],
            ['id' => $source2->id, 'sort_order' => 4],
        ]);

        $channel->load('inventorySources');
        $this->assertCount(3, $channel->inventorySources);

        $ids = $channel->inventorySources->pluck('id')->toArray();
        $this->assertEquals([$source1->id, $source2->id, $source3->id], $ids);

        $primary = $channel->inventorySources->firstWhere('pivot.is_primary', true);
        $this->assertEquals($source3->id, $primary->id);
    }

    public function test_sync_sources_filters_out_inactive_sources(): void
    {
        $channel = Channel::factory()->create();
        $active = InventorySource::factory()->active()->create();
        $inactive = InventorySource::factory()->inactive()->create();

        $this->stateManager->syncSources($channel, [$active->id, $inactive->id]);

        $channel->load('inventorySources');
        $this->assertCount(1, $channel->inventorySources);
        $this->assertEquals($active->id, $channel->inventorySources->first()->id);
    }

    public function test_sync_sources_empty_array_clears_all_bindings(): void
    {
        $channel = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create();
        $source2 = InventorySource::factory()->active()->create();
        $channel->syncInventorySources([$source1->id, $source2->id]);
        $this->assertCount(2, $channel->fresh()->inventorySources);

        $this->stateManager->syncSources($channel, []);

        $this->assertCount(0, $channel->fresh()->inventorySources);
    }

    public function test_sync_sources_auto_sets_first_entry_as_primary_when_none_specified(): void
    {
        $channel = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create();
        $source2 = InventorySource::factory()->active()->create();

        $this->stateManager->syncSources($channel, [
            ['id' => $source2->id, 'sort_order' => 1],
            ['id' => $source1->id, 'sort_order' => 0],
        ]);

        $channel->load('inventorySources');
        $primary = $channel->inventorySources->firstWhere('pivot.is_primary', true);
        $this->assertEquals($source1->id, $primary->id);
    }

    public function test_sync_sources_ensures_only_one_primary(): void
    {
        $channel = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create();
        $source2 = InventorySource::factory()->active()->create();
        $source3 = InventorySource::factory()->active()->create();

        $this->stateManager->syncSources($channel, [
            ['id' => $source1->id, 'is_primary' => true, 'sort_order' => 0],
            ['id' => $source2->id, 'is_primary' => true, 'sort_order' => 1],
            ['id' => $source3->id, 'is_primary' => true, 'sort_order' => 2],
        ]);

        $channel->load('inventorySources');
        $primaryCount = $channel->inventorySources->where('pivot.is_primary', true)->count();
        $this->assertEquals(1, $primaryCount);

        $primary = $channel->inventorySources->firstWhere('pivot.is_primary', true);
        $this->assertEquals($source1->id, $primary->id);
    }

    public function test_sync_sources_throws_exception_when_entry_missing_id(): void
    {
        $channel = Channel::factory()->create();

        $this->expectException(StateTransitionException::class);
        $this->expectExceptionMessage('Each inventory source entry must contain an "id" field.');

        $this->stateManager->syncSources($channel, [
            ['name' => 'missing id'],
        ]);
    }

    public function test_sync_sources_exception_contains_entry_index_context(): void
    {
        $channel = Channel::factory()->create();

        try {
            $this->stateManager->syncSources($channel, [
                ['id' => 1],
                ['sort_order' => 0],
            ]);
            $this->fail('Expected StateTransitionException was not thrown.');
        } catch (StateTransitionException $e) {
            $this->assertEquals('INVALID_SOURCE_ENTRY', $e->getErrorCode());
            $this->assertEquals(422, $e->getHttpStatusCode());
            $this->assertEquals(1, $e->getContext()['entry_index']);
        }
    }

    public function test_sync_sources_replaces_existing_bindings(): void
    {
        $channel = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create();
        $source2 = InventorySource::factory()->active()->create();
        $source3 = InventorySource::factory()->active()->create();

        $channel->syncInventorySources([$source1->id, $source2->id]);
        $this->assertCount(2, $channel->fresh()->inventorySources);

        $this->stateManager->syncSources($channel, [$source2->id, $source3->id]);

        $channel->load('inventorySources');
        $this->assertCount(2, $channel->inventorySources);
        $ids = $channel->inventorySources->pluck('id')->toArray();
        $this->assertContains($source2->id, $ids);
        $this->assertContains($source3->id, $ids);
        $this->assertNotContains($source1->id, $ids);
    }

    public function test_handle_source_deactivation_detaches_and_rebuilds(): void
    {
        $channel1 = Channel::factory()->create();
        $channel2 = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create();
        $source2 = InventorySource::factory()->active()->create();
        $deactivatedSource = InventorySource::factory()->active()->create();

        $channel1->syncInventorySources([
            ['id' => $source1->id, 'is_primary' => true, 'sort_order' => 0],
            ['id' => $deactivatedSource->id, 'sort_order' => 1],
            ['id' => $source2->id, 'sort_order' => 2],
        ]);
        $channel2->syncInventorySources([
            ['id' => $deactivatedSource->id, 'is_primary' => true, 'sort_order' => 0],
            ['id' => $source1->id, 'sort_order' => 1],
        ]);

        $this->stateManager->handleSourceDeactivation($deactivatedSource);

        $channel1->load('inventorySources');
        $this->assertCount(2, $channel1->inventorySources);
        $this->assertFalse($channel1->inventorySources->contains($deactivatedSource));
        $sortOrders1 = $channel1->inventorySources->pluck('pivot.sort_order')->toArray();
        $this->assertEquals([0, 1], $sortOrders1);
        $primary1 = $channel1->inventorySources->firstWhere('pivot.is_primary', true);
        $this->assertEquals($source1->id, $primary1->id);

        $channel2->load('inventorySources');
        $this->assertCount(1, $channel2->inventorySources);
        $this->assertFalse($channel2->inventorySources->contains($deactivatedSource));
        $primary2 = $channel2->inventorySources->firstWhere('pivot.is_primary', true);
        $this->assertEquals($source1->id, $primary2->id);
    }

    public function test_handle_source_deactivation_with_no_bound_channels(): void
    {
        $source = InventorySource::factory()->active()->create();

        $this->expectNotToPerformAssertions();
        $this->stateManager->handleSourceDeactivation($source);
    }

    public function test_remove_inactive_and_rebuild_removes_inactive_sources(): void
    {
        $channel = Channel::factory()->create();
        $active1 = InventorySource::factory()->active()->create();
        $inactive = InventorySource::factory()->inactive()->create();
        $active2 = InventorySource::factory()->active()->create();

        \DB::table('channel_inventory_source')->insert([
            ['channel_id' => $channel->id, 'inventory_source_id' => $active1->id, 'sort_order' => 0, 'is_primary' => true],
            ['channel_id' => $channel->id, 'inventory_source_id' => $inactive->id, 'sort_order' => 1, 'is_primary' => false],
            ['channel_id' => $channel->id, 'inventory_source_id' => $active2->id, 'sort_order' => 2, 'is_primary' => false],
        ]);

        $this->stateManager->removeInactiveAndRebuild($channel);

        $channel->load('inventorySources');
        $this->assertCount(2, $channel->inventorySources);
        $this->assertFalse($channel->inventorySources->contains($inactive));

        $sortOrders = $channel->inventorySources->pluck('pivot.sort_order')->toArray();
        $this->assertEquals([0, 1], $sortOrders);
    }

    public function test_remove_inactive_and_rebuild_reassigns_primary_when_primary_was_inactive(): void
    {
        $channel = Channel::factory()->create();
        $inactivePrimary = InventorySource::factory()->inactive()->create();
        $active1 = InventorySource::factory()->active()->create();
        $active2 = InventorySource::factory()->active()->create();

        \DB::table('channel_inventory_source')->insert([
            ['channel_id' => $channel->id, 'inventory_source_id' => $inactivePrimary->id, 'sort_order' => 0, 'is_primary' => true],
            ['channel_id' => $channel->id, 'inventory_source_id' => $active1->id, 'sort_order' => 1, 'is_primary' => false],
            ['channel_id' => $channel->id, 'inventory_source_id' => $active2->id, 'sort_order' => 2, 'is_primary' => false],
        ]);

        $this->stateManager->removeInactiveAndRebuild($channel);

        $channel->load('inventorySources');
        $this->assertCount(2, $channel->inventorySources);

        $primary = $channel->inventorySources->firstWhere('pivot.is_primary', true);
        $this->assertNotNull($primary);
        $this->assertEquals($active1->id, $primary->id);
    }

    public function test_remove_inactive_and_rebuild_when_all_sources_inactive(): void
    {
        $channel = Channel::factory()->create();
        $inactive1 = InventorySource::factory()->inactive()->create();
        $inactive2 = InventorySource::factory()->inactive()->create();

        \DB::table('channel_inventory_source')->insert([
            ['channel_id' => $channel->id, 'inventory_source_id' => $inactive1->id, 'sort_order' => 0, 'is_primary' => true],
            ['channel_id' => $channel->id, 'inventory_source_id' => $inactive2->id, 'sort_order' => 1, 'is_primary' => false],
        ]);

        $this->stateManager->removeInactiveAndRebuild($channel);

        $this->assertCount(0, $channel->fresh()->inventorySources);
    }

    public function test_rebuild_channel_bindings_sorts_correctly(): void
    {
        $channel = Channel::factory()->create();
        $lowPriority = InventorySource::factory()->active()->create(['priority' => 1.00, 'country' => 'US']);
        $highPriority = InventorySource::factory()->active()->create(['priority' => 9.00, 'country' => 'BR']);
        $midPriority = InventorySource::factory()->active()->create(['priority' => 5.00, 'country' => 'CN']);

        \DB::table('channel_inventory_source')->insert([
            ['channel_id' => $channel->id, 'inventory_source_id' => $lowPriority->id, 'sort_order' => 5, 'is_primary' => false],
            ['channel_id' => $channel->id, 'inventory_source_id' => $highPriority->id, 'sort_order' => 10, 'is_primary' => false],
            ['channel_id' => $channel->id, 'inventory_source_id' => $midPriority->id, 'sort_order' => 7, 'is_primary' => false],
        ]);

        $this->stateManager->rebuildChannelBindings($channel);

        $channel->load('inventorySources');
        $sortOrders = $channel->inventorySources->pluck('pivot.sort_order')->toArray();
        $this->assertEquals([0, 1, 2], $sortOrders);

        $ids = $channel->inventorySources->pluck('id')->toArray();
        $this->assertEquals([$lowPriority->id, $midPriority->id, $highPriority->id], $ids);
    }

    public function test_rebuild_channel_bindings_handles_multiple_primaries(): void
    {
        $channel = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create();
        $source2 = InventorySource::factory()->active()->create();
        $source3 = InventorySource::factory()->active()->create();

        \DB::table('channel_inventory_source')->insert([
            ['channel_id' => $channel->id, 'inventory_source_id' => $source1->id, 'sort_order' => 0, 'is_primary' => true],
            ['channel_id' => $channel->id, 'inventory_source_id' => $source2->id, 'sort_order' => 1, 'is_primary' => true],
            ['channel_id' => $channel->id, 'inventory_source_id' => $source3->id, 'sort_order' => 2, 'is_primary' => true],
        ]);

        $this->stateManager->rebuildChannelBindings($channel);

        $channel->load('inventorySources');
        $primaryCount = $channel->inventorySources->where('pivot.is_primary', true)->count();
        $this->assertEquals(1, $primaryCount);

        $primary = $channel->inventorySources->firstWhere('pivot.is_primary', true);
        $this->assertEquals($source1->id, $primary->id);
    }

    public function test_rebuild_channel_bindings_sets_first_as_primary_when_none_exists(): void
    {
        $channel = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create();
        $source2 = InventorySource::factory()->active()->create();

        \DB::table('channel_inventory_source')->insert([
            ['channel_id' => $channel->id, 'inventory_source_id' => $source1->id, 'sort_order' => 0, 'is_primary' => false],
            ['channel_id' => $channel->id, 'inventory_source_id' => $source2->id, 'sort_order' => 1, 'is_primary' => false],
        ]);

        $this->stateManager->rebuildChannelBindings($channel);

        $channel->load('inventorySources');
        $primary = $channel->inventorySources->firstWhere('pivot.is_primary', true);
        $this->assertEquals($source1->id, $primary->id);
    }

    public function test_rebuild_channel_bindings_with_no_sources_does_nothing(): void
    {
        $channel = Channel::factory()->create();

        $this->expectNotToPerformAssertions();
        $this->stateManager->rebuildChannelBindings($channel);
    }

    public function test_sync_inventory_sources_preserving_inactive(): void
    {
        $channel = Channel::factory()->create();
        $active1 = InventorySource::factory()->active()->create();
        $active2 = InventorySource::factory()->active()->create();
        $inactive = InventorySource::factory()->inactive()->create();

        \DB::table('channel_inventory_source')->insert([
            ['channel_id' => $channel->id, 'inventory_source_id' => $inactive->id, 'sort_order' => 0, 'is_primary' => false],
        ]);

        $channel->syncInventorySourcesPreservingInactive([$active1->id, $active2->id]);

        $allBindings = \DB::table('channel_inventory_source')
            ->where('channel_id', $channel->id)
            ->pluck('inventory_source_id')
            ->toArray();

        $this->assertCount(3, $allBindings);
        $this->assertContains($inactive->id, $allBindings);
        $this->assertContains($active1->id, $allBindings);
        $this->assertContains($active2->id, $allBindings);
    }

    public function test_inventory_source_model_deactivation_triggers_state_manager(): void
    {
        $channel = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create();
        $source2 = InventorySource::factory()->active()->create();
        $toDeactivate = InventorySource::factory()->active()->create();

        $channel->syncInventorySources([
            ['id' => $source1->id, 'sort_order' => 0, 'is_primary' => true],
            ['id' => $toDeactivate->id, 'sort_order' => 1],
            ['id' => $source2->id, 'sort_order' => 2],
        ]);

        $toDeactivate->update(['is_active' => false]);

        $channel->load('inventorySources');
        $this->assertCount(2, $channel->inventorySources);
        $this->assertFalse($channel->inventorySources->contains($toDeactivate));

        $sortOrders = $channel->inventorySources->pluck('pivot.sort_order')->toArray();
        $this->assertEquals([0, 1], $sortOrders);
    }

    public function test_inventory_source_model_deletion_triggers_state_manager(): void
    {
        $channel = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create();
        $toDelete = InventorySource::factory()->active()->create();
        $source2 = InventorySource::factory()->active()->create();

        $channel->syncInventorySources([
            ['id' => $source1->id, 'sort_order' => 0, 'is_primary' => true],
            ['id' => $toDelete->id, 'sort_order' => 1],
            ['id' => $source2->id, 'sort_order' => 2],
        ]);

        $toDelete->delete();

        $channel->load('inventorySources');
        $this->assertCount(2, $channel->inventorySources);

        $sortOrders = $channel->inventorySources->pluck('pivot.sort_order')->toArray();
        $this->assertEquals([0, 1], $sortOrders);
    }
}
