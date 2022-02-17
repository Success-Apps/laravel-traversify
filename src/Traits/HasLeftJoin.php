<?php
namespace Traversify\Traits;

use Exception;
use Illuminate\Database\Eloquent\Builder;

trait HasLeftJoin
{
    /**
     * Perform the JOIN clause for eloquent joins.
     */
    public function performJoinForEloquent(Builder $query, $relation, $alias = null)
    {
        $joinType = 'leftJoin';

        if ($relation instanceof BelongsToMany) {

            return $this->performJoinForEloquentForBelongsToMany($query, $relation, $joinType, $alias);
        } elseif ($relation instanceof MorphOne || $relation instanceof MorphMany || $relation instanceof MorphOneOrMany || $relation instanceof MorphTo || $relation instanceof MorphPivot || $relation instanceof MorphToMany) {

            return $this->performJoinForEloquentForMorph($query, $relation, $joinType, $alias);
        } elseif ($relation instanceof HasMany || $relation instanceof HasOne || $relation instanceof HasOneOrMany) {

            return $this->performJoinForEloquentForHasMany($query, $relation, $joinType, $alias);
        } elseif ($relation instanceof HasManyThrough || $relation instanceof HasOneThrough) {

            return $this->performJoinForEloquentForHasManyThrough($query, $relation, $joinType, $alias);
        } elseif ($relation instanceof BelongsTo) {

            return $this->performJoinForEloquentForBelongsTo($query, $relation, $joinType, $alias);
        };
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

        return $query->{$joinType}($relationTable, function ($join) use ($relation, $tableOrAlias) {

            $join->on(

                "{$tableOrAlias}.{$relation->getOwnerKeyName()}",
                '=',
                $relation->getQualifiedForeignKeyName()
            );

            $ignoredKeys = [$relation->getQualifiedOwnerKeyName(), $relation->getQualifiedForeignKeyName()];
            $this->applyExtraConditions($relation, $join, $ignoredKeys, $tableOrAlias);

            if ($relation->usesSoftDeletes($relation->getModel())) {

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

            if ($relation->usesSoftDeletes($relation->getRelated())) {

                $join->whereNull("{$pivotTable}.{$relation->getRelated()->getDeletedAtColumn()}");
            }
        });

        return $query->{$joinType}($relationTable, function ($join) use ($relation, $relationTable, $tableOrAlias) {

            $join->on(

                "{$tableOrAlias}.{$relation->getModel()->getKeyName()}",
                '=',
                $relation->getQualifiedRelatedPivotKeyName()
            );

//            $ignoredKeys = ["{$relationTable}.{$relation->getModel()->getKeyName()}", $relation->getQualifiedRelatedPivotKeyName()];
//            $this->applyExtraConditions($relation, $join, $ignoredKeys, $tableOrAlias);
//
//            if ($relation->usesSoftDeletes($relation->getModel())) {
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

        return $query->{$joinType}($relationTable, function ($join) use ($relation, $tableOrAlias, $parentTable) {

            $join->on(

                "{$tableOrAlias}.{$relation->getForeignKeyName()}",
                '=',
                "{$parentTable}.{$relation->getLocalKeyName()}"
            );

            $ignoredKeys = [$relation->getQualifiedForeignKeyName(), "{$parentTable}.{$relation->getLocalKeyName()}"];
            $this->applyExtraConditions($relation, $join, $ignoredKeys, $tableOrAlias);

            if ($relation->usesSoftDeletes($relation->getModel())) {

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

        return $query->{$joinType}($relationTable, function ($join) use ($relation, $relationTable, $parentTable, $tableOrAlias) {

            $join->on(

                "{$parentTable}.{$relation->getLocalKeyName()}",
                '=',
                "{$tableOrAlias}.{$relation->getForeignKeyName()}"
            );

            $ignoredKeys = ["{$parentTable}.{$relation->getLocalKeyName()}", $relation->getQualifiedForeignKeyName()];
            $this->applyExtraConditions($relation, $join, $ignoredKeys, $tableOrAlias);

            if ($relation->usesSoftDeletes($relation->getRelated())) {

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

            if ($relation->usesSoftDeletes($relation->getParent())) {

                $join->whereNull("{$throughTable}.{$relation->getParent()->getDeletedAtColumn()}");
            }
        });

        return $query->{$joinType}($tableOrAlias, function ($join) use ($relation, $throughTable, $farTable, $tableOrAlias) {

            $join->on(

                "{$tableOrAlias}.{$relation->getForeignKeyName()}",
                '=',
                "{$throughTable}.{$relation->getSecondLocalKeyName()}"
            );

//            $ignoredKeys = ["{$throughTable}.{$relation->getForeignKeyName()}", $relation->getQualifiedLocalKeyName(), "{$farTable}.{$relation->getForeignKeyName()}", "{$throughTable}.{$relation->getSecondLocalKeyName()}"];
//            $this->applyExtraConditions($relation, $join, $ignoredKeys, $tableOrAlias);
//
//            if ($relation->usesSoftDeletes($relation->getModel())) {
//
//                $join->whereNull("{$tableOrAlias}.{$relation->getModel()->getDeletedAtColumn()}");
//            }
        });

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
     * @return bool
     */
    private function relationshipIsAlreadyJoined(Builder $query, string $tableName)
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
                $join = $explode[0];
                $alias = $explode[1];

                if ($join == $tableName) {
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
