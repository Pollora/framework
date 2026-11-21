<?php

declare(strict_types=1);

namespace Pollora\Doctor\Infrastructure\Checks;

use Closure;
use Pollora\Doctor\Domain\Contracts\CheckInterface;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Domain\Models\CheckResult;
use Pollora\WordPress\Multisite;

/**
 * A WordPress network works through a bridge, not through full support.
 *
 * Nothing breaks on its own when a site becomes a network: the front and the
 * REST API of every site answer. What does not work fails somewhere else — a
 * sub-site's administration lands on the Laravel 404 — so the limits are named
 * here, once, rather than found one at a time.
 */
final readonly class MultisiteCheck implements CheckInterface
{
    /**
     * @param  (Closure(): bool)|null  $isNetwork  Tells whether the project is a network; the configuration answers by default
     * @param  (Closure(): bool)|null  $isSubdomainInstall  Tells whether sites live on subdomains; the constant answers by default
     */
    public function __construct(
        private ?Closure $isNetwork = null,
        private ?Closure $isSubdomainInstall = null,
    ) {}

    public function id(): string
    {
        return 'multisite';
    }

    public function label(): string
    {
        return 'Multisite';
    }

    public function runsIn(): array
    {
        return [RunContext::Console, RunContext::Http];
    }

    public function run(RunContext $context): CheckResult
    {
        $network = $this->isNetwork instanceof Closure ? ($this->isNetwork)() : Multisite::isConfigured();

        if (! $network) {
            return CheckResult::skipped('This is a single site.');
        }

        $details = [
            "The front and the REST API of each site work; the main site's administration is /cms/wp-admin/.",
            "Laravel queries that read WordPress tables directly (the installed check, the allowed plugins of /api/ requests) always read the main site's tables, whichever site is served.",
            'The discovery cache is keyed by path, not by site: it is not verified for sites that activate different themes or plugins.',
            'Not verified: subdomain networks, WP-CLI with --url, WooCommerce, Laravel sessions and cookies across sites.',
        ];

        $subdomains = $this->isSubdomainInstall instanceof Closure
            ? ($this->isSubdomainInstall)()
            : (defined('SUBDOMAIN_INSTALL') && (bool) constant('SUBDOMAIN_INSTALL'));

        if (! $subdomains) {
            array_unshift($details, 'Sub-directory network: the administration of a sub-site (/site/wp-admin/, /site/wp-login.php) is not served by WordPress and ends on a Laravel page.');
        }

        return CheckResult::warning(
            'A network is detected: Pollora supports it in a limited way.',
            $details,
            'Read "Multisite: what works, what does not" in the documentation; administer sub-sites from the network admin (/cms/wp-admin/network/).',
        );
    }
}
