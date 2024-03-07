<?php
namespace Traversify\Traits;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\SoftDeletes;

trait HasLeftJoin
{
    /**
     * Perform the JOIN clause for eloquent joins.
     */
    public function performJoinForEloquent(Builder $query, $relation, $alias = null)
    {
        $joinType = 'leftJoin';

        switch (true) {
            case ($relation instanceof BelongsTo):
                $this->performJoinForEloquentForBelongsTo($query, $relation, $joinType, $alias);
                break;
            case ($relation instanceof BelongsToMany):
                $this->performJoinForEloquentForBelongsToMany($query, $relation, $joinType, $alias);
                break;
            case ($relation instanceof MorphOne || $relation instanceof MorphMany || $relation instanceof MorphOneOrMany || $relation instanceof MorphTo || $relation instanceof MorphPivot || $relation instanceof MorphToMany):
                $this->performJoinForEloquentForMorph($query, $relation, $joinType, $alias);
                break;
            case ($relation instanceof HasMany || $relation instanceof HasOne || $relation instanceof HasOneOrMany):
                $this->performJoinForEloquentForHasMany($query, $relation, $joinType, $alias);
                break;
            case ($relation instanceof HasManyThrough || $relation instanceof HasOneThrough):
                $this->performJoinForEloquentForHasManyThrough($query, $relation, $joinType, $alias);
                break;
            default:
                Log::info("No Relationship Class found => ".$relation::class);
                throw new Exception("No Relationship Class found => ".$relation::class);
                break;
        }
    }

    /**
     * Perform the JOIN clause for the BelongsTo (or similar) relationships.
     */
    protected function performJoinForEloquentForBelongsTo(Builder $query, $relation, $joinType, $alias)
    {
        $relationTable = $relation->getModel()->getTable();
        $parentTable = $relation->getParent()->getTable();
        $tableOrAlias = $relationTable;

        if ($alias) {
            $tableOrAlias = $alias;
            $relationTable = $relationTable . ' AS ' . $alias;
        }

        $query->{$joinType}($relationTable, function ($join) use ($relation, $tableOrAlias) {

            $join->on(
                "{$tableOrAlias}.{$relation->getOwnerKeyName()}",
                '=',
                $relation->getQualifiedForeignKeyName()
            );

            $ignoredKeys = [$relation->getQualifiedOwnerKeyName(), $relation->getQualifiedForeignKeyName()];
            $this->applyExtraConditions($relation, $join, $ignoredKeys, $tableOrAlias);

            if ($this->usesSoftDeletes($relation->getModel())) {
                $join->whereNull("{$tableOrAlias}.{$relation->getModel()->getDeletedAtColumn()}");
            }
        });
    }

    /**
     * Perform the JOIN clause for the BelongsToMany (or similar) relationships.
     */
    protected function performJoinForEloquentForBelongsToMany(Builder $query, $relation, $joinType, $alias)
    {
        $pivotTable = $relation->getRelated()->getTable();
        $relationTable = $relation->getModel()->getTable();
        $tableOrAlias = $relationTable;

        if ($alias) {
            $tableOrAlias = $alias;
            $relationTable = $relationTable . ' AS ' . $alias;
        }

        $query->{$joinType}($pivotTable, function ($join) use ($relation, $pivotTable) {

            $join->on(
                $relation->getQualifiedForeignPivotKeyName(),
                '=',
                $relation->getQualifiedParentKeyName()
            );

            $ignoredKeys = [$relation->getQualifiedForeignPivotKeyName(), $relation->getQualifiedParentKeyName()];
            $this->applyExtraConditions($relation, $join, $ignoredKeys);

            if ($this->usesSoftDeletes($relation->getRelated())) {
                $join->whereNull("{$pivotTable}.{$relation->getRelated()->getDeletedAtColumn()}");
            }
        });

        $query->{$joinType}($relationTable, function ($join) use ($relation, $relationTable, $tableOrAlias) {

            $join->on(
                "{$tableOrAlias}.{$relation->getModel()->getKeyName()}",
                '=',
                $relation->getQualifiedRelatedPivotKeyName()
            );

//            $ignoredKeys = ["{$relationTable}.{$relation->getModel()->getKeyName()}", $relation->getQualifiedRelatedPivotKeyName()];
//            $this->applyExtraConditions($relation, $join, $ignoredKeys, $tableOrAlias);
//
//            if ($this->usesSoftDeletes($relation->getModel())) {
//
//                $join->whereNull("{$tableOrAlias}.{$relation->getModel()->getDeletedAtColumn()}");
//            }
        });
    }

