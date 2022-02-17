<?php
namespace Traversify\Traits;

use Exception;
use Illuminate\Database\Eloquent\Builder;

trait HasLoadCount
{
    public function scopeLoadCount(Builder $query, Array $load = [])
    {
        if (!$loadCounts = $this->loadCount) {
            throw new Exception('No column configured to be load-counted');
        }

        if (empty($load)) {
            return;
        }

        foreach($loadCounts as $count) {

            if(in_array($count, array_values($load))) {

                $query->withCount($count);
            }
        }
    }
}
