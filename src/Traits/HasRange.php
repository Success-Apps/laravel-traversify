<?php
namespace Traversify\Traits;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Builder;

trait HasRange
{
    use HasLeftJoin;

    /**
     * Initialize ranges
     *
     * @param Builder $query
     * @param array $range
     * @return void
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function scopeRange(Builder $query, array $range = []): void
    {
        if (!$rangeFilters = $this->range) {
            Log::error('No column configured to be ranged - ' . $this::class);
            return;
        }

        if (empty($range)) {
            return;
        }

        if (is_null($query->getQuery()->columns)) {
            $query->select(sprintf('%s.*', $query->getModel()->getTable()));
        }

        foreach($range as $key => $value) {

            if (!in_array($key, $rangeFilters)) {
                continue;
            }

            $rangeBoundaries = $value;
            if (count($rangeBoundaries) != 2) {
                continue;
            }

            $currentModel = new self;
            $relationsSplit = explode('.', $key);
            $rangeColumn = array_pop($relationsSplit);
            $result['last_relation_table'] = $currentModel->getTable();

            if (count($relationsSplit)) {
                $result = $this->performJoinLogic($query, $currentModel, $relationsSplit, $currentModel->getTable(), $currentModel);
            }

            $wheres = $query->getQuery()->wheres;
            $finalColumn = $result['last_relation_table'].'.'.$rangeColumn;

            $filteredWheres = Arr::where($wheres, function ($item) {
                return $item['type'] === 'between' && $item['column'] === $finalColumn && $item['values'] === $value && $item['not'] === false;
            });

            if (!count($filteredWheres)) {
                $query->whereBetween($finalColumn, $rangeBoundaries);
            }

        }
    }
}