    /**
     * Perform the JOIN clause for the Morph (or similar) relationships.
     */
    protected function performJoinForEloquentForMorph(Builder $query, $relation, $joinType, $alias)
    {
        $parentTable = $relation->getParent()->getTable();
        $relationTable = $relation->getModel()->getTable();
        $tableOrAlias = $relationTable;

        if ($alias) {
            $tableOrAlias = $alias;
            $relationTable = $relationTable . ' AS ' . $alias;
        }

        $query->{$joinType}($relationTable, function ($join) use ($relation, $tableOrAlias, $parentTable) {

            $join->on(
                "{$tableOrAlias}.{$relation->getForeignKeyName()}",
                '=',
                "{$parentTable}.{$relation->getLocalKeyName()}"
            );

            $ignoredKeys = [$relation->getQualifiedForeignKeyName(), "{$parentTable}.{$relation->getLocalKeyName()}"];
            $this->applyExtraConditions($relation, $join, $ignoredKeys, $tableOrAlias);

            if ($this->usesSoftDeletes($relation->getModel())) {
                $join->whereNull("{$tableOrAlias}.{$relation->getModel()->getDeletedAtColumn()}");
            }
        });

    }

    /**
     * Perform the JOIN clause for the HasMany (or similar) relationships.
     */
    protected function performJoinForEloquentForHasMany(Builder $query, $relation, $joinType, $alias)
    {
        $parentTable = $relation->getParent()->getTable();
        $relationTable = $relation->getRelated()->getTable();
        $tableOrAlias = $relationTable;

        if ($alias) {
            $tableOrAlias = $alias;
            $relationTable = $relationTable . ' AS ' . $alias;
        }

        $query->{$joinType}($relationTable, function ($join) use ($relation, $relationTable, $parentTable, $tableOrAlias) {

            $join->on(
                "{$parentTable}.{$relation->getLocalKeyName()}",
                '=',
                "{$tableOrAlias}.{$relation->getForeignKeyName()}"
            );

            $ignoredKeys = ["{$parentTable}.{$relation->getLocalKeyName()}", $relation->getQualifiedForeignKeyName()];
            $this->applyExtraConditions($relation, $join, $ignoredKeys, $tableOrAlias);

            if ($this->usesSoftDeletes($relation->getRelated())) {

                $join->whereNull("{$relationTable}.{$relation->getRelated()->getDeletedAtColumn()}");
            }
        });
    }

    /**
     * Perform the JOIN clause for the HasManyThrough relationships.
     */
    protected function performJoinForEloquentForHasManyThrough(Builder $query, $relation, $joinType, $alias)
    {
        $throughTable = $relation->getParent()->getTable();
        $farTable = $relation->getRelated()->getTable();
        $tableOrAlias = $farTable;

        if ($alias) {
            $tableOrAlias = $alias;
            $relationTable = $farTable . ' AS ' . $alias;
        }

        $query->{$joinType}($throughTable, function ($join) use ($relation, $throughTable, $farTable) {

            $join->on(
                "{$throughTable}.{$relation->getFirstKeyName()}",
                '=',
                $relation->getQualifiedLocalKeyName()
            );


            $ignoredKeys = ["{$throughTable}.{$relation->getFirstKeyName()}", $relation->getQualifiedLocalKeyName(), "{$farTable}.{$relation->getForeignKeyName()}", "{$throughTable}.{$relation->getSecondLocalKeyName()}"];
            $this->applyExtraConditions($relation, $join, $ignoredKeys);

            if ($this->usesSoftDeletes($relation->getParent())) {
                $join->whereNull("{$throughTable}.{$relation->getParent()->getDeletedAtColumn()}");
            }
        });

        $query->{$joinType}($tableOrAlias, function ($join) use ($relation, $throughTable, $farTable, $tableOrAlias) {

            $join->on(
                "{$tableOrAlias}.{$relation->getForeignKeyName()}",
                '=',
                "{$throughTable}.{$relation->getSecondLocalKeyName()}"
            );

//            $ignoredKeys = ["{$throughTable}.{$relation->getForeignKeyName()}", $relation->getQualifiedLocalKeyName(), "{$farTable}.{$relation->getForeignKeyName()}", "{$throughTable}.{$relation->getSecondLocalKeyName()}"];
//            $this->applyExtraConditions($relation, $join, $ignoredKeys, $tableOrAlias);
//
//            if ($this->usesSoftDeletes($relation->getModel())) {
//
//                $join->whereNull("{$tableOrAlias}.{$relation->getModel()->getDeletedAtColumn()}");
//            }
        });

    }

