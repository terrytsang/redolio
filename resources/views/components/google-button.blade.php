@props(['label' => 'continue_with', 'claim' => null, 'remix' => null])
@if (\App\Http\Controllers\Auth\GoogleAuthController::configured())
    {{-- Google Identity Services button; its signed ID token is verified server-side (see GoogleAuthController). --}}
    <div class="flex flex-col items-center gap-2" data-google-auth data-claim="{{ $claim }}" data-remix="{{ $remix }}">
        <div id="g_id_onload" data-client_id="{{ \App\Http\Controllers\Auth\GoogleAuthController::clientId() }}" data-callback="redolioGoogleSignIn" data-auto_prompt="false" data-use_fedcm_for_prompt="true"></div>
        <div class="g_id_signin" data-type="standard" data-size="large" data-theme="outline" data-text="{{ $label }}" data-shape="pill" data-logo_alignment="left" data-width="320"></div>
        <p id="google-auth-error" class="hidden text-center text-[12.5px] text-bad" role="alert"></p>
    </div>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <script>
        window.redolioGoogleSignIn = async function (response) {
            const box = document.querySelector('[data-google-auth]'), err = document.getElementById('google-auth-error');
            try {
                const res = await fetch(@json(route('auth.google.token')), { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                    body: JSON.stringify({ credential: response.credential, claim_run: box.dataset.claim || null, remix_direction: box.dataset.remix || null }) });
                const d = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(d.message || 'Google sign-in failed. Please try again.');
                window.location.href = d.redirect;
            } catch (e) { err.textContent = e.message; err.classList.remove('hidden'); }
        };
    </script>
@endif
