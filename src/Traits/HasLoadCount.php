<?php
namespace Traversify\Traits;

use Exception;
use Illuminate\Database\Eloquent\Builder;

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
            throw new Exception('No column configured to be load-counted');
        }

        if (empty($load)) {
            return;
        }

        $clean = [];

        foreach($loadCounts as $count) {

            if (in_array($count, array_values($load))) {

                $clean[] = $count;
            }
        }

        if (!empty($clean)) {
            $query->withCount($clean);
        }
    }
}
