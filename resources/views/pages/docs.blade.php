@php($pageEyebrow = 'Resources')
@php($pageTitle = 'Documentation')
@php($pageIntro = 'How to use REDOLIO, from your first analysis to a published website.')
@extends('pages.layout')

@section('page')
<h2>1. Analyse your website</h2>
<p>On the <a href="{{ route('landing') }}#analyze">homepage</a>, paste your website address and start. REDOLIO reads your pages, then shows what it found and what could be better, with the evidence.</p>
<h2>2. See the new design</h2>
<p>You get a redesigned version using your own content and brand. Compare it with the old one side by side.</p>
<h2>3. Edit it</h2>
<p>Choose <em>Edit the design</em> to change text and pictures, switch layouts, and add sections such as a hero, testimonials or buttons. Add-ons can be dragged into place.</p>
<h2>4. Publish or download</h2>
<p>Publish it, or use an Export Pass to download the website as files. After exporting you are shown how to upload them to normal web hosting. See <a href="{{ route('pricing') }}">pricing</a> for what each plan includes.</p>
<h2>Running your own copy</h2>
<p>REDOLIO is open source. The <a href="https://github.com/terrytsang/redolio#readme" target="_blank" rel="noopener noreferrer">README</a> explains installation, the database, and how an admin adds a Meta Model API key under Settings.</p>
@endsection
