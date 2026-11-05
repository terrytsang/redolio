@php($pageEyebrow = 'Company')
@php($pageTitle = 'Terms of service')
@php($pageIntro = 'The rules for using REDOLIO.')
@php($pageUpdated = 'November 2026')
@extends('pages.layout')

@section('page')
<p><em>This is a plain-language draft. Please have it reviewed before relying on it as a legal document.</em></p>
<ul>
<li><strong>Your content.</strong> Only analyse websites you own or have permission to redo. You keep your rights to your own content.</li>
<li><strong>Our output.</strong> Designs are generated automatically and may contain mistakes. Check them before publishing.</li>
<li><strong>Acceptable use.</strong> Do not misuse the service, overload it, or use it for anything unlawful.</li>
<li><strong>Payments.</strong> Plans and the Export Pass are as shown on the <a href="{{ route('pricing') }}">pricing page</a>. Payments are handled by Stripe.</li>
<li><strong>Availability.</strong> We work to keep REDOLIO running, but do not guarantee it will always be available. See <a href="{{ route('pages.status') }}">API status</a>.</li>
<li><strong>Changes.</strong> We may update these terms; continued use means you accept them.</li>
</ul>
@endsection
