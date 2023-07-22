<?php

declare(strict_types=1);

namespace Pollen\Models;

use Illuminate\Database\Eloquent\Model;
use Pollen\Support\WordPress;
use Watson\Rememberable\Rememberable;

/**
 * Taxonomy for the terms in the CMS.
 *
 * @author Jordan Doyle <jordan@doyle.wf>
 */
class TermTaxonomy extends Model
{
    use Rememberable;

    public $timestamps = false;

    protected $primaryKey = 'term_taxonomy_id';

    protected $table = DB_PREFIX.'term_taxonomy';

    /**
     * Length of time to cache this model for.
     *
     * @var int
     */
    protected $rememberFor;

    /**
     * Create a new Eloquent model instance.
     *
     *
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // Set the current table to the site's own table if we're in a multisite
        if (WordPress::multisite() && (WordPress::getSiteId() !== 0 && WordPress::getSiteId() !== 1)) {
            $this->setTable(DB_PREFIX.WordPress::getSiteId().'_term_taxonomy');
        }

        // enable caching if the user has opted for it in their configuration
        if (config('wordpress.caching')) {
            $this->rememberFor = config('wordpress.caching');
        } else {
            unset($this->rememberFor);
        }
    }
}
