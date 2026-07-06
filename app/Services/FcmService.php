<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class FcmService
{
    /**
     * Send a push notification to a specific user.
     *
     * @param User $user The recipient user
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Extra data payload
     * @return bool
     */
    public static function sendToUser(User $user, string $title, string $body, array $data = []): bool
    {
        $token = $user->fcm_token;

        if (!$token) {
            Log::info("FCM: Cannot send notification to user #{$user->id} ({$user->name}) because FCM token is missing.");
            return false;
        }

        $serverKey = config('services.fcm.server_key') ?? env('FCM_SERVER_KEY');

        if (!$serverKey) {
            Log::info("FCM Mock Notification (FCM_SERVER_KEY is not set):", [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'fcm_token' => $token,
                'title' => $title,
                'body' => $body,
                'data' => $data,
            ]);
            return true;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . $serverKey,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'to' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                    'sound' => 'default',
                ],
                'data' => $data,
                'priority' => 'high',
            ]);

            if ($response->successful()) {
                Log::info("FCM: Notification sent successfully to user #{$user->id}");
                return true;
            } else {
                Log::error("FCM: Failed to send notification to user #{$user->id}. Response: " . $response->body());
                return false;
            }
        } catch (\Throwable $e) {
            Log::error("FCM: Exception while sending notification: " . $e->getMessage());
            return false;
        }
    }
}
