@extends('layouts.app')

@section('title', 'Your website files are ready — REDOLIO')
@section('no_float_toggle', '1')

@php
    $dl = route('exports.download', $token);
    $mail = 'mailto:'.config('redo.support_email').'?subject='.rawurlencode('Installation service for '.$host).'&body='.rawurlencode("Hi REDOLIO,\n\nI'd like help installing my exported website files on my hosting.\n\nWebsite: {$host}\nHosting provider (e.g. Hostinger, GoDaddy, cPanel):\nDomain name:\n\nThanks!");
@endphp

@section('content')
    @auth<x-app-header active="sites" />@endauth
    <div class="relative">
        <div class="page-glow"></div>
        <main class="relative mx-auto max-w-3xl px-5 pb-24 pt-10 sm:px-8">
            <div class="text-center">
                <span class="pill pill-ok">✓ Export ready</span>
                <h1 class="mt-4 text-[30px] font-black leading-[1.1] tracking-tight text-ink sm:text-[40px]">Your <span class="grad-text">website files</span> are ready</h1>
                <p class="mt-3 text-[15.5px] leading-relaxed text-muted">The HTML, CSS and JS for <strong class="text-ink">{{ $host }}</strong> are in one zip file. Your download should start by itself.</p>
                <a id="dl" href="{{ $dl }}" class="btn btn-primary mt-6 px-8 py-3.5 text-[15px]" download>⬇ Download website files (.zip)</a>
                <p class="mt-2 text-[12.5px] text-faint">If nothing happened, press the button. This link works for {{ max(1, (int) ceil(now()->diffInHours($export->expires_at, false))) }} more hours.</p>
            </div>

            <section class="card mt-10 p-6 sm:p-8" aria-labelledby="how-h">
                <h2 id="how-h" class="text-xl font-extrabold tracking-tight text-ink">How to put it on your website</h2>
                <p class="mt-1 text-[14px] text-muted">Works with ordinary web hosting (cPanel, Hostinger, GoDaddy, Bluehost, Namecheap, SiteGround and similar). No coding needed.</p>

                <ol class="mt-6 space-y-5 text-[14.5px] leading-relaxed text-body">
                    <li class="flex gap-4"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-accent-soft text-sm font-extrabold text-accent-ink">1</span>
                        <div><p class="font-bold text-ink">Unzip the download</p><p>Double-click the zip on your computer. You’ll see <code class="rounded bg-surface-2 px-1.5 py-0.5 text-[13px]">index.html</code> and an <code class="rounded bg-surface-2 px-1.5 py-0.5 text-[13px]">assets</code> folder (your styles, fonts and pictures). Keep them together.</p></div></li>
                    <li class="flex gap-4"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-accent-soft text-sm font-extrabold text-accent-ink">2</span>
                        <div><p class="font-bold text-ink">Open your hosting’s file manager</p><p>Log in to your hosting account and open <strong>File Manager</strong> (cPanel, hPanel and Plesk all have one). Prefer FTP? Use an app such as FileZilla with the FTP details from your host.</p></div></li>
                    <li class="flex gap-4"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-accent-soft text-sm font-extrabold text-accent-ink">3</span>
                        <div><p class="font-bold text-ink">Go to your website’s main folder</p><p>This is usually called <code class="rounded bg-surface-2 px-1.5 py-0.5 text-[13px]">public_html</code> (sometimes <code class="rounded bg-surface-2 px-1.5 py-0.5 text-[13px]">www</code> or <code class="rounded bg-surface-2 px-1.5 py-0.5 text-[13px]">htdocs</code>). For a subdomain, open that subdomain’s own folder. <em>Tip: download a backup of any old files first.</em></p></div></li>
                    <li class="flex gap-4"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-accent-soft text-sm font-extrabold text-accent-ink">4</span>
                        <div><p class="font-bold text-ink">Upload the files</p><p>Upload <strong>everything inside</strong> the unzipped folder — <code class="rounded bg-surface-2 px-1.5 py-0.5 text-[13px]">index.html</code> and the <code class="rounded bg-surface-2 px-1.5 py-0.5 text-[13px]">assets</code> folder — straight into the main folder, not inside another folder. Or upload the zip itself and use <strong>Extract</strong> in the file manager.</p></div></li>
                    <li class="flex gap-4"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-accent-soft text-sm font-extrabold text-accent-ink">5</span>
                        <div><p class="font-bold text-ink">Replace the old home page</p><p>If the folder already has an <code class="rounded bg-surface-2 px-1.5 py-0.5 text-[13px]">index.html</code>, <code class="rounded bg-surface-2 px-1.5 py-0.5 text-[13px]">index.php</code> or a “coming soon” page, rename or delete it so your new <code class="rounded bg-surface-2 px-1.5 py-0.5 text-[13px]">index.html</code> is the one that loads.</p></div></li>
                    <li class="flex gap-4"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-accent-soft text-sm font-extrabold text-accent-ink">6</span>
                        <div><p class="font-bold text-ink">Check it</p><p>Open your domain in a browser and refresh with <kbd class="rounded border border-line px-1.5 py-0.5 text-[12px]">Ctrl</kbd>+<kbd class="rounded border border-line px-1.5 py-0.5 text-[12px]">Shift</kbd>+<kbd class="rounded border border-line px-1.5 py-0.5 text-[12px]">R</kbd> (Mac: <kbd class="rounded border border-line px-1.5 py-0.5 text-[12px]">⌘</kbd>+<kbd class="rounded border border-line px-1.5 py-0.5 text-[12px]">Shift</kbd>+<kbd class="rounded border border-line px-1.5 py-0.5 text-[12px]">R</kbd>). Still seeing the old site? Clear your hosting’s cache (or CDN, if you use one).</p></div></li>
                </ol>

                <div class="mt-7 rounded-xl bg-surface-2 p-4 text-[13px] leading-relaxed text-muted">
                    <p class="font-semibold text-ink">Good to know</p>
                    <ul class="mt-1.5 list-disc space-y-1 pl-5">
                        <li>This is a static website: it needs no database, PHP or build step.</li>
                        <li>Contact forms and other add-ons that send data need their own service (for example a form service or your email provider) — they aren’t part of a plain HTML file.</li>
                        <li>Not sure which web hosting you have? Your domain provider or the email they sent you when you signed up will say.</li>
                    </ul>
                </div>
            </section>

            <section class="card mt-6 flex flex-col gap-4 p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8" aria-labelledby="help-h">
                <div class="max-w-md">
                    <h2 id="help-h" class="text-lg font-extrabold tracking-tight text-ink">Want us to do it for you?</h2>
                    <p class="mt-1 text-[14px] leading-relaxed text-body">Email <a href="{{ $mail }}" class="font-semibold text-accent-ink underline">{{ config('redo.support_email') }}</a> for our installation service. Tell us your hosting provider and domain, and we’ll get your website live.</p>
                </div>
                <a href="{{ $mail }}" class="btn btn-dark shrink-0 px-6 py-3">✉ Email {{ config('redo.support_email') }}</a>
            </section>

            <p class="mt-8 text-center text-[13px]">
                @if ($run)<a href="{{ route('analyses.launch', $run->public_id) }}" class="font-semibold text-accent-ink hover:text-ink">← Back to my website</a>@endif
                @auth<span class="mx-3 text-faint">·</span><a href="{{ route('dashboard') }}" class="font-semibold text-muted hover:text-ink">My websites</a>@endauth
            </p>
        </main>
    </div>

    <script>
        // Start the download once the page has shown (a download keeps this page open).
        setTimeout(function () { var a = document.getElementById('dl'); if (a) window.location.href = a.href; }, 900);
    </script>
@endsection