    /**
     * Check if query has Join
     *
     * @param Builder $query
     * @param string $tableName
     * @return bool
     */
    private function relationshipIsAlreadyJoined(Builder $query, string $tableName, $relation): bool
    {
        $existingJoins = collect($query->getQuery()->joins);
        $parentTable = $relation->getParent()->getTable();

        $searchKey = null;
        $searchValue = null;

        switch (true) {
            case ($relation instanceof BelongsTo):
                $pivotTable = $relation->getRelated()->getTable();
                $relationTable = $relation->getModel()->getTable();
                $tableOrAlias = $relationTable;

                $searchKey = "{$tableOrAlias}.{$relation->getOwnerKeyName()}";
                $searchValue = $relation->getQualifiedForeignKeyName();
                break;
            case ($relation instanceof BelongsToMany):
                $pivotTable = $relation->getRelated()->getTable();
                $relationTable = $relation->getModel()->getTable();
                $tableOrAlias = $relationTable;

                $searchKey = "{$tableOrAlias}.{$relation->getModel()->getKeyName()}";
                $searchValue = $relation->getQualifiedRelatedPivotKeyName();
                break;
            case ($relation instanceof MorphOne || $relation instanceof MorphMany || $relation instanceof MorphOneOrMany || $relation instanceof MorphTo || $relation instanceof MorphPivot || $relation instanceof MorphToMany):
                $parentTable = $relation->getParent()->getTable();
                $relationTable = $relation->getModel()->getTable();
                $tableOrAlias = $relationTable;

                $searchKey = "{$tableOrAlias}.{$relation->getForeignKeyName()}";
                $searchValue = "{$parentTable}.{$relation->getLocalKeyName()}";
                break;
            case ($relation instanceof HasMany || $relation instanceof HasOne || $relation instanceof HasOneOrMany):
                $parentTable = $relation->getParent()->getTable();
                $relationTable = $relation->getModel()->getTable();
                $tableOrAlias = $relationTable;

                $searchKey = "{$parentTable}.{$relation->getLocalKeyName()}";
                $seearchValue = "{$tableOrAlias}.{$relation->getForeignKeyName()}";
                break;
            case ($relation instanceof HasManyThrough || $relation instanceof HasOneThrough):
                $throughTable = $relation->getParent()->getTable();
                $farTable = $relation->getRelated()->getTable();
                $tableOrAlias = $farTable;

                $searchKey1 = "{$throughTable}.{$relation->getFirstKeyName()}";
                $seearchValue1 = $relation->getQualifiedLocalKeyName();
                $searchKey2 = "{$tableOrAlias}.{$relation->getForeignKeyName()}";
                $seearchValue2 = "{$throughTable}.{$relation->getSecondLocalKeyName()}";
                return $this->checkForJoinColumnsInExisitingQueryJoins($existingJoins, $searchKey1, $searchValue1, $tableName) && $this->checkForJoinColumnsInExisitingQueryJoins($existingJoins, $searchKey2, $searchValue2, $tableName);
                break;
            default:
                Log::info("No Relationship Class found => ".$relation::class);
                throw new Exception("No Relationship Class found => ".$relation::class);
                break;
        }

        if ($searchKey && $searchValue) {
            return $this->checkForJoinColumnsInExisitingQueryJoins($existingJoins, $searchKey, $searchValue, $tableName);
        }

        return false;
    }

    /**
     * Check if columns / values exist in query joins
     *
     * @param $existingJoins
     * @param $searchKey
     * @param $searchValue
     * @return bool
     */
    public function checkForJoinColumnsInExisitingQueryJoins($existingJoins, $searchKey, $searchValue, $tableName): bool
    {
        // Use 'some' instead of 'every' to check if any item meets the condition
        return $existingJoins->some(function ($join) use ($searchKey, $searchValue) {
            if (!($join instanceof Illuminate\Database\Query\JoinClause)) {
                return false; // Non-JoinClause objects don't match
            }

            $wheres = $join->wheres;
            $tableJoin = $join->getTable();

            // Return boolean directly from array_filter condition
            return array_filter($wheres, function ($where) use ($searchKey, $searchValue, $tableJoin, $tableName) {
                    return $where[$searchKey] === $searchValue && $tableJoin === $tableName;
                }) !== [];
        });
    }

