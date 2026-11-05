@php($pageEyebrow = 'Company')
@php($pageTitle = 'About REDOLIO')
@php($pageIntro = 'REDOLIO redesigns a website you already have: you paste the address, and it shows you a better version next to the old one.')
@extends('pages.layout')

@section('page')
<h2>What it does</h2>
<p>REDOLIO reads your live website, works out what it is for and who it is for, points out what is holding it back, and then designs a new version using your own words, pictures and brand. You can edit the new design, publish it, or download it as files to put on any normal web hosting.</p>
<h2>Why we built it</h2>
<p>Most small business websites are years out of date, and getting a redesign usually means weeks of back-and-forth and a large bill. We wanted the first draft to take minutes, and to be honest about <em>why</em> a change is suggested, so you stay in control.</p>
<h2>Who makes it</h2>
<p>REDOLIO is a product of <a href="https://appition.com" target="_blank" rel="noopener noreferrer">Appition</a>. The code is open source at <a href="https://github.com/terrytsang/redolio" target="_blank" rel="noopener noreferrer">github.com/terrytsang/redolio</a>, so you can read it or run your own copy.</p>
<p>Questions? See the <a href="{{ route('pages.contact') }}">contact page</a>.</p>
@endsection
