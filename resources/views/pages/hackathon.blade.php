@php($pageEyebrow = 'Company')
@php($pageTitle = 'Built at Meta Hackathon')
@php($pageIntro = 'REDOLIO uses Meta’s Muse models to understand and redesign websites.')
@extends('pages.layout')

@section('page')
<p>REDOLIO is built on Meta’s Models API. <strong>Muse Spark</strong> reads each website and writes the diagnosis, the new copy and the design choices, and <strong>Muse Image</strong> can make a picture when a site has no suitable one of its own.</p>
<h2>How the models are used</h2>
<ul>
<li><strong>Understanding:</strong> the site’s pages are read and summarised into a profile of the business, its audience and its tone.</li>
<li><strong>Diagnosis:</strong> problems are listed with the evidence for each, taken from the site itself.</li>
<li><strong>Redesign:</strong> a new page is written and laid out in one of our design directions, then checked visually in a real browser.</li>
<li><strong>Images:</strong> original pictures are always preferred; AI images are only a last resort and are labelled as such.</li>
</ul>
<p>The whole project is open source, and you can connect your own Meta Model API key in the admin settings. See <a href="{{ route('pages.docs') }}">the documentation</a>.</p>
@endsection
