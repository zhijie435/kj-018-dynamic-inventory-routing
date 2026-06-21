<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\InventorySource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelApiTest extends TestCase
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

    public function test_unauthenticated_user_cannot_access_channels_api(): void
    {
        $this->getJson('/api/channels')->assertStatus(401);
        $this->postJson('/api/channels', [])->assertStatus(401);
        $this->getJson('/api/channels/1')->assertStatus(401);
        $this->putJson('/api/channels/1', [])->assertStatus(401);
        $this->deleteJson('/api/channels/1')->assertStatus(401);
    }

    public function test_admin_can_list_all_channels(): void
    {
        Channel::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)->getJson('/api/channels');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_manager_can_list_all_channels(): void
    {
        Channel::factory()->count(2)->create();

        $response = $this->actingAs($this->manager)->getJson('/api/channels');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function test_analyst_can_list_all_channels(): void
    {
        Channel::factory()->count(5)->create();

        $response = $this->actingAs($this->analyst)->getJson('/api/channels');

        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data');
    }

    public function test_list_channels_with_search_filter(): void
    {
        Channel::factory()->create(['name' => 'Alpha Channel', 'code' => 'ALPHA']);
        Channel::factory()->create(['name' => 'Beta Channel', 'code' => 'BETA']);
        Channel::factory()->create(['name' => 'Gamma Channel', 'code' => 'GAMMA']);

        $response = $this->actingAs($this->admin)->getJson('/api/channels?search=Alpha');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Alpha Channel');
    }

    public function test_list_channels_with_region_filter(): void
    {
        Channel::factory()->us()->create();
        Channel::factory()->br()->create();

        $response = $this->actingAs($this->admin)->getJson('/api/channels?region=US');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.region', 'US');
    }

    public function test_list_channels_with_active_filter(): void
    {
        Channel::factory()->active()->count(2)->create();
        Channel::factory()->inactive()->count(3)->create();

        $response = $this->actingAs($this->admin)->getJson('/api/channels?is_active=1');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function test_admin_can_create_channel(): void
    {
        $payload = [
            'code' => 'TEST_CHANNEL',
            'name' => 'Test Channel',
            'region' => 'US',
            'currency' => 'USD',
            'locale' => 'en_US',
            'description' => 'A test channel',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/channels', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('data.code', 'TEST_CHANNEL');
        $response->assertJsonPath('data.name', 'Test Channel');
        $this->assertDatabaseHas('channels', ['code' => 'TEST_CHANNEL']);
    }

    public function test_manager_can_create_channel(): void
    {
        $payload = [
            'code' => 'MGR_CHANNEL',
            'name' => 'Manager Channel',
            'currency' => 'EUR',
            'locale' => 'de_DE',
        ];

        $response = $this->actingAs($this->manager)->postJson('/api/channels', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('channels', ['code' => 'MGR_CHANNEL']);
    }

    public function test_analyst_cannot_create_channel(): void
    {
        $payload = [
            'code' => 'ANALYST_CHANNEL',
            'name' => 'Analyst Channel',
            'currency' => 'USD',
            'locale' => 'en_US',
        ];

        $response = $this->actingAs($this->analyst)->postJson('/api/channels', $payload);

        $response->assertStatus(403);
    }

    public function test_create_channel_with_inventory_sources(): void
    {
        $source1 = InventorySource::factory()->active()->create();
        $source2 = InventorySource::factory()->active()->create();

        $payload = [
            'code' => 'CHANNEL_WITH_SOURCES',
            'name' => 'Channel With Sources',
            'currency' => 'USD',
            'locale' => 'en_US',
            'inventory_source_ids' => [
                ['id' => $source1->id, 'is_primary' => true, 'sort_order' => 0],
                ['id' => $source2->id, 'sort_order' => 1],
            ],
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/channels', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('channel_inventory_source', [
            'channel_id' => $response->json('data.id'),
            'inventory_source_id' => $source1->id,
            'is_primary' => true,
        ]);
    }

    public function test_create_channel_validation_fails_with_duplicate_code(): void
    {
        Channel::factory()->create(['code' => 'DUPLICATE']);

        $payload = [
            'code' => 'DUPLICATE',
            'name' => 'Test',
            'currency' => 'USD',
            'locale' => 'en_US',
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/channels', $payload);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $response->assertJsonPath('error.errors.code', fn ($errors) => is_array($errors) && count($errors) > 0);
    }

    public function test_create_channel_validation_fails_without_required_fields(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/channels', []);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $response->assertJsonPath('error.errors.code', fn ($errors) => is_array($errors) && count($errors) > 0);
        $response->assertJsonPath('error.errors.name', fn ($errors) => is_array($errors) && count($errors) > 0);
        $response->assertJsonPath('error.errors.currency', fn ($errors) => is_array($errors) && count($errors) > 0);
        $response->assertJsonPath('error.errors.locale', fn ($errors) => is_array($errors) && count($errors) > 0);
    }

    public function test_admin_can_view_single_channel(): void
    {
        $channel = Channel::factory()->create();

        $response = $this->actingAs($this->admin)->getJson("/api/channels/{$channel->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $channel->id);
    }

    public function test_view_nonexistent_channel_returns_404(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/channels/99999');

        $response->assertStatus(404);
    }

    public function test_admin_can_update_channel(): void
    {
        $channel = Channel::factory()->create(['name' => 'Old Name']);

        $payload = [
            'code' => $channel->code,
            'name' => 'New Name',
            'currency' => $channel->currency,
            'locale' => $channel->locale,
        ];

        $response = $this->actingAs($this->admin)->putJson("/api/channels/{$channel->id}", $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'New Name');
        $this->assertDatabaseHas('channels', ['id' => $channel->id, 'name' => 'New Name']);
    }

    public function test_manager_can_update_channel(): void
    {
        $channel = Channel::factory()->create();

        $payload = [
            'code' => $channel->code,
            'name' => 'Manager Updated',
            'currency' => $channel->currency,
            'locale' => $channel->locale,
        ];

        $response = $this->actingAs($this->manager)->putJson("/api/channels/{$channel->id}", $payload);

        $response->assertStatus(200);
    }

    public function test_analyst_cannot_update_channel(): void
    {
        $channel = Channel::factory()->create();

        $payload = [
            'code' => $channel->code,
            'name' => 'Analyst Updated',
            'currency' => $channel->currency,
            'locale' => $channel->locale,
        ];

        $response = $this->actingAs($this->analyst)->putJson("/api/channels/{$channel->id}", $payload);

        $response->assertStatus(403);
    }

    public function test_update_channel_with_inventory_sources(): void
    {
        $channel = Channel::factory()->create();
        $oldSource = InventorySource::factory()->active()->create();
        $newSource = InventorySource::factory()->active()->create();
        $channel->syncInventorySources([$oldSource->id]);

        $payload = [
            'code' => $channel->code,
            'name' => $channel->name,
            'currency' => $channel->currency,
            'locale' => $channel->locale,
            'inventory_source_ids' => [
                ['id' => $newSource->id, 'is_primary' => true],
            ],
        ];

        $response = $this->actingAs($this->admin)->putJson("/api/channels/{$channel->id}", $payload);

        $response->assertStatus(200);
        $channel->load('inventorySources');
        $this->assertCount(1, $channel->inventorySources);
        $this->assertEquals($newSource->id, $channel->inventorySources->first()->id);
    }

    public function test_admin_can_delete_channel(): void
    {
        $channel = Channel::factory()->create();

        $response = $this->actingAs($this->admin)->deleteJson("/api/channels/{$channel->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('channels', ['id' => $channel->id]);
    }

    public function test_manager_cannot_delete_channel(): void
    {
        $channel = Channel::factory()->create();

        $response = $this->actingAs($this->manager)->deleteJson("/api/channels/{$channel->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('channels', ['id' => $channel->id]);
    }

    public function test_analyst_cannot_delete_channel(): void
    {
        $channel = Channel::factory()->create();

        $response = $this->actingAs($this->analyst)->deleteJson("/api/channels/{$channel->id}");

        $response->assertStatus(403);
    }

    public function test_view_channel_inventory_sources(): void
    {
        $channel = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create();
        $source2 = InventorySource::factory()->active()->create();
        $inactive = InventorySource::factory()->inactive()->create();
        $channel->syncInventorySources([$source1->id, $source2->id, $inactive->id]);

        $response = $this->actingAs($this->admin)->getJson("/api/channels/{$channel->id}/inventory-sources");

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function test_admin_can_sync_inventory_sources(): void
    {
        $channel = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create();
        $source2 = InventorySource::factory()->active()->create();

        $payload = [
            'inventory_source_ids' => [
                ['id' => $source1->id, 'is_primary' => true, 'sort_order' => 0],
                ['id' => $source2->id, 'sort_order' => 1],
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->postJson("/api/channels/{$channel->id}/inventory-sources/sync", $payload);

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
        $this->assertDatabaseHas('channel_inventory_source', [
            'channel_id' => $channel->id,
            'inventory_source_id' => $source1->id,
            'is_primary' => true,
        ]);
    }

    public function test_manager_can_sync_inventory_sources(): void
    {
        $channel = Channel::factory()->create();
        $source = InventorySource::factory()->active()->create();

        $payload = [
            'inventory_source_ids' => [['id' => $source->id]],
        ];

        $response = $this->actingAs($this->manager)
            ->postJson("/api/channels/{$channel->id}/inventory-sources/sync", $payload);

        $response->assertStatus(200);
    }

    public function test_analyst_cannot_sync_inventory_sources(): void
    {
        $channel = Channel::factory()->create();
        $source = InventorySource::factory()->active()->create();

        $payload = [
            'inventory_source_ids' => [['id' => $source->id]],
        ];

        $response = $this->actingAs($this->analyst)
            ->postJson("/api/channels/{$channel->id}/inventory-sources/sync", $payload);

        $response->assertStatus(403);
    }

    public function test_sync_inventory_sources_validation_fails_with_inactive_source(): void
    {
        $channel = Channel::factory()->create();
        $inactive = InventorySource::factory()->inactive()->create();

        $payload = [
            'inventory_source_ids' => [['id' => $inactive->id]],
        ];

        $response = $this->actingAs($this->admin)
            ->postJson("/api/channels/{$channel->id}/inventory-sources/sync", $payload);

        $response->assertStatus(422);
    }

    public function test_sync_inventory_sources_validation_fails_without_ids(): void
    {
        $channel = Channel::factory()->create();

        $response = $this->actingAs($this->admin)
            ->postJson("/api/channels/{$channel->id}/inventory-sources/sync", []);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $response->assertJsonPath('error.errors.inventory_source_ids', fn ($errors) => is_array($errors) && count($errors) > 0);
    }

    public function test_view_routing_order(): void
    {
        $channel = Channel::factory()->create();
        $source1 = InventorySource::factory()->active()->create();
        $source2 = InventorySource::factory()->active()->create();
        $channel->syncInventorySources([
            ['id' => $source1->id, 'is_primary' => true, 'sort_order' => 0],
            ['id' => $source2->id, 'sort_order' => 1],
        ]);

        $response = $this->actingAs($this->admin)->getJson("/api/channels/{$channel->id}/routing-order");

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.is_primary', true);
    }

    public function test_analyst_can_view_routing_order(): void
    {
        $channel = Channel::factory()->create();

        $response = $this->actingAs($this->analyst)->getJson("/api/channels/{$channel->id}/routing-order");

        $response->assertStatus(200);
    }

    public function test_view_primary_source(): void
    {
        $channel = Channel::factory()->create();
        $primary = InventorySource::factory()->active()->create();
        $other = InventorySource::factory()->active()->create();
        $channel->syncInventorySources([
            ['id' => $primary->id, 'is_primary' => true],
            ['id' => $other->id],
        ]);

        $response = $this->actingAs($this->admin)->getJson("/api/channels/{$channel->id}/primary-source");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $primary->id);
    }

    public function test_view_primary_source_returns_null_when_no_sources(): void
    {
        $channel = Channel::factory()->create();

        $response = $this->actingAs($this->admin)->getJson("/api/channels/{$channel->id}/primary-source");

        $response->assertStatus(200);
        $response->assertJsonPath('data', null);
    }

    public function test_route_source_by_country(): void
    {
        $channel = Channel::factory()->create();
        $usSource = InventorySource::factory()->active()->create(['country' => 'US']);
        $brSource = InventorySource::factory()->active()->create(['country' => 'BR']);
        $channel->syncInventorySources([
            ['id' => $usSource->id, 'is_primary' => true],
            ['id' => $brSource->id],
        ]);

        $payload = ['country' => 'BR'];
        $response = $this->actingAs($this->admin)
            ->postJson("/api/channels/{$channel->id}/route-source", $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $brSource->id);
        $response->assertJsonPath('meta.route_type', 'country_match');
    }

    public function test_route_source_by_preferred_source(): void
    {
        $channel = Channel::factory()->create();
        $primary = InventorySource::factory()->active()->create();
        $preferred = InventorySource::factory()->active()->create();
        $channel->syncInventorySources([
            ['id' => $primary->id, 'is_primary' => true],
            ['id' => $preferred->id],
        ]);

        $payload = ['preferred_source_id' => $preferred->id];
        $response = $this->actingAs($this->admin)
            ->postJson("/api/channels/{$channel->id}/route-source", $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $preferred->id);
        $response->assertJsonPath('meta.route_type', 'preferred_source');
    }

    public function test_route_source_with_cn_moq_fallback(): void
    {
        $channel = Channel::factory()->create();
        $usSource = InventorySource::factory()->active()->create(['country' => 'US']);
        $cnSource = InventorySource::factory()->active()->create(['country' => 'CN']);
        $channel->syncInventorySources([
            ['id' => $usSource->id, 'is_primary' => true],
            ['id' => $cnSource->id],
        ]);

        $payload = ['country' => 'JP'];
        $response = $this->actingAs($this->admin)
            ->postJson("/api/channels/{$channel->id}/route-source", $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $cnSource->id);
        $response->assertJsonPath('meta.route_type', 'cn_moq_fallback');
        $response->assertJsonPath('meta.is_moq_direct', true);
        $response->assertJsonPath('meta.fallback_to_cn', true);
    }

    public function test_route_source_validation_fails_with_invalid_params(): void
    {
        $channel = Channel::factory()->create();

        $payload = ['min_priority' => -1];
        $response = $this->actingAs($this->admin)
            ->postJson("/api/channels/{$channel->id}/route-source", $payload);

        $response->assertStatus(422);
    }

    public function test_analyst_can_route_source(): void
    {
        $channel = Channel::factory()->create();
        $source = InventorySource::factory()->active()->create();
        $channel->syncInventorySources([$source->id]);

        $response = $this->actingAs($this->analyst)
            ->postJson("/api/channels/{$channel->id}/route-source", []);

        $response->assertStatus(200);
    }

    public function test_can_route_returns_true_for_bound_source(): void
    {
        $channel = Channel::factory()->create();
        $source = InventorySource::factory()->active()->create();
        $channel->syncInventorySources([$source->id]);

        $payload = ['inventory_source_id' => $source->id];
        $response = $this->actingAs($this->admin)
            ->postJson("/api/channels/{$channel->id}/can-route", $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('data.can_route', true);
    }

    public function test_can_route_returns_false_for_unbound_source(): void
    {
        $channel = Channel::factory()->create();
        $bound = InventorySource::factory()->active()->create();
        $unbound = InventorySource::factory()->active()->create();
        $channel->syncInventorySources([$bound->id]);

        $payload = ['inventory_source_id' => $unbound->id];
        $response = $this->actingAs($this->admin)
            ->postJson("/api/channels/{$channel->id}/can-route", $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('data.can_route', false);
    }

    public function test_can_route_validation_fails_without_id(): void
    {
        $channel = Channel::factory()->create();

        $response = $this->actingAs($this->admin)
            ->postJson("/api/channels/{$channel->id}/can-route", []);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $response->assertJsonPath('error.errors.inventory_source_id', fn ($errors) => is_array($errors) && count($errors) > 0);
    }
}
