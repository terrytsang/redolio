<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Account\AfterSignIn;
use App\Services\Account\NewAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

/**
 * "Sign in with Google" (Google Identity Services). The page's Google button hands us a signed ID token; we ask Google to
 * verify it and only accept one issued to THIS app's client id with a verified email. Sign-up and sign-in are one click: the
 * email either signs in the existing account or creates one on the Free plan. Needs only the Web client ID (no secret).
 */
class GoogleAuthController extends Controller
{
    public static function clientId(): string
    {
        return (string) config('services.google.client_id');
    }

    public static function configured(): bool
    {
        return self::clientId() !== '';
    }

    public function token(Request $request, NewAccount $accounts, AfterSignIn $after): JsonResponse
    {
        $data = $request->validate(['credential' => ['required', 'string', 'max:6000'], 'claim_run' => ['nullable', 'string', 'max:64'], 'remix_direction' => ['nullable', 'string', 'max:64']]);
        $fail = fn (string $m, int $code = 422) => response()->json(['message' => $m], $code);
        if (! self::configured()) {
            return $fail('Google sign-in isn’t set up yet — please use your email and password.', 503);
        }

        $r = Http::timeout(15)->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $data['credential']]);
        $info = $r->json() ?: [];
        $verified = filter_var($info['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $issuer = in_array($info['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true);
        $email = strtolower((string) ($info['email'] ?? ''));
        if (! $r->successful() || ! $issuer || ($info['aud'] ?? '') !== self::clientId() || (int) ($info['exp'] ?? 0) < time() || ! $verified || $email === '' || empty($info['sub'])) {
            return $fail('We couldn’t verify that Google sign-in. Please try again.');
        }

        $sub = (string) $info['sub'];
        $user = User::where('google_id', $sub)->first() ?? User::where('email', $email)->first();
        $isNew = false;
        if ($user) {
            if (! $user->is_active) {
                return $fail('This account has been deactivated. Please contact an administrator.', 403);
            }
            $user->google_id ?: $user->forceFill(['google_id' => $sub])->save();
        } else {
            $user = $accounts->create($email, null, (string) ($info['name'] ?? '') ?: null, $sub);
            $isNew = true;
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        $request->merge(array_filter(['claim_run' => $data['claim_run'] ?? null, 'remix_direction' => $data['remix_direction'] ?? null]));

        return response()->json(['redirect' => $after->redirect($request, $user, $isNew)->getTargetUrl()]);
    }
}