    /**
     * Check if this table been already joined already
     *
     * @param Builder $query
     * @param string $tableName
     * @return bool
     */
    private function tableIsAlreadyJoined(Builder $query, string $tableName)
    {
        $tableJoins = collect($query->getQuery()->joins)->pluck('table');

        foreach($tableJoins as $join) {
            $join = strtolower($join);

            if (str_contains($join, ' as ')) {
                $explode = explode(' as ', $join);
                $join = $explode[0];
            }

            if ($join == $tableName) {
                return true;
            }
        };

        return false;
    }

    /**
     * Extra conditions on Relationships
     *
     * @return \Closure
     */
    public function applyExtraConditions($relation, $join, $ignoredKeys, $alias = null)
    {
        foreach ($relation->getQuery()->getQuery()->wheres as $index => $condition) {

            if (! in_array($condition['type'], ['Basic', 'Null', 'NotNull', 'Nested'])) {
                continue;
            }

            if (in_array($condition['type'], ['Null', 'NotNull']) && in_array($condition['column'], $ignoredKeys)) {
                continue;
            }

            if ($condition['type'] == 'Nested') {

                $method = "apply{$condition['type']}Condition";
                $this->$method($join, $condition, $ignoredKeys, $alias);
            } else {

                $method = "apply{$condition['type']}Condition";
                $this->$method($join, $condition, $alias);
            }
        }
    }

    /**
     * Apply relationship conditions
     *
     * @param $join
     * @param $condition
     */
    public function applyBasicCondition($join, $condition, $alias = null)
    {
        $column = $condition['column'];
        if ($alias) {
            $parts = explode('.', $condition['column']);

            if (count($parts) == 2) {
                $column = "{$alias}.{$parts[1]}";
            } else {
                $column = "{$alias}.{$parts[0]}";
            }
        }

        $join->where($column, $condition['operator'], $condition['value']);
    }

    public function applyNullCondition($join, $condition, $alias = null)
    {
        $column = $condition['column'];
        if ($alias) {
            $parts = explode('.', $condition['column']);

            if (count($parts) == 2) {
                $column = "{$alias}.{$parts[1]}";
            } else {
                $column = "{$alias}.{$parts[0]}";
            }
        }

        $join->whereNull($column);
    }

    public function applyNotNullCondition($join, $condition, $alias = null)
    {
        $column = $condition['column'];
        if ($alias) {
            $parts = explode('.', $condition['column']);

            if (count($parts) == 2) {
                $column = "{$alias}.{$parts[1]}";
            } else {
                $column = "{$alias}.{$parts[0]}";
            }
        }

        $join->whereNotNull($column);
    }

    public function applyNestedCondition($join, $condition, $ignoredKeys, $alias = null)
    {
        foreach ($condition['query']->wheres as $condition) {

            if (! in_array($condition['type'], ['Basic', 'Null', 'NotNull', 'Nested'])) {
                continue;
            }

            if (in_array($condition['type'], ['Null', 'NotNull']) && in_array($condition['column'], $ignoredKeys)) {
                continue;
            }

            $method = "apply{$condition['type']}Condition";
            $this->$method($join, $condition, $alias);
        }
    }

    /**
     * Checks if the relationship model uses soft deletes.
     */
    public function usesSoftDeletes($model)
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model));
    }

    /**
     * Check if query has Join
     *
     * @param Builder $query
     * @param string $tableName
     * @return false|string
     */
    private function getTableOrAliasForModel(Builder $query, string $tableName)
    {
        $tableJoins = collect($query->getQuery()->joins)->pluck('table');

        $alias = null;

        foreach($tableJoins as $join) {
            $join = strtolower($join);

            if (str_contains($join, ' as ')) {

                $explode = explode(' as ', $join);
                $joinTable = $explode[0];
                $alias = $explode[1];

                if ($joinTable == $tableName) {
                    return $alias;
                }
            }

            if ($join == $tableName) {
                return $tableName;
            }
        };

        return false;
    }
}
