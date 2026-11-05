@php($pageEyebrow = 'Product')
@php($pageTitle = 'Changelog')
@php($pageIntro = 'What has changed in REDOLIO, newest first.')
@extends('pages.layout')

@section('page')
<h2>Latest</h2>
<ul>
<li><strong>Edit the design:</strong> change text, pictures and add-ons on your new design, drag add-ons up and down the page, and pick a layout from a picture of each one.</li>
<li><strong>Transparent pictures</strong> keep their transparency and are shown whole.</li>
<li><strong>Privacy:</strong> an analysis can only be opened by its owner (or an admin).</li>
<li><strong>Export Pass:</strong> download your finished website as files for any web host, with a guide to uploading them.</li>
<li><strong>Design Directions:</strong> import styles from a DESIGN.md file, with a screenshot of the inspiring site.</li>
<li><strong>Open source:</strong> the code is public on GitHub, with a first-run setup and automatic tests.</li>
</ul>
<h2>Earlier</h2>
<ul>
<li>Payments for plans and the one-time Export Pass.</li>
<li>AI pictures in the editor (Meta Muse Image).</li>
<li>Side-by-side “Reveal” of the old and new website.</li>
<li>Sign in with Google.</li>
<li>Free plan with 2 website analyses.</li>
</ul>
@endsection
