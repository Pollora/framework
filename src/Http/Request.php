<?php

namespace Pollen\Http;

use Illuminate\Http\Request as BaseRequest;
use Pollen\Models\Post;
use Pollen\Support\WordPress;

/**
 * Extend the Request class to add some WordPress-related helpers.
 *
 * @author Jordan Doyle <jordan@doyle.wf>
 */
class Request extends BaseRequest
{
    /**
     * @var Post
     */
    private $post;

    /**
     * Get the Post instance this request has asked for.
     *
     * @return Post
     */
    public function page()
    {
        return $this->post ?: $this->post = Post::find(WordPress::id());
    }
}
