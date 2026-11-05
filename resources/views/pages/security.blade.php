@php($pageEyebrow = 'Resources')
@php($pageTitle = 'Security')
@php($pageIntro = 'How we look after your account and your data.')
@extends('pages.layout')

@section('page')
<ul>
<li><strong>Your analyses are private.</strong> Only you (and site administrators) can open them.</li>
<li><strong>Secrets are encrypted.</strong> API keys saved in the admin settings are stored encrypted, and are never part of the source code.</li>
<li><strong>Payments are handled by Stripe.</strong> We never see or store your card number.</li>
<li><strong>Read-only crawling.</strong> When analysing a site we only read public pages, and identify ourselves with a REDOLIO user-agent.</li>
<li><strong>Passwords</strong> are stored hashed, and deactivated accounts are signed out.</li>
</ul>
<h2>Report a problem</h2>
<p>If you think you found a security issue, please <a href="{{ route('pages.contact') }}">contact us</a> privately before sharing it publicly, and we will respond as soon as we can.</p>
@endsection
