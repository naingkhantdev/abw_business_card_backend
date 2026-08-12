<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessCardResource;
use App\Models\BusinessCard;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str as SupportStr;
use Illuminate\Support\Str;
use App\Services\FcmService;

class BusinessCardController extends Controller
{
    public function index(Request $request)
    {
        $currentUser = auth('sanctum')->user();

        if ($currentUser) {
            // Auto-ensure user has a profile card (user_card)
            $hasProfileCard = BusinessCard::where('user_id', $currentUser->id)
                ->where('card_type', 'user_card')
                ->exists();
            if (!$hasProfileCard) {
                BusinessCard::create([
                    'user_id' => $currentUser->id,
                    'created_by' => $currentUser->id,
                    'name' => $currentUser->name,
                    'position' => 'Member', // Default position
                    'emails' => [$currentUser->email], // Default email
                    'card_type' => 'user_card', // Default is user_card (profile)
                    'qr_code_data' => 'user-' . $currentUser->id . '-' . Str::uuid(), // Auto-generate QR Code
                ]);
            }

            $ownCards = BusinessCard::with(['company.socials', 'user'])
                ->where('user_id', $currentUser->id)
                ->latest()
                ->get()
                ->map(fn (BusinessCard $card) => $this->attachFriendState($card, $currentUser));

            $friendIds = $this->acceptedFriendUserIds($currentUser);

            $friendCards = BusinessCard::with(['company.socials', 'user'])
                ->where(function ($q) {
                    $q->where('card_type', 'user_card')
                      ->orWhereNull('card_type')
                      ->orWhere('card_type', '');
                })
                ->whereIn('user_id', $friendIds)
                ->latest()
                ->get()
                ->map(fn (BusinessCard $card) => $this->attachFriendState($card, $currentUser));

            $cards = $ownCards->concat($friendCards)->unique('id')->values();
        } else {
            $cards = BusinessCard::with(['company.socials', 'user'])->latest()->get();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Business cards fetched successfully',
            'data' => BusinessCardResource::collection($cards),
        ], 200);
    }

    public function show($id)
    {
        $card = BusinessCard::with(['company.socials', 'user'])->find($id);

        if (!$card) {
            return response()->json([
                'status' => 'error',
                'message' => 'Business card not found',
            ], 404);
        }

        $currentUser = auth('sanctum')->user();
        if ($currentUser) {
            $card = $this->attachFriendState($card, $currentUser);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Business card fetched successfully',
            'data' => new BusinessCardResource($card),
        ], 200);
    }

    public function myCards(Request $request)
    {
        $currentUser = $request->user();
        if ($currentUser) {
            // Auto-ensure user has a profile card (user_card)
            $hasProfileCard = BusinessCard::where('user_id', $currentUser->id)
                ->where('card_type', 'user_card')
                ->exists();
            if (!$hasProfileCard) {
                BusinessCard::create([
                    'user_id' => $currentUser->id,
                    'created_by' => $currentUser->id,
                    'name' => $currentUser->name,
                    'position' => 'Member', // Default position
                    'emails' => [$currentUser->email], // Default email
                    'card_type' => 'user_card', // Default is user_card (profile)
                    'qr_code_data' => 'user-' . $currentUser->id . '-' . Str::uuid(), // Auto-generate QR Code
                ]);
            }
        }

        $query = BusinessCard::with(['company.socials', 'user'])
            ->where('user_id', $request->user()->id);

        if ($cardType = $request->input('card_type')) {
            $query->where('card_type', $cardType);

            // For saved_card, strictly require created_by == current user
            if ($cardType === 'saved_card') {
                $query->where('created_by', $request->user()->id);
            }
        }

        $cards = $query->latest()
            ->get()
            ->map(fn (BusinessCard $card) => $this->attachFriendState($card, $request->user()));

        return response()->json([
            'status' => 'success',
            'message' => 'My business cards fetched successfully',
            'data' => BusinessCardResource::collection($cards),
        ], 200);
    }

    public function search(Request $request)
    {
        $query = $request->input('query');
        $companyId = $request->input('company_id');
        $cardType = $request->input('card_type', 'user_card');
        $city = $request->input('city');
        $state = $request->input('state');
        $country = $request->input('country');
        $hasAddressFilter = filled($city) || filled($state) || filled($country);

        if (empty($query) && empty($companyId) && !$hasAddressFilter) {
            return response()->json([
                'status' => 'success',
                'message' => 'No search parameters provided',
                'data' => [],
            ], 200);
        }

        $cards = BusinessCard::with(['company.socials', 'user'])
            ->where(function ($q) use ($cardType) {
                if ($cardType === 'user_card') {
                    $q->where('card_type', 'user_card')
                      ->orWhereNull('card_type')
                      ->orWhere('card_type', '');
                } else {
                    $q->where('card_type', $cardType);
                }
            })
            ->where('user_id', '!=', $request->user()->id)
            ->where(function ($q) use ($query) {
                if (!empty($query)) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('position', 'like', "%{$query}%")
                        ->orWhereHas('user', function ($userQuery) use ($query) {
                            $userQuery->where('name', 'like', "%{$query}%")
                                      ->orWhere('email', 'like', "%{$query}%");
                        });
                }
            })
            ->when($companyId, function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })
            ->latest()
            ->get();

        if ($hasAddressFilter) {
            $cards = $cards
                ->filter(fn (BusinessCard $card) => $this->matchesAddressFilter($card, $city, $state, $country))
                ->values();
        }

        $cards = $cards->map(fn (BusinessCard $card) => $this->attachFriendState($card, $request->user()));

        return response()->json([
            'status' => 'success',
            'message' => 'Search results',
            'data' => BusinessCardResource::collection($cards),
        ], 200);
    }

    /**
     * Case-insensitive partial match against the structured addresses JSON.
     * Filtered in PHP because JSON-path SQL differs between MySQL (prod) and
     * sqlite (tests); revisit with a generated column or address table if the
     * cards dataset grows large.
     */
    private function matchesAddressFilter(BusinessCard $card, ?string $city, ?string $state, ?string $country): bool
    {
        $matches = function (?string $needle, $value): bool {
            if (blank($needle)) {
                return true;
            }
            return is_string($value) && str_contains(mb_strtolower($value), mb_strtolower(trim($needle)));
        };

        return collect($card->addresses ?? [])->contains(function ($address) use ($matches, $city, $state, $country) {
            if (!is_array($address)) {
                return false;
            }

            return $matches($city, $address['city'] ?? null)
                && $matches($state, $address['state'] ?? null)
                && $matches($country, $address['country'] ?? null);
        });
    }

    public function scanQr(Request $request)
    {
        $request->validate([
            'qr_code_data' => 'required|string',
        ]);

        $card = BusinessCard::with(['company.socials', 'user'])
            ->where('qr_code_data', $request->input('qr_code_data'))
            ->first();

        if (!$card) {
            return response()->json([
                'status' => 'error',
                'message' => 'Business card not found for this QR code',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Card found',
            'data' => new BusinessCardResource($this->attachFriendState($card, $request->user())),
        ], 200);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'company_id' => 'nullable|exists:companies,id',
            'position' => 'nullable|string|max:255',
            'phones' => 'nullable|array',
            'phones.*' => 'string|min:6',
            'emails' => 'nullable|array',
            'emails.*' => 'email',
            ...$this->addressRules(),
            'bio' => 'nullable|string',
            'profile_image' => 'nullable',
            'card_type' => 'nullable|string|in:user_card,saved_card',
            'qr_code_data' => 'nullable|string',
            'social_links' => 'nullable|array',
        ]);

        $imagePath = null;
        if ($request->hasFile('profile_image')) {
            $imagePath = $request->file('profile_image')->store('profile_images', 'public');
        } elseif ($request->input('profile_image') && is_string($request->input('profile_image'))) {
            $imagePath = $this->normalizeProfileImagePath($request->input('profile_image'));
        }

        $cardType = $data['card_type'] ?? 'user_card';
        $qrCodeData = $data['qr_code_data'] ?? null;

        if ($cardType === 'user_card' && empty($qrCodeData)) {
            $qrCodeData = 'user-' . $request->user()->id . '-' . Str::uuid();
        }

        $card = BusinessCard::create([
            'user_id' => $request->user()->id,
            'created_by' => $request->user()->id,
            'name' => $data['name'] ?? null,
            'company_id' => $data['company_id'] ?? null,
            'position' => $data['position'] ?? null,
            'phones' => $data['phones'] ?? [],
            'emails' => $data['emails'] ?? [],
            'addresses' => $data['addresses'] ?? [],
            'bio' => $data['bio'] ?? null,
            'card_type' => $cardType,
            'qr_code_data' => $qrCodeData,
            'social_links' => $data['social_links'] ?? [],
            'profile_image' => $imagePath,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Business card created successfully',
            'data' => new BusinessCardResource(
                $this->attachFriendState($card->load(['company', 'user']), $request->user())
            ),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $card = BusinessCard::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$card) {
            return response()->json([
                'status' => 'error',
                'message' => 'Business card not found or unauthorized',
            ], 404);
        }

        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'company_id' => 'nullable|exists:companies,id',
            'position' => 'nullable|string|max:255',
            'phones' => 'nullable|array',
            'phones.*' => 'string|min:6',
            'emails' => 'nullable|array',
            'emails.*' => 'email',
            ...$this->addressRules(),
            'bio' => 'nullable|string',
            'profile_image' => 'nullable',
            'card_type' => 'nullable|string',
            'qr_code_data' => 'nullable|string',
            'social_links' => 'nullable|array',
        ]);

        $updateData = [
            'name' => $data['name'] ?? $card->name,
            'company_id' => array_key_exists('company_id', $data) ? $data['company_id'] : $card->company_id,
            'position' => $data['position'] ?? $card->position,
            'phones' => $data['phones'] ?? $card->phones,
            'emails' => $data['emails'] ?? $card->emails,
            'addresses' => $data['addresses'] ?? $card->addresses,
            'bio' => $data['bio'] ?? $card->bio,
            'card_type' => $data['card_type'] ?? $card->card_type,
            'qr_code_data' => $data['qr_code_data'] ?? $card->qr_code_data,
            'social_links' => $data['social_links'] ?? $card->social_links,
            'updated_by' => $request->user()->id,
        ];

        if ($request->hasFile('profile_image')) {
            $updateData['profile_image'] = $request->file('profile_image')->store('profile_images', 'public');
        } elseif ($request->input('profile_image') && is_string($request->input('profile_image'))) {
            $updateData['profile_image'] = $this->normalizeProfileImagePath($request->input('profile_image'));
        }

        $card->update($updateData);

        return response()->json([
            'status' => 'success',
            'message' => 'Business card updated successfully',
            'data' => new BusinessCardResource(
                $this->attachFriendState($card->refresh()->load(['company', 'user']), $request->user())
            ),
        ], 200);
    }

    public function destroy(Request $request, $id)
    {
        $card = BusinessCard::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$card) {
            return response()->json([
                'status' => 'error',
                'message' => 'Business card not found or unauthorized',
            ], 404);
        }

        $card->deleted_by = $request->user()->id;
        $card->saveQuietly();
        $card->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Business card deleted successfully',
        ], 200);
    }

    public function addFriend(Request $request, $id)
    {
        $currentUser = $request->user();
        $card = BusinessCard::with(['company.socials', 'user'])->find($id);

        if (!$card || !in_array($card->card_type, ['user_card', null, ''])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Card not found',
            ], 404);
        }

        if ($card->user_id === $currentUser->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'You cannot send a friend request to your own card',
            ], 422);
        }

        $friendship = $this->findFriendship($currentUser->id, $card->user_id);

        if ($friendship) {
            if ($friendship->status === 'accepted') {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Already friends',
                    'data' => new BusinessCardResource($this->attachFriendState($card, $currentUser)),
                ], 200);
            }

            if ($friendship->requester_user_id === $card->user_id && $friendship->status === 'pending') {
                $friendship->update([
                    'status' => 'accepted',
                    'accepted_at' => now(),
                ]);
                if ($card->user) {
                    FcmService::sendToUser($card->user, 'Friend Request Accepted', "{$currentUser->name} accepted your friend request.", [
                        'type' => 'friend_request_accepted',
                        'sender_id' => (string) $currentUser->id,
                    ]);
                }
            } else {
                $friendship->update([
                    'status' => 'pending',
                    'accepted_at' => null,
                ]);
                if ($card->user) {
                    FcmService::sendToUser($card->user, 'New Friend Request', "{$currentUser->name} sent you a friend request.", [
                        'type' => 'friend_request_received',
                        'sender_id' => (string) $currentUser->id,
                    ]);
                }
            }
        } else {
            Friendship::create([
                'requester_user_id' => $currentUser->id,
                'receiver_user_id' => $card->user_id,
                'status' => 'pending',
            ]);
            if ($card->user) {
                FcmService::sendToUser($card->user, 'New Friend Request', "{$currentUser->name} sent you a friend request.", [
                    'type' => 'friend_request_received',
                    'sender_id' => (string) $currentUser->id,
                ]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Friend request sent successfully',
            'data' => new BusinessCardResource($this->attachFriendState($card, $currentUser)),
        ], 200);
    }

    public function friendRequests(Request $request)
    {
        $friendships = Friendship::query()
            ->where('receiver_user_id', $request->user()->id)
            ->where('status', 'pending')
            ->get();

        $requesterIds = $friendships->pluck('requester_user_id');
        $friendshipsByRequester = $friendships->keyBy('requester_user_id');

        $requestCards = BusinessCard::with(['company.socials', 'user'])
            ->where(function ($q) {
                $q->where('card_type', 'user_card')
                  ->orWhereNull('card_type')
                  ->orWhere('card_type', '');
            })
            ->whereIn('user_id', $requesterIds)
            ->latest()
            ->get()
            ->map(function (BusinessCard $card) use ($request, $friendshipsByRequester) {
                $card->friend_request_status = 'pending';
                $card->friend_status = 'pending';
                $card->is_friend = false;
                $card->request_created_at = optional(
                    $friendshipsByRequester->get($card->user_id)
                )->created_at;
                return $this->attachFriendState($card, $request->user());
            });

        return response()->json([
            'status' => 'success',
            'message' => 'Friend requests fetched successfully',
            'data' => BusinessCardResource::collection($requestCards),
        ], 200);
    }

    public function acceptFriendRequest(Request $request, $id)
    {
        $requesterCard = BusinessCard::with(['company.socials', 'user'])->find($id);

        if (!$requesterCard) {
            return response()->json([
                'status' => 'error',
                'message' => 'Friend request card not found',
            ], 404);
        }

        $friendship = Friendship::query()
            ->where('requester_user_id', $requesterCard->user_id)
            ->where('receiver_user_id', $request->user()->id)
            ->where('status', 'pending')
            ->first();

        if (!$friendship) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pending friend request not found',
            ], 404);
        }

        $friendship->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        if ($requesterCard->user) {
            FcmService::sendToUser($requesterCard->user, 'Friend Request Accepted', "{$request->user()->name} accepted your friend request.", [
                'type' => 'friend_request_accepted',
                'sender_id' => (string) $request->user()->id,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Friend request accepted',
            'data' => new BusinessCardResource($this->attachFriendState($requesterCard, $request->user())),
        ], 200);
    }

    public function rejectFriendRequest(Request $request, $id)
    {
        $requesterCard = BusinessCard::find($id);

        if (!$requesterCard) {
            return response()->json([
                'status' => 'error',
                'message' => 'Friend request card not found',
            ], 404);
        }

        $friendship = Friendship::query()
            ->where('requester_user_id', $requesterCard->user_id)
            ->where('receiver_user_id', $request->user()->id)
            ->where('status', 'pending')
            ->first();

        if (!$friendship) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pending friend request not found',
            ], 404);
        }

        $friendship->update([
            'status' => 'rejected',
            'accepted_at' => null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Friend request rejected',
        ], 200);
    }

    public function removeFriend(Request $request, $id)
    {
        $friendCard = BusinessCard::find($id);

        if (!$friendCard) {
            return response()->json([
                'status' => 'error',
                'message' => 'Friend card not found',
            ], 404);
        }

        $friendship = $this->findFriendship($request->user()->id, $friendCard->user_id);

        if (!$friendship) {
            return response()->json([
                'status' => 'error',
                'message' => 'Friendship not found',
            ], 404);
        }

        $friendship->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Friend removed successfully',
        ], 200);
    }

    /**
     * Addresses are structured per-entry so location is exact across countries:
     * city and country are required; state and postal_code stay optional
     * because not every country uses them.
     */
    private function addressRules(): array
    {
        return [
            'addresses' => 'nullable|array',
            'addresses.*' => 'array',
            'addresses.*.street' => 'nullable|string|max:255',
            'addresses.*.city' => 'required|string|max:120',
            'addresses.*.state' => 'nullable|string|max:120',
            'addresses.*.postal_code' => 'nullable|string|max:32',
            'addresses.*.country' => 'required|string|max:120',
        ];
    }

    private function attachFriendState(BusinessCard $card, ?User $viewer): BusinessCard
    {
        $card->is_friend = false;
        $card->friend_status = 'none';
        $card->friend_request_status = 'none';

        if (!$viewer || !$card->user_id || $viewer->id === $card->user_id) {
            return $card;
        }

        $friendship = $this->findFriendship($viewer->id, $card->user_id);
        if (!$friendship) {
            return $card;
        }

        $card->friend_status = $friendship->status;
        if ($friendship->status === 'pending') {
            if ($friendship->requester_user_id === $viewer->id) {
                $card->friend_request_status = 'pending_sent';
            } else {
                $card->friend_request_status = 'pending_received';
            }
        } else {
            $card->friend_request_status = $friendship->status;
        }
        $card->is_friend = $friendship->status === 'accepted';

        return $card;
    }

    private function acceptedFriendUserIds(User $user): Collection
    {
        return Friendship::query()
            ->where('status', 'accepted')
            ->where(function ($query) use ($user) {
                $query->where('requester_user_id', $user->id)
                    ->orWhere('receiver_user_id', $user->id);
            })
            ->get()
            ->map(function (Friendship $friendship) use ($user) {
                return $friendship->requester_user_id === $user->id
                    ? $friendship->receiver_user_id
                    : $friendship->requester_user_id;
            })
            ->values();
    }

    private function findFriendship(int $firstUserId, int $secondUserId): ?Friendship
    {
        return Friendship::query()
            ->where(function ($query) use ($firstUserId, $secondUserId) {
                $query->where('requester_user_id', $firstUserId)
                    ->where('receiver_user_id', $secondUserId);
            })
            ->orWhere(function ($query) use ($firstUserId, $secondUserId) {
                $query->where('requester_user_id', $secondUserId)
                    ->where('receiver_user_id', $firstUserId);
            })
            ->first();
    }

    private function normalizeProfileImagePath(string $value): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return $trimmed;
        }

        if (SupportStr::startsWith($trimmed, ['http://', 'https://'])) {
            $path = parse_url($trimmed, PHP_URL_PATH);
            if (is_string($path) && str_contains($path, '/storage/')) {
                return ltrim(substr($path, strpos($path, '/storage/') + 9), '/');
            }

            return $trimmed;
        }

        if (str_contains($trimmed, '/storage/')) {
            return ltrim(substr($trimmed, strpos($trimmed, '/storage/') + 9), '/');
        }

        return ltrim($trimmed, '/');
    }
}
