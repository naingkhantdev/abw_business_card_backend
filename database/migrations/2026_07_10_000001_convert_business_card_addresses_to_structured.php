<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convert business_cards.addresses entries from free-text strings to
     * structured objects: {street, city, state, postal_code, country}.
     * Legacy text is preserved in `street`.
     */
    public function up(): void
    {
        DB::table('business_cards')->orderBy('id')->chunkById(100, function ($cards) {
            foreach ($cards as $card) {
                $addresses = json_decode($card->addresses ?? '[]', true);

                if (!is_array($addresses) || $addresses === []) {
                    continue;
                }

                $changed = false;
                $converted = [];

                foreach ($addresses as $address) {
                    if (is_string($address)) {
                        $converted[] = [
                            'street' => $address,
                            'city' => null,
                            'state' => null,
                            'postal_code' => null,
                            'country' => null,
                        ];
                        $changed = true;
                    } elseif (is_array($address)) {
                        $converted[] = [
                            'street' => $address['street'] ?? null,
                            'city' => $address['city'] ?? null,
                            'state' => $address['state'] ?? null,
                            'postal_code' => $address['postal_code'] ?? null,
                            'country' => $address['country'] ?? null,
                        ];
                    }
                }

                if ($changed) {
                    DB::table('business_cards')
                        ->where('id', $card->id)
                        ->update(['addresses' => json_encode($converted)]);
                }
            }
        });
    }

    public function down(): void
    {
        DB::table('business_cards')->orderBy('id')->chunkById(100, function ($cards) {
            foreach ($cards as $card) {
                $addresses = json_decode($card->addresses ?? '[]', true);

                if (!is_array($addresses) || $addresses === []) {
                    continue;
                }

                $flattened = [];
                foreach ($addresses as $address) {
                    if (is_array($address)) {
                        $parts = array_filter([
                            $address['street'] ?? null,
                            $address['city'] ?? null,
                            $address['state'] ?? null,
                            $address['postal_code'] ?? null,
                            $address['country'] ?? null,
                        ], fn ($part) => is_string($part) && trim($part) !== '');
                        $flattened[] = implode(', ', $parts);
                    } elseif (is_string($address)) {
                        $flattened[] = $address;
                    }
                }

                DB::table('business_cards')
                    ->where('id', $card->id)
                    ->update(['addresses' => json_encode($flattened)]);
            }
        });
    }
};
