<section class="bp-section" id="faq" data-section="{{ $id }}">
    <div class="bp-wrap">
        <h2 class="bp-center bp-head" style="margin-left:auto;margin-right:auto">{{ $c['heading'] }}</h2>
        <div class="bp-faq">@foreach ($c['items'] as $q)<details><summary>{{ $q['question'] }}</summary><p>{{ $q['answer'] }}</p></details>@endforeach</div>
    </div>
</section>
