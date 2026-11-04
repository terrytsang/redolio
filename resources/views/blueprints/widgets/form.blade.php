@php
    $mail = ($c['destination'] ?? 'email') === 'email' && ! empty($c['email']);
    $hook = ($c['destination'] ?? '') === 'endpoint' && ! empty($c['endpoint']);
    $action = $mail ? 'mailto:'.$c['email'].'?subject='.rawurlencode(($c['heading'] ?: 'New message').' — website form') : ($hook ? $c['endpoint'] : null);
@endphp
<section class="bp-section" id="form-{{ $id }}">
    <div class="bp-wrap">
        <div class="bpw-form bpw-form--{{ $c['style'] ?? 'card' }}">
            @if (! empty($c['heading']))<h2>{{ $c['heading'] }}</h2>@endif
            @if (! empty($c['intro']))<p class="bp-lead" style="margin-top:.6rem">{{ $c['intro'] }}</p>@endif
            <form class="bp-form" aria-label="{{ $c['heading'] ?: 'Contact form' }}" @if ($action) action="{{ $action }}" method="post" @if ($mail) enctype="text/plain" @endif @endif>
                @foreach ($c['fields'] as $i => $f)
                    @php $name = \Illuminate\Support\Str::slug($f['label'], '_') ?: 'field_'.($i + 1); @endphp
                    <label>{{ $f['label'] }}@if (! empty($f['required'])) <span aria-hidden="true">*</span>@endif
                        @if ($f['type'] === 'textarea')<textarea name="{{ $name }}" rows="4" @required(! empty($f['required']))></textarea>
                        @else<input type="{{ $f['type'] }}" name="{{ $name }}" @required(! empty($f['required']))>@endif
                    </label>
                @endforeach
                <button class="bp-btn" type="submit">{{ $c['submit_label'] ?: 'Send' }}</button>
                @if (! empty($c['note']))<p class="bpw-note">{{ $c['note'] }}</p>@endif
            </form>
        </div>
    </div>
</section>
