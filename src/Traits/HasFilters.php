<?php
namespace Traversify\Traits;

use Exception;
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
    public function scopeFilter(Builder $query, Array $filter = [])
    {
        if (!$filters = $this->filters) {
            throw new Exception('No column configured to be filtered');
        }

        if (empty($filter)) {
            return;
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
     * @param array $filterables
     * @param string $value
     * @return void
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    private function createFilterQuery(Builder $query, String $filterable, Array $value)
    {
        $filterables = explode('.', $filterable);
        $filterColumn = array_pop($filterable);

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
                            $alias = 'A' . time();
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

        return $query->whereIn("$lastRelationTable.$filterColumn", $value);
    }
}
