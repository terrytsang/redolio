@php($pageEyebrow = 'Company')
@php($pageTitle = 'Contact')
@php($pageIntro = 'We read every message.')
@extends('pages.layout')

@section('page')
<p>Email us at <a href="mailto:{{ config('redo.support_email') }}">{{ config('redo.support_email') }}</a> for help with your account, billing, installation of your exported website, security reports, or ideas.</p>
<p>For code issues, you can also open an issue on <a href="https://github.com/terrytsang/redolio/issues" target="_blank" rel="noopener noreferrer">GitHub</a>.</p>
@endsection
