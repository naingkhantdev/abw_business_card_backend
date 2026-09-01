<?php

namespace Tests\Feature;

use App\Models\BusinessCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessCardImageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create(['is_verified' => true]);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_card_can_be_created_with_front_and_back_images(): void
    {
        Storage::fake('public');
        $this->actingAsUser();

        $response = $this->postJson('/api/business-cards', [
            'name' => 'John Doe',
            'card_type' => 'saved_card',
            'front_image' => UploadedFile::fake()->image('front.jpg'),
            'back_image' => UploadedFile::fake()->image('back.jpg'),
        ]);

        $response->assertStatus(201);

        $front = $response->json('data.front_image');
        $back = $response->json('data.back_image');

        $this->assertNotNull($front);
        $this->assertNotNull($back);
        $this->assertStringStartsWith('card_images/', $front);
        $this->assertStringStartsWith('card_images/', $back);
        Storage::disk('public')->assertExists($front);
        Storage::disk('public')->assertExists($back);
    }

    public function test_card_images_are_optional(): void
    {
        Storage::fake('public');
        $this->actingAsUser();

        $response = $this->postJson('/api/business-cards', [
            'name' => 'No Photos',
            'card_type' => 'saved_card',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.front_image', null)
            ->assertJsonPath('data.back_image', null);
    }

    /**
     * Dio serialises a null multipart field as an empty string, so that is what
     * the app actually sends for a card with no photo.
     */
    public function test_empty_string_image_fields_are_treated_as_absent(): void
    {
        Storage::fake('public');
        $this->actingAsUser();

        $this->postJson('/api/business-cards', [
            'name' => 'Empty Strings',
            'card_type' => 'saved_card',
            'front_image' => '',
            'back_image' => '',
        ])->assertStatus(201)
            ->assertJsonPath('data.front_image', null)
            ->assertJsonPath('data.back_image', null);
    }

    public function test_non_image_upload_is_rejected(): void
    {
        Storage::fake('public');
        $this->actingAsUser();

        $this->postJson('/api/business-cards', [
            'name' => 'Bad Upload',
            'card_type' => 'saved_card',
            'front_image' => UploadedFile::fake()->create('card.pdf', 20, 'application/pdf'),
        ])->assertStatus(422)
            ->assertJsonValidationErrors('front_image');
    }

    public function test_update_replaces_front_image_and_keeps_back_image(): void
    {
        Storage::fake('public');
        $user = $this->actingAsUser();

        $card = BusinessCard::create([
            'user_id' => $user->id,
            'created_by' => $user->id,
            'name' => 'Jane Doe',
            'card_type' => 'saved_card',
            'front_image' => 'card_images/old-front.jpg',
            'back_image' => 'card_images/old-back.jpg',
        ]);

        $response = $this->postJson("/api/business-cards/{$card->id}", [
            '_method' => 'PUT',
            'front_image' => UploadedFile::fake()->image('new-front.jpg'),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.back_image', 'card_images/old-back.jpg');

        $front = $response->json('data.front_image');
        $this->assertNotSame('card_images/old-front.jpg', $front);
        Storage::disk('public')->assertExists($front);
    }
}
