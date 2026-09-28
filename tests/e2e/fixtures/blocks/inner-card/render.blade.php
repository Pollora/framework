{{--
    The inner-card fixture: the render.blade.php make:block writes for
    --inner-blocks, with the options of <InnerBlocks /> in use. tests/e2e
    checks the editor gets them (a paragraph from the template, the allowed
    blocks) and the page gets the inner blocks in the tag's wrapper.
--}}
<div {!! get_block_wrapper_attributes() !!}>
    <h3 class="e2e-inner-card__title">{{ __('Inner Card', 'e2e-blocks') }}</h3>
    <InnerBlocks
        class="e2e-inner-card__body"
        allowedBlocks="{{ json_encode(['core/paragraph', 'core/heading']) }}"
        template="{{ json_encode([['core/paragraph', ['placeholder' => 'E2E inner placeholder']]]) }}"
    />
</div>
