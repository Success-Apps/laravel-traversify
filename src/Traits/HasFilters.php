<?php
namespace Traversify\Traits;

use Exception;
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
    public function scopeFilter(Builder $query, array $filter = [])
    {
        if (!$filters = $this->filters) {
            throw new Exception('No column configured to be filtered - ' . $this::class);
        }

        if (empty($filter)) {
            return;
        }

        if (is_null($query->getQuery()->columns)) {
            $query->select(sprintf('%s.*', $query->getModel()->getTable()));
        }

        foreach($filters as $filterable) {

            if(in_array($filterable, array_keys($filter))) {

                $value = is_array($filter[$filterable]) ? $filter[$filterable] : [$filter[$filterable]];

                $this->createFilterQuery($query, $filterable, $value);
            }
        }
    }

    /**
     * Generate filter query
     *
     * @param Builder $query
     * @param string $filterable
     * @param array $value
     * @return void
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    private function createFilterQuery(Builder $query, string $filterable, array $value)
    {
        $filterables = explode('.', $filterable);
        $filterColumn = array_pop($filterables);

        $motherOfAllRelationsTable = (new self)->getTable();
        $lastRelationTable = $motherOfAllRelationsTable;
        $currentModel = new self;

        if (count($filterables)) {

            foreach ($filterables as $index => $relationName) {

                if ($relationName != $motherOfAllRelationsTable) {
                    $relation = $currentModel->{$relationName}();
                    $currentModel = $relation->getRelated();
                    $tableName = $currentModel->getTable();

                    $alias = null;

                    if (!$this->relationshipIsAlreadyJoined($query, $tableName)) {
                        if ($tableName == $motherOfAllRelationsTable) {
                            $alias = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz"), 0, 3) . time();
                        }

                        $this->performJoinForEloquent($query, $relation, $alias);
                    } else {
                        $tableName = $this->getTableOrAliasForModel($query, $tableName);
                    }

                    if (array_key_last($filterables) == $index) {
                        $lastRelationTable = $alias ?? $tableName;
                    }
                }
            }
        }

        /**
         * Make True or False
         */
        foreach($value as $item) {
            switch($item) {
                case 'true':
                    $item = true;
                    break;
                case 'false':
                    $item = false;
                    break;
                default:
                    break;
            }
        }

        if (count($value) > 1) {
            $query->whereIn($lastRelationTable.'.'.$filterColumn, $value);
        } else {
            $query->where($lastRelationTable.'.'.$filterColumn, '=', $value);
        }
    }
}
