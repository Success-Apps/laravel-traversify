<?php
namespace Traversify\Traits;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

trait HasLoadCount
{
    /**
     * Load Counts
     *
     * @param Builder $query
     * @param array $load
     * @throws Exception
     */
    public function scopeLoadCount(Builder $query, array $load = [])
    {
        if (!$loadCounts = $this->loadCount) {
            throw new Exception('No column configured to be load-counted - ' . $this::class);
        }

        if (empty($load)) {
            return;
        }

        $clean = [];

        foreach($loadCounts as $count) {
            if (in_array($count, array_values($load))) {
                $query->withCount($count)->withCasts([Str::snake($count) . '_count' => 'integer']);
            }
        }
    }
}
