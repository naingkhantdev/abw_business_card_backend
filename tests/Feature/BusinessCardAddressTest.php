<?php

namespace Tests\Feature;

use App\Models\BusinessCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessCardAddressTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create(['is_verified' => true]);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_card_can_be_created_with_structured_addresses(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/business-cards', [
            'name' => 'John Doe',
            'card_type' => 'saved_card',
            'addresses' => [
                [
                    'street' => '123 Main St',
                    'city' => 'Yangon',
                    'state' => 'Yangon Region',
                    'postal_code' => '11181',
                    'country' => 'Myanmar',
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.addresses.0.street', '123 Main St')
            ->assertJsonPath('data.addresses.0.city', 'Yangon')
            ->assertJsonPath('data.addresses.0.state', 'Yangon Region')
            ->assertJsonPath('data.addresses.0.postal_code', '11181')
            ->assertJsonPath('data.addresses.0.country', 'Myanmar');
    }

    public function test_address_without_city_or_country_is_rejected(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/business-cards', [
            'name' => 'John Doe',
            'card_type' => 'saved_card',
            'addresses' => [
                ['street' => '123 Main St'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['addresses.0.city', 'addresses.0.country']);
    }

    public function test_state_and_postal_code_are_optional(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/business-cards', [
            'name' => 'John Doe',
            'card_type' => 'saved_card',
            'addresses' => [
                ['city' => 'Dublin', 'country' => 'Ireland'],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.addresses.0.city', 'Dublin')
            ->assertJsonPath('data.addresses.0.country', 'Ireland')
            ->assertJsonPath('data.addresses.0.state', null)
            ->assertJsonPath('data.addresses.0.postal_code', null);
    }

    public function test_addresses_can_be_updated(): void
    {
        $user = $this->actingAsUser();

        $card = BusinessCard::create([
            'user_id' => $user->id,
            'created_by' => $user->id,
            'name' => 'John Doe',
            'card_type' => 'saved_card',
            'addresses' => [
                ['street' => null, 'city' => 'Yangon', 'state' => null, 'postal_code' => null, 'country' => 'Myanmar'],
            ],
        ]);

        $response = $this->putJson("/api/business-cards/{$card->id}", [
            'addresses' => [
                [
                    'street' => '55 Baker St',
                    'city' => 'London',
                    'state' => null,
                    'postal_code' => 'NW1 6XE',
                    'country' => 'United Kingdom',
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.addresses.0.city', 'London')
            ->assertJsonPath('data.addresses.0.postal_code', 'NW1 6XE')
            ->assertJsonPath('data.addresses.0.country', 'United Kingdom');
    }

    public function test_legacy_string_addresses_are_normalized_in_responses(): void
    {
        $user = $this->actingAsUser();

        $card = BusinessCard::create([
            'user_id' => $user->id,
            'created_by' => $user->id,
            'name' => 'Legacy Card',
            'card_type' => 'saved_card',
            'addresses' => ['No 12, Old Town Road'],
        ]);

        $response = $this->getJson("/api/business-cards/{$card->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.addresses.0.street', 'No 12, Old Town Road')
            ->assertJsonPath('data.addresses.0.city', null)
            ->assertJsonPath('data.addresses.0.country', null);
    }
}
