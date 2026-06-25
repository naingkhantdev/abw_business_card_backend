<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\BusinessCard;
use App\Models\Company;
use App\Models\Friendship;

class BusinessCardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create a primary user (YOU)
        $me = User::firstOrCreate(
            ['email' => 'me@example.com'],
            [
                'name' => 'My Account',
                'password' => bcrypt('password'),
                'is_verified' => true,
            ]
        );
        if (!$me->is_verified) {
            $me->forceFill(['is_verified' => true])->save();
        }

        // 2. Create another user (A Friend)
        $friendUser = User::firstOrCreate(
            ['email' => 'friend@example.com'],
            [
                'name' => 'John Doe',
                'password' => bcrypt('password'),
                'is_verified' => true,
            ]
        );
        if (!$friendUser->is_verified) {
            $friendUser->forceFill(['is_verified' => true])->save();
        }

        // 3. Create a Company for context
        $company = Company::create([
            'name' => 'Tech Solutions Ltd',
            'industry' => 'IT',
            'business_type' => 'Software',
            'created_by' => $me->id,
        ]);

        // ==========================================
        // TYPE A: "User Card" (Created by the User themselves)
        // ==========================================
        // This is John's official card. He created it.
        $johnsCard = BusinessCard::create([
            'user_id' => $friendUser->id, // Owned by John
            'created_by' => $friendUser->id,
            'company_id' => $company->id,
            'card_type' => 'user_card',   // It's a real user card
            'position' => 'Senior Developer',
            'phones' => ['+1-555-0100'],
            'emails' => ['john@techsolutions.com'],
            'social_links' => [
                ['platform' => 'linkedin', 'url' => 'https://linkedin.com/in/johndoe']
            ],
            'qr_code_data' => 'user_card_qr_john_doe_123',
        ]);

        // ==========================================
        // TYPE B: "Saved Card" (Manual Entry)
        // ==========================================
        // You met "Alice" at a conference. She doesn't use the app.
        // You type her details manually into your account.
        BusinessCard::create([
            'user_id' => $me->id,        // Owned by YOU (in your list)
            'created_by' => $me->id,
            'card_type' => 'saved_card', // Manual entry
            'position' => 'Marketing Manager',
            'phones' => ['+1-555-0200'],
            'emails' => ['alice@marketing.com'],
            'bio' => 'Met at the Tech Conference 2026',
            'social_links' => [
                ['platform' => 'twitter', 'url' => 'https://x.com/alicemarketing']
            ],
        ]);

        // ==========================================
        // ACTION: Add Friend (Collect User Card)
        // ==========================================
        // You scan John's QR code and add him as a friend.
        // This links YOU to John's EXISTING card.
        Friendship::create([
            'requester_user_id' => $me->id,
            'receiver_user_id' => $friendUser->id,
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);
    }
}
