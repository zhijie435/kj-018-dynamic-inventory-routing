<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\InventorySource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventorySourceApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected User $analyst;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->manager = User::factory()->create(['role' => 'manager']);
        $this->analyst = User::factory()->create(['role' => 'analyst']);
    }

    public function test_unauthenticated_user_cannot_access_inventory_sources_api(): void
    {
        $this->getJson('/api/inventory-sources')->assertStatus(401);
        $this->postJson('/api/inventory-sources', [])->assertStatus(401);
        $this->getJson('/api/inventory-sources/1')->assertStatus(401);
        $this->putJson('/api/inventory-sources/1', [])->assertStatus(401);
        $this->deleteJson('/api/inventory-sources/1')->assertStatus(401);
    }

    public function test_admin_can_list_all_inventory_sources(): void
    {
        InventorySource::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)->getJson('/api/inventory-sources');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_manager_can_list_all_inventory_sources(): void
    {
        InventorySource::factory()->count(2)->create();

        $response = $this->actingAs($this->manager)->getJson('/api/inventory-sources');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function test_analyst_can_list_all_inventory_sources(): void
    {
        InventorySource::factory()->count(5)->create();

        $response = $this->actingAs($this->analyst)->getJson('/api/inventory-sources');

        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data');
    }

    public function test_list_inventory_sources_with_search_filter(): void
    {
        InventorySource::factory()->create(['name' => 'Shanghai Warehouse', 'code' => 'SH_WH']);
        InventorySource::factory()->create(['name' => 'New York Store', 'code' => 'NY_ST']);

        $response = $this->actingAs($this->admin)->getJson('/api/inventory-sources?search=Shanghai');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Shanghai Warehouse');
    }

    public function test_list_inventory_sources_with_type_filter(): void
    {
        InventorySource::factory()->warehouse()->count(2)->create();
        InventorySource::factory()->dropship()->count(3)->create();

        $response = $this->actingAs($this->admin)->getJson('/api/inventory-sources?type=warehouse');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function test_list_inventory_sources_with_active_filter(): void
    {
        InventorySource::factory()->active()->count(3)->create();
        InventorySource::factory()->inactive()->count(2)->create();

        $response = $this->actingAs($this->admin)->getJson('/api/inventory-sources?is_active=0');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function test_admin_can_create_inventory_source(): void
    {
        $payload = [
            'code' => 'TEST_WH',
            'name' => 'Test Warehouse',
            'type' => 'warehouse',
            'country' => 'US',
            'city' => 'Los Angeles',
            'address' => '123 Main St',
            'timezone' => 'America/Los_Angeles',
            'priority' => 8.50,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/inventory-sources', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('data.code', 'TEST_WH');
        $response->assertJsonPath('data.name', 'Test Warehouse');
        $this->assertDatabaseHas('inventory_sources', ['code' => 'TEST_WH']);
    }

    public function test_manager_can_create_inventory_source(): void
    {
        $payload = [
            'code' => 'MGR_WH',
            'name' => 'Manager Warehouse',
            'type' => 'warehouse',
        ];

        $response = $this->actingAs($this->manager)->postJson('/api/inventory-sources', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('inventory_sources', ['code' => 'MGR_WH']);
    }

    public function test_analyst_cannot_create_inventory_source(): void
    {
        $payload = [
            'code' => 'ANALYST_WH',
            'name' => 'Analyst Warehouse',
            'type' => 'warehouse',
        ];

        $response = $this->actingAs($this->analyst)->postJson('/api/inventory-sources', $payload);

        $response->assertStatus(403);
    }

    public function test_create_inventory_source_validation_fails_with_duplicate_code(): void
    {
        InventorySource::factory()->create(['code' => 'DUPLICATE']);

        $payload = [
            'code' => 'DUPLICATE',
            'name' => 'Test',
            'type' => 'warehouse',
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/inventory-sources', $payload);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $response->assertJsonPath('error.errors.code', fn ($errors) => is_array($errors) && count($errors) > 0);
    }

    public function test_create_inventory_source_validation_fails_without_required_fields(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/inventory-sources', []);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $response->assertJsonPath('error.errors.code', fn ($errors) => is_array($errors) && count($errors) > 0);
        $response->assertJsonPath('error.errors.name', fn ($errors) => is_array($errors) && count($errors) > 0);
        $response->assertJsonPath('error.errors.type', fn ($errors) => is_array($errors) && count($errors) > 0);
    }

    public function test_create_inventory_source_validation_fails_with_invalid_priority(): void
    {
        $payload = [
            'code' => 'INVALID_PRIO',
            'name' => 'Invalid Priority',
            'type' => 'warehouse',
            'priority' => 1000,
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/inventory-sources', $payload);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $response->assertJsonPath('error.errors.priority', fn ($errors) => is_array($errors) && count($errors) > 0);
    }

    public function test_admin_can_view_single_inventory_source(): void
    {
        $source = InventorySource::factory()->create();

        $response = $this->actingAs($this->admin)->getJson("/api/inventory-sources/{$source->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $source->id);
    }

    public function test_view_inventory_source_loads_channels(): void
    {
        $source = InventorySource::factory()->active()->create();
        $channel = Channel::factory()->create();
        $channel->syncInventorySources([$source->id]);

        $response = $this->actingAs($this->admin)->getJson("/api/inventory-sources/{$source->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['channels']]);
    }

    public function test_view_nonexistent_inventory_source_returns_404(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/inventory-sources/99999');

        $response->assertStatus(404);
    }

    public function test_admin_can_update_inventory_source(): void
    {
        $source = InventorySource::factory()->create(['name' => 'Old Name']);

        $payload = [
            'code' => $source->code,
            'name' => 'New Name',
            'type' => $source->type,
        ];

        $response = $this->actingAs($this->admin)->putJson("/api/inventory-sources/{$source->id}", $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'New Name');
        $this->assertDatabaseHas('inventory_sources', ['id' => $source->id, 'name' => 'New Name']);
    }

    public function test_manager_can_update_inventory_source(): void
    {
        $source = InventorySource::factory()->create();

        $payload = [
            'code' => $source->code,
            'name' => 'Manager Updated',
            'type' => $source->type,
        ];

        $response = $this->actingAs($this->manager)->putJson("/api/inventory-sources/{$source->id}", $payload);

        $response->assertStatus(200);
    }

    public function test_analyst_cannot_update_inventory_source(): void
    {
        $source = InventorySource::factory()->create();

        $payload = [
            'code' => $source->code,
            'name' => 'Analyst Updated',
            'type' => $source->type,
        ];

        $response = $this->actingAs($this->analyst)->putJson("/api/inventory-sources/{$source->id}", $payload);

        $response->assertStatus(403);
    }

    public function test_update_inventory_source_deactivation_triggers_channel_rebuild(): void
    {
        $channel = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create();
        $source2 = InventorySource::factory()->active()->create();
        $channel->syncInventorySources([
            ['id' => $source1->id, 'is_primary' => true, 'sort_order' => 0],
            ['id' => $source2->id, 'sort_order' => 1],
        ]);

        $payload = [
            'code' => $source1->code,
            'name' => $source1->name,
            'type' => $source1->type,
            'is_active' => false,
        ];

        $this->actingAs($this->admin)->putJson("/api/inventory-sources/{$source1->id}", $payload);

        $channel->load('inventorySources');
        $this->assertCount(1, $channel->inventorySources);
        $this->assertEquals($source2->id, $channel->inventorySources->first()->id);

        $primary = $channel->inventorySources->firstWhere('pivot.is_primary', true);
        $this->assertEquals($source2->id, $primary->id);
    }

    public function test_admin_can_delete_inventory_source(): void
    {
        $source = InventorySource::factory()->create();

        $response = $this->actingAs($this->admin)->deleteJson("/api/inventory-sources/{$source->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('inventory_sources', ['id' => $source->id]);
    }

    public function test_manager_cannot_delete_inventory_source(): void
    {
        $source = InventorySource::factory()->create();

        $response = $this->actingAs($this->manager)->deleteJson("/api/inventory-sources/{$source->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('inventory_sources', ['id' => $source->id]);
    }

    public function test_analyst_cannot_delete_inventory_source(): void
    {
        $source = InventorySource::factory()->create();

        $response = $this->actingAs($this->analyst)->deleteJson("/api/inventory-sources/{$source->id}");

        $response->assertStatus(403);
    }

    public function test_deleting_inventory_source_removes_it_from_channels(): void
    {
        $channel = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create();
        $source2 = InventorySource::factory()->active()->create();
        $channel->syncInventorySources([$source1->id, $source2->id]);

        $this->actingAs($this->admin)->deleteJson("/api/inventory-sources/{$source1->id}");

        $channel->load('inventorySources');
        $this->assertCount(1, $channel->inventorySources);
        $this->assertEquals($source2->id, $channel->inventorySources->first()->id);
    }

    public function test_view_channels_for_inventory_source(): void
    {
        $source = InventorySource::factory()->active()->create();
        $channel1 = Channel::factory()->create();
        $channel2 = Channel::factory()->create();
        $channel1->syncInventorySources([$source->id]);
        $channel2->syncInventorySources([$source->id]);

        $response = $this->actingAs($this->admin)->getJson("/api/inventory-sources/{$source->id}/channels");

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function test_analyst_can_view_channels_for_inventory_source(): void
    {
        $source = InventorySource::factory()->create();

        $response = $this->actingAs($this->analyst)->getJson("/api/inventory-sources/{$source->id}/channels");

        $response->assertStatus(200);
    }

    public function test_view_channels_for_inventory_source_without_bindings(): void
    {
        $source = InventorySource::factory()->create();

        $response = $this->actingAs($this->admin)->getJson("/api/inventory-sources/{$source->id}/channels");

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
    }

    public function test_is_primary_for_channel_check(): void
    {
        $channel = Channel::factory()->create();
        $primarySource = InventorySource::factory()->active()->create();
        $otherSource = InventorySource::factory()->active()->create();
        $channel->syncInventorySources([
            ['id' => $primarySource->id, 'is_primary' => true],
            ['id' => $otherSource->id],
        ]);

        $this->assertTrue($primarySource->isPrimaryForChannel($channel->id));
        $this->assertFalse($otherSource->isPrimaryForChannel($channel->id));
    }

    public function test_update_inventory_source_preserves_other_attributes(): void
    {
        $source = InventorySource::factory()->create([
            'code' => 'PRESERVE',
            'name' => 'Original Name',
            'type' => 'warehouse',
            'country' => 'US',
            'city' => 'NYC',
            'priority' => 7.50,
            'is_active' => true,
        ]);

        $payload = [
            'code' => 'PRESERVE',
            'name' => 'Updated Name',
            'type' => 'warehouse',
        ];

        $response = $this->actingAs($this->admin)->putJson("/api/inventory-sources/{$source->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('inventory_sources', [
            'id' => $source->id,
            'name' => 'Updated Name',
            'country' => 'US',
            'city' => 'NYC',
            'priority' => 7.50,
            'is_active' => true,
        ]);
    }
}
