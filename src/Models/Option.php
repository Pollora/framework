<?php

namespace Pollen\Models;

use Illuminate\Database\Eloquent\Model;
use Pollen\Support\WordPress;
use Watson\Rememberable\Rememberable;

/**
 * Table containing all WordPress options.
 *
 * @author Jordan Doyle <jordan@doyle.wf>
 */
class Option extends Model
{
    use Rememberable;

    public $timestamps = false;

    protected $primaryKey = 'option_id';

    protected $table = DB_PREFIX.'options';

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
            $this->setTable(DB_PREFIX.WordPress::getSiteId().'_options');
        }

        // enable caching if the user has opted for it in their configuration
        if (config('wordpress.caching')) {
            $this->rememberFor = config('wordpress.caching');
        } else {
            unset($this->rememberFor);
        }
    }

    /**
     * Get an option by its name.
     *
     *
     * @return mixed
     */
    public static function findByName($name)
    {
        return static::where('option_name', $name)->first();
    }

    /**
     * Get the option's value.
     *
     *
     * @return mixed
     */
    public function getOptionValueAttribute($value)
    {
        return is_serialized($value) ? unserialize($value) : $value;
    }
}
