<?php

declare(strict_types=1);

namespace Pollora\Block\Infrastructure\Services;

/**
 * Whether blocks are being rendered for the block editor's preview.
 *
 * The editor previews a dynamic block through the core `block-renderer` REST
 * route, which calls the block's render callback. During that request a
 * template renders for the editor: `<InnerBlocks />` stays in its output for
 * the editor to turn into editable blocks, and the template gets
 * `$isPreview`.
 */
final class BlockPreview
{
    /**
     * REST route prefix of the core block renderer.
     */
    private const string RENDERER_ROUTE = '/wp/v2/block-renderer/';

    private bool $isActive = false;

    /**
     * Start the preview when the REST request is the block renderer's.
     *
     * Hooked on `rest_request_before_callbacks`.
     *
     * @param  mixed  $response  The response so far, untouched
     * @param  mixed  $handler  The route handler
     * @param  mixed  $request  The REST request
     * @return mixed The response, untouched
     */
    public function start(mixed $response, mixed $handler, mixed $request): mixed
    {
        if ($request instanceof \WP_REST_Request && str_starts_with($request->get_route(), self::RENDERER_ROUTE)) {
            $this->isActive = true;
        }

        return $response;
    }

    /**
     * End the preview once the REST callbacks have run.
     *
     * Hooked on `rest_request_after_callbacks`.
     *
     * @param  mixed  $response  The response, untouched
     * @return mixed The response, untouched
     */
    public function end(mixed $response): mixed
    {
        $this->isActive = false;

        return $response;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }
}
