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
    public function scopeFilter(Builder $query, array $filter = []): void
    {
        if (!$filters = $this->filters) {
            Log::error('No column configured to be filtered - ' . $this::class);
            return;
        }

        if (empty($filter)) {
            return;
        }

        if (is_null($query->getQuery()->columns)) {
            $query->select(sprintf('%s.*', $query->getModel()->getTable()));
        }

        foreach($filters as $filterable) {
            if(in_array($filterable, array_keys($filter))) {
                $this->createFilterQuery($query, $filterable, $filter[$filterable]);
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
    private function createFilterQuery(Builder $query, string $filterable, mixed $value): void
    {
        $filterables = explode('.', $filterable);
        $filterColumn = array_pop($filterables);

        $motherOfAllModelsTable = (new self)->getTable();
        $lastRelationTable = $motherOfAllModelsTable;
        $currentModel = new self;

        if (count($filterables)) {

            Log::info($filterables);

            foreach ($filterables as $index => $relationName) {

                $alias = null;

                Log::info([$relationName, $index]);

                if (strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $relationName)) !== $motherOfAllModelsTable) {

                    $relation = $currentModel->{$relationName}();
                    $currentModel = $relation->getRelated();
                    $tableName = $currentModel->getTable();
                    $relationshipJoined = $this->relationshipIsAlreadyJoined($query, $tableName, $relation);

                    if ($relationshipJoined['table_exists']) {

                        if (!($relationshipJoined['tables_joined'] && $relationshipJoined['with_columns'])) {
                            $alias = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz"), 0, 3) . time();
                            $this->performJoinForEloquent($query, $relation, $alias);
                        } else {
                            $tableName = $this->getTableOrAliasForModel($query, $tableName);
                        }

                    } else {

                        if ($tableName === $motherOfAllModelsTable) {
                            $alias = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz"), 0, 3) . time();
                        }
                        $this->performJoinForEloquent($query, $relation, $alias);

                    }

                } else {

                    if ($index > 0) {
                        $alias = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz"), 0, 3) . time();
                        $this->performJoinForEloquent($query, $relation, $alias);
                    } else {
                        $tableName = $motherOfAllModelsTable;
                    }

                }

                if (array_key_last($filterables) == $index) {
                    $lastRelationTable = $alias ?? $tableName;
                }

            }
        }

        switch (true) {
            case ($value === '{null}'):
                $query->whereNull($lastRelationTable.'.'.$filterColumn);
                break;

            case ($value === '{!null}'):
                $query->whereNotNull($lastRelationTable.'.'.$filterColumn);
                break;

            case (is_array($value)):
                $query->whereIn($lastRelationTable.'.'.$filterColumn, $value);
                break;

            default:
                $query->where($lastRelationTable.'.'.$filterColumn, '=', $value);
                break;
        }
    }
}
