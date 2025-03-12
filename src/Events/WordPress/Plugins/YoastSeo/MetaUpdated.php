<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\YoastSeo;

/**
 * Event fired when a Yoast SEO meta is updated.
 *
 * This event is triggered when an existing Yoast SEO meta field is updated.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class MetaUpdated extends MetaEvent {}
