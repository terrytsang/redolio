<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** A person an admin deactivates is signed out on their next request, not only blocked from signing in again. */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (($user = $request->user()) && $user->is_active === false) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'This account has been deactivated. Please contact an administrator.');
        }

        return $next($request);
    }
}
