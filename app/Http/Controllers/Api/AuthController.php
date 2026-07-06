<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\BusinessCard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Str; // Added import
use Carbon\Carbon;

class AuthController extends Controller
{

    // =========================
    // SEND OTP (Register Step 1)
    // =========================
    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ], [
            'email.required' => 'Email is required',
            'email.email' => 'Email format is invalid',
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user && $user->is_verified) {
            return response()->json([
                'success' => false,
                'message' => 'This email is already registered. Please login instead.'
            ], 400);
        }

        $otp = rand(100000, 999999);
        $otpMailer = config('mail.otp_mailer', 'failover');

        $user = User::updateOrCreate(
            ['email' => $request->email],
            [
                'otp' => $otp,
                'otp_expires_at' => now()->addMinutes(5),
                'is_verified' => false
            ]
        );

        try {
            Mail::mailer($otpMailer)->send(
                'emails.otp',
                [
                    'otp' => (string) $otp,
                    'email' => $request->email,
                    'appName' => config('app.name', 'BusinessCard4U'),
                    'iconUrl' => rtrim((string) config('app.url'), '/') . '/ABW.png',
                ],
                function ($message) use ($request) {
                    $message
                        ->to($request->email)
                        ->subject('BusinessCard4U OTP Code');
                }
            );
        } catch (\Throwable $e) {
            Log::error('OTP email send failed', [
                'email' => $request->email,
                'mailer' => $otpMailer,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'OTP created but email delivery failed',
                'otp' => app()->isLocal() ? (string) $otp : null,
                'error' => app()->isProduction() ? null : $e->getMessage(),
            ], 500);
        }

        $response = [
            'success' => true,
            'message' => 'OTP sent successfully'
        ];

        if (app()->isLocal()) {
            $response['otp'] = (string) $otp;
            $response['delivery'] = $otpMailer === 'log' ? 'logged' : 'sent_or_logged';
        }

        return response()->json($response, 200);
    }




    // =========================
    // VERIFY OTP ONLY
    // =========================
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        if ($user->otp !== $request->otp) {
            return response()->json([
                'message' => 'Invalid OTP'
            ], 400);
        }

        if (now()->gt($user->otp_expires_at)) {
            return response()->json([
                'message' => 'OTP expired'
            ], 400);
        }
        $user->update([
            'email_verified_at' => now(),
            'otp' => null,
            'otp_expires_at' => null,
        ]);

        return response()->json([
            'message' => 'OTP verified successfully'
        ]);
    }
    public function completeRegister(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'name' => 'required|string',
            'password' => 'required|min:6|confirmed'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if ($user->is_verified) {
            return response()->json(['message' => 'Already registered'], 400);
        }

        $user->update([
            'name' => $request->name,
            'password' => Hash::make($request->password), // Ensure password hashing
            'is_verified' => true
        ]);

        // Automatically create a default Business Card for the user
        BusinessCard::create([
            'user_id' => $user->id,
            'created_by' => $user->id,
            'name' => $user->name,
            'position' => 'Member', // Default position
            'emails' => [$user->email], // Default email
            'card_type' => 'user_card', // Default is user_card (profile)
            'qr_code_data' => 'user-' . $user->id . '-' . Str::uuid(), // Auto-generate QR Code
        ]);

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
            'message' => 'Registration completed and Business Card created'
        ]);
    }



    // =========================
    // LOGIN
    // =========================
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        if (!$user->is_verified) {
            return response()->json([
                'message' => 'Please verify your email first'
            ], 403);
        }

        $message = 'Logged in successfully';

        if ($user->deactivated_at !== null) {
            if (
                $user->reactivation_deadline_at !== null &&
                now()->greaterThan($user->reactivation_deadline_at)
            ) {
                return response()->json([
                    'message' => 'Your account can no longer be restored. Please contact support.'
                ], 403);
            }

            $user->forceFill([
                'deactivated_at' => null,
                'reactivation_deadline_at' => null,
            ])->save();

            $message = 'Your account has been restored successfully';
        }

        $user->tokens()->delete();

        if ($request->filled('firebase_token')) {
            $user->update(['fcm_token' => $request->firebase_token]);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
            'message' => $message
        ]);
    }


    // =========================
    // LOGOUT
    // =========================
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    public function deactivateAccount(Request $request)
    {
        $request->validate([
            'password' => 'required',
        ], [
            'password.required' => 'Password is required',
        ]);

        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Your password is incorrect',
            ], 403);
        }

        $user->forceFill([
            'deactivated_at' => now(),
            'reactivation_deadline_at' => now()->addDays(7),
        ])->save();

        $user->tokens()->delete();

        return response()->json([
            'message' => 'Your account has been deactivated. Log in again within 7 days to restore it.',
            'reactivation_deadline_at' => optional($user->reactivation_deadline_at)->toIso8601String(),
        ]);
    }


    // =========================
    // CHANGE PASSWORD
    // =========================
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:6|confirmed'
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect'
            ], 403);
        }

        $user->update([
            'password' => $request->new_password
        ]);

        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password changed successfully. Please login again.'
        ]);
    }


    // =========================
    // FORGOT PASSWORD
    // =========================
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        ResetPassword::createUrlUsing(function ($user, string $token) {
            return 'myapp://reset-password?token=' . $token . '&email=' . $user->email;
        });

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return response()->json([
            'message' => __($status)
        ], $status === Password::RESET_LINK_SENT ? 200 : 400);
    }


    // =========================
    // RESET PASSWORD
    // =========================
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:6|confirmed'
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->update([
                    'password' => $password
                ]);

                $user->tokens()->delete();
            }
        );

        return response()->json([
            'message' => __($status)
        ], $status === Password::PASSWORD_RESET ? 200 : 400);
    }


    // =========================
    // REFRESH TOKEN
    // =========================
    public function refreshToken(Request $request)
    {
        $user = $request->user();

        $user->currentAccessToken()->delete();

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token
        ]);
    }

    // =========================
    // ME (Get Current User)
    // =========================
    public function me(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'user'   => $request->user(),
        ]);
    }
}
