<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Events\Registered;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class AuthController extends Controller
{
    /**
     * Register new user
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
    
        $user->ulid = (string) Str::ulid();
        $user->save();

        event(new Registered($user));

        return response()->json([
            'ok' => true,
            'must_verify_email' => !$user->hasVerifiedEmail() && $user->getEmailForVerification(),
        ], 201);
    }

    /**
     * Generate sanctum token on successful login
     */
    public function login(LoginRequest $request)
    {
        $request->authenticate();

        $user = User::where('email', $request->email)->first();

        if ($user instanceof MustVerifyEmail && !$user->hasVerifiedEmail()) {
            return response()->json([
                'ok' => false,
                'action' => 'verify_email',
                'message' => __('Please confirm your email address'),
            ]);
        }

        return response()->json([
            'ok' => true,
            'user' => $user,
            'token' => $user->createToken(
                $request->userAgent(),
                ['*'],
                $request->remember ? 
                    now()->addMonth():
                    now()->addDay()
            )->plainTextToken,
        ], 200);
    }

    /**
     * Revoke token; only remove token that is used to perform logout (i.e. will not revoke all tokens)
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'ok' => true
        ], 200);
    }

    /**
     * Get authenticated user details
     */
    public function user(Request $request)
    {
        return response()->json([
            'ok' => true,
            'user' => $request->user()
        ], 200);
    }

    /**
     * Handle an incoming password reset link request.
     */
    public function sendResetPasswordLink(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status != Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'ok' => true,
            'message' => __($status)
        ]);
    }

    /**
     * Handle an incoming new password request.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status != Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'ok' => true,
            'message' => __($status)
        ]);
    }

    /**
     * Mark the authenticated user's email address as verified.
     */
    public function emailVirify(Request $request, $ulid, $hash): JsonResponse
    {
        $user = User::whereUlid($ulid)->first();

        abort_unless($user, 404);
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403, __('Invalid verification link'));

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();

            event(new Verified($user));
        }
    
        return response()->json([
            'ok' => true
        ]);
    }

    /**
     * Send a new email verification notification.
     */
    public function verificationNotification(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->email)->whereNull('email_verified_at')->first();
        abort_unless($user, 400);

        $user->sendEmailVerificationNotification();

        return response()->json([
            'ok' => true,
            'message' => 'Verification link sent!'
        ]);
    }
}
