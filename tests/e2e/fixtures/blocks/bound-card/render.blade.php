{{--
    The bound-card fixture: a Blade block whose block.json lists its "title"
    attribute under pollora.bindings. tests/e2e binds it to a source and
    checks the page shows the bound value instead of the saved one.
--}}
<div {!! get_block_wrapper_attributes() !!}>
    <h3 class="e2e-bound-card__title">{{ $attributes['title'] ?? '' }}</h3>
</div>
