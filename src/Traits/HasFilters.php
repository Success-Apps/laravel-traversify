<?php
namespace Traversify\Traits;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Builder;

trait HasFilters
{
    use HasLeftJoin;

    /**
     * Initialize filters
     *
     * @param Builder $query
     * @param array $filter
     * @return void
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function scopeFilter(Builder $query, array $filter = []): void
    {
        if (!$filterFilters = $this->filters) {
            Log::error('No column configured to be filtered - ' . $this::class);
            return;
        }

        if (empty($filter)) {
            return;
        }

        if (is_null($query->getQuery()->columns)) {
            $query->select(sprintf('%s.*', $query->getModel()->getTable()));
        }

        sort($filterFilters);

        foreach ($filter as $key => $value) {

            if (!in_array($key, $filterFilters)) {
                continue;
            }

            $currentModel = new self;
            $relationsSplit = explode('.', $key);
            $filterColumn = array_pop($relationsSplit);
            $result['last_relation_table'] = $currentModel->getTable();

            if (count($relationsSplit)) {
                $result = $this->performJoinLogic($query, $currentModel, $relationsSplit, $currentModel->getTable(), $currentModel);
            }

            $wheres = $query->getQuery()->wheres;
            $finalColumn = $result['last_relation_table'].'.'.$filterColumn;

            switch (true) {
                case ($value === '{null}'):
                    $filteredWheres = Arr::where($wheres, function ($item) use ($finalColumn) {
                        return $item['type'] === 'Null' && $item['column'] === $finalColumn;
                    });

                    if (!count($filteredWheres)) {
                        $query->whereNull($finalColumn);
                    }
                    break;

                case ($value === '{!null}'):
                    $filteredWheres = Arr::where($wheres, function ($item) use ($finalColumn) {
                        return $item['type'] === 'NotNull' && $item['column'] === $finalColumn;
                    });

                    if (!count($filteredWheres)) {
                        $query->whereNotNull($finalColumn);
                    }
                    break;

                case (is_array($value)):
                    $filteredWheres = Arr::where($wheres, function ($item) use ($finalColumn, $value) {
                        return $item['type'] === 'In' && $item['column'] === $finalColumn && $item['value'] === $value;
                    });

                    if (!count($filteredWheres)) {
                        $query->whereIn($finalColumn, $value);
                    }
                    break;

                default:
                    $filteredWheres = Arr::where($wheres, function ($item) use ($finalColumn, $value) {
                        return $item['type'] === 'Basic' && $item['column'] === $finalColumn && $item['operator'] === '=' && $item['value'] === $value;
                    });

                    if (!count($filteredWheres)) {
                        $query->where($finalColumn, '=', $value);
                    }
                    break;
            }

        }

    }
}
