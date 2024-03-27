<?php

namespace Traversify;

use Traversify\Traits\HasSort;
use Traversify\Traits\HasRange;
use Traversify\Traits\HasSearch;
use Traversify\Traits\HasFilters;
use Traversify\Traits\HasAutoload;
use Traversify\Traits\HasLoadCount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

trait Traversify
{
    use HasFilters, HasRange, HasSearch, HasSort, HasAutoload, HasLoadCount;

    /**
     * All-in-one solution to create indexed endpoints fast
     * Out of the box support:
     * Search, sort, filter, load, range
     *
     * @param mixed $query
     * @param mixed $request
     * @return mixed
     */
    public function scopeTraversify(Builder $query, $request)
    {
        return self::traverse($query, $request);
    }

    public static function traverse(Builder $query, $request)
    {
        if (in_array(SoftDeletes::class, class_uses_recursive(self::class)) && $request->has('trashed') && $request->trashed == 1) {
            $query->onlyTrashed();
        }

        if ($request->has('search') && is_string($request->search)) {
            $query->search($request->search);
        }

        if ($request->has('filter') && is_array($request->filter)) {
            $query->filter($request->filter);
        }

        if ($request->has('range') && is_array($request->range)) {
            $query->range($request->range);
        }

        if ($request->has('autoload') && is_array($request->autoload)) {
            $query->autoload($request->autoload);
        }

        if ($request->has('loadCount') && is_array($request->loadCount)) {
            $query->loadCount($request->loadCount);
        }

        if ($request->has('sort') && is_array($request->sort)) {
            $query->sort($request->sort);
        }

        return $query;
    }
}
