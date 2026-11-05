@php($pageEyebrow = 'Company')
@php($pageTitle = 'Privacy policy')
@php($pageIntro = 'What we collect, why, and your choices.')
@php($pageUpdated = 'November 2026')
@extends('pages.layout')

@section('page')
<p><em>This is a plain-language summary. Please have it reviewed before relying on it as a legal document.</em></p>
<h2>What we collect</h2>
<ul>
<li><strong>Account details:</strong> your name and email, or your Google sign-in details.</li>
<li><strong>Websites you analyse:</strong> the public content and pictures of the addresses you give us, and the designs made from them.</li>
<li><strong>Payment records:</strong> processed by Stripe; we keep the transaction record, not your card number.</li>
</ul>
<h2>How we use it</h2>
<p>To run the service: analyse and redesign your website, keep your work, take payment, and contact you about your account. Website content is sent to our AI provider (Meta) to produce the analysis and design.</p>
<h2>Sharing</h2>
<p>We do not sell your data. We share it only with the services needed to run REDOLIO (AI, payments, hosting, email).</p>
<h2>Your choices</h2>
<p>You can ask us to delete your account and your analyses at any time via the <a href="{{ route('pages.contact') }}">contact page</a>.</p>
@endsection
