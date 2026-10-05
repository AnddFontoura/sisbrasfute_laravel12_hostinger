<?php

namespace App\Http\Controllers;

use App\Mail\EmailVerificationMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class EmailVerificationController extends Controller
{
    /**
     * Verify a user's email address via signed URL.
     */
    public function verify(Request $request, int $id, string $hash): JsonResponse
    {
        if (!$request->hasValidSignature()) {
            // Check if it's expired vs tampered
            if (now()->timestamp > $request->query('expires')) {
                return response()->json([
                    'message' => __('error.email_verification.link_expired'),
                    'error' => 'link_expired',
                ], Response::HTTP_GONE);
            }

            return response()->json([
                'message' => __('error.email_verification.invalid_link'),
                'error' => 'invalid_signature',
            ], Response::HTTP_FORBIDDEN);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => __('error.user.not_found'),
                'error' => 'user_not_found',
            ], Response::HTTP_NOT_FOUND);
        }

        if (!hash_equals(sha1($user->email), $hash)) {
            return response()->json([
                'message' => __('error.email_verification.invalid_link'),
                'error' => 'invalid_signature',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($user->email_verified_at !== null) {
            return response()->json([
                'message' => __('messages.email_verification.already_verified'),
            ], Response::HTTP_OK);
        }

        $user->email_verified_at = now();
        $user->save();

        return response()->json([
            'message' => __('messages.email_verification.verified'),
        ], Response::HTTP_OK);
    }

    /**
     * Resend the email verification link to the authenticated user.
     */
    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->email_verified_at !== null) {
            return response()->json([
                'message' => __('messages.email_verification.already_verified'),
            ], Response::HTTP_OK);
        }

        $throttleKey = 'verify-resend:' . $user->id;

        if (RateLimiter::tooManyAttempts($throttleKey, 1)) {
            return response()->json([
                'message' => __('error.email_verification.throttled'),
                'error' => 'throttled',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        RateLimiter::hit($throttleKey, 60);

        $signedUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $parsed = parse_url($signedUrl);
        parse_str($parsed['query'], $queryParams);

        $frontendUrl = config('app.frontend_url')
            . '/verify-email?id=' . $user->id
            . '&hash=' . sha1($user->email)
            . '&expires=' . $queryParams['expires']
            . '&signature=' . $queryParams['signature'];

        Mail::to($user->email)->send(new EmailVerificationMail($frontendUrl, $user->name));

        return response()->json([
            'message' => __('messages.email_verification.resent'),
        ], Response::HTTP_OK);
    }
}
