<?php

namespace Tests\Feature;

use App\Models\BusinessCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessCardSearchFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->viewer = User::factory()->create(['is_verified' => true]);
        Sanctum::actingAs($this->viewer);

        $yangonUser = User::factory()->create(['is_verified' => true]);
        BusinessCard::create([
            'user_id' => $yangonUser->id,
            'created_by' => $yangonUser->id,
            'name' => 'Aung Aung',
            'card_type' => 'user_card',
            'addresses' => [
                ['street' => null, 'city' => 'Yangon', 'state' => 'Yangon Region', 'postal_code' => '11181', 'country' => 'Myanmar'],
            ],
        ]);

        $londonUser = User::factory()->create(['is_verified' => true]);
        BusinessCard::create([
            'user_id' => $londonUser->id,
            'created_by' => $londonUser->id,
            'name' => 'John Smith',
            'card_type' => 'user_card',
            'addresses' => [
                ['street' => '55 Baker St', 'city' => 'London', 'state' => null, 'postal_code' => 'NW1 6XE', 'country' => 'United Kingdom'],
            ],
        ]);
    }

    public function test_cards_can_be_filtered_by_country_without_query(): void
    {
        $response = $this->getJson('/api/business-cards/search?country=myanmar');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.full_name', 'Aung Aung');
    }

    public function test_cards_can_be_filtered_by_city(): void
    {
        $response = $this->getJson('/api/business-cards/search?city=london');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.full_name', 'John Smith');
    }

    public function test_query_and_address_filters_combine(): void
    {
        $response = $this->getJson('/api/business-cards/search?query=Aung&country=United Kingdom');

        $response->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_state_filter_matches_partially_and_case_insensitively(): void
    {
        $response = $this->getJson('/api/business-cards/search?state=yangon reg');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.full_name', 'Aung Aung');
    }

    public function test_no_parameters_returns_empty_results(): void
    {
        $response = $this->getJson('/api/business-cards/search');

        $response->assertStatus(200)->assertJsonCount(0, 'data');
    }
}
