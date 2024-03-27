<?php
namespace Traversify\Traits;

use Exception;
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
        if (!$ranges = $this->range) {
            Log::error('No column configured to be ranged - ' . $this::class);
            return;
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
    private function createRangeQuery(Builder $query, string $rangeable, array $value): void
    {
        $rangeables = explode('.', $rangeable);
        $rangeColumn = array_pop($rangeables);

        $motherOfAllModelsTable = (new self)->getTable();
        $lastRelationTable = $motherOfAllModelsTable;
        $currentModel = new self;

        if (count($rangeables)) {

            foreach ($rangeables as $index => $relationName) {

                $alias = null;

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

                if (array_key_last($rangeables) == $index) {
                    $lastRelationTable = $alias ?? $tableName;
                }
            }
        }

        $query->whereBetween($lastRelationTable.'.'.$rangeColumn, $value);
    }
}
