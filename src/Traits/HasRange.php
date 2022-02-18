<?php
namespace Traversify\Traits;

use Exception;
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
    public function scopeRange(Builder $query, array $range = [])
    {
        if (!$ranges = $this->range) {
            throw new Exception('No column configured to be ranged');
        }

        if (empty($range)) {
            return;
        }

        if (is_null($query->getQuery()->columns)) {
            $query->select(sprintf('%s.*', $query->getModel()->getTable()));
        }

        foreach($ranges as $rangeable) {

            if (in_array($rangeable, array_keys($range))) {

                $this->createRangeQuery($query, $rangeable, $range[$rangeable]);
            }
        }
    }

    /**
     * Create Range Query
     *
     * @param Builder $query
     * @param string $rangeable
     * @param array $value
     * @return mixed
     */
    private function createRangeQuery(Builder $query, string $rangeable, array $value)
    {
        $rangeables = explode('.', $rangeable);
        $rangeColumn = array_pop($rangeables);

        $motherOfAllRelationsTable = (new self)->getTable();
        $lastRelationTable = $motherOfAllRelationsTable;
        $currentModel = new self;

        if (count($rangeables)) {

            foreach ($rangeables as $index => $relationName) {

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

                    if (array_key_last($rangeables) == $index) {
                        $lastRelationTable = $alias ?? $tableName;
                    }
                }
            }
        }

        $query->whereBetween($lastRelationTable.'.'.$rangeColumn, $value);
    }
}
