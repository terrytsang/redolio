@php($pageEyebrow = 'Product')
@php($pageTitle = 'Roadmap')
@php($pageIntro = 'What we are working on and thinking about. Plans can change.')
@extends('pages.layout')

@section('page')
<h2>Now</h2>
<ul><li>Making edited designs easier to polish: more layouts and add-ons.</li><li>Faster analyses for larger sites.</li></ul>
<h2>Next</h2>
<ul><li>More Design Directions.</li><li>Easier publishing to your own domain.</li></ul>
<h2>Later</h2>
<ul><li>Re-checking your site over time and suggesting improvements.</li></ul>
<p>Have a request? <a href="{{ route('pages.contact') }}">Tell us</a>.</p>
@endsection
