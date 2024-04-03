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
use Illuminate\Database\Query\JoinClause;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

trait HasLeftJoin
{
    protected $cachedJoins = [];

    /**
     * Perform the JOIN clause for eloquent joins.
     */
    public function performJoinForEloquent(Builder $query, $relation, $alias = null): Builder
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

        return $query;
    }

    /**
     * Perform the JOIN clause for the BelongsTo (or similar) relationships.
     */
    protected function performJoinForEloquentForBelongsTo(Builder $query, $relation, $joinType, $alias): Builder
    {
        $parentTable = $relation->getParent()->getTable();
        $relationTable = $relation->getModel()->getTable();
        $tableOrAlias = $relationTable;
        $wheres = $query->getQuery()->wheres;

        if ($alias) {
            $tableOrAlias = $alias;
            $relationTable = $relationTable . ' AS ' . $alias;
        }

        $query->{$joinType}($relationTable, function ($join) use ($relation, $tableOrAlias, $wheres) {

            $join->on(
                "{$tableOrAlias}.{$relation->getOwnerKeyName()}",
                '=',
                $relation->getQualifiedForeignKeyName()
            );

            // CHECK HERE AND NOT ADD IF ALREADY EXISTS, FULLY QUALIFIED
            $ignoredKeys = [$relation->getQualifiedOwnerKeyName(), $relation->getQualifiedForeignKeyName()];
            $this->applyExtraConditions($wheres, $relation, $join, $ignoredKeys, $tableOrAlias);

            if ($this->usesSoftDeletes($wheres, $relation->getModel(), $tableOrAlias)) {
                $join->whereNull("{$tableOrAlias}.{$relation->getModel()->getDeletedAtColumn()}");
            }
        });

        return $query;
    }

    /**
     * Perform the JOIN clause for the BelongsToMany (or similar) relationships.
     */
    protected function performJoinForEloquentForBelongsToMany(Builder $query, $relation, $joinType, $alias): Builder
    {
        $pivotTable = $relation->getRelated()->getTable();
        $relationTable = $relation->getModel()->getTable();
        $tableOrAlias = $relationTable;
        $wheres = $query->getQuery()->wheres;

        if ($alias) {
            $tableOrAlias = $alias;
            $relationTable = $relationTable . ' AS ' . $alias;
        }

        $query->{$joinType}($pivotTable, function ($join) use ($relation, $pivotTable, $wheres) {

            $join->on(
                $relation->getQualifiedForeignPivotKeyName(),
                '=',
                $relation->getQualifiedParentKeyName()
            );

            $ignoredKeys = [$relation->getQualifiedForeignPivotKeyName(), $relation->getQualifiedParentKeyName()];
            $this->applyExtraConditions($wheres, $relation, $join, $ignoredKeys);

            if ($this->usesSoftDeletes($wheres, $relation->getRelated(), $tableOrAlias)) {
                $join->whereNull("{$pivotTable}.{$relation->getRelated()->getDeletedAtColumn()}");
            }
        });

        $query->{$joinType}($relationTable, function ($join) use ($relation, $relationTable, $tableOrAlias, $wheres) {

            $join->on(
                "{$tableOrAlias}.{$relation->getModel()->getKeyName()}",
                '=',
                $relation->getQualifiedRelatedPivotKeyName()
            );

            // CHECK HERE AND NOT ADD IF ALREADY EXISTS, FULLY QUALIFIED
            $ignoredKeys = ["{$relationTable}.{$relation->getModel()->getKeyName()}", $relation->getQualifiedRelatedPivotKeyName()];
            $this->applyExtraConditions($wheres, $relation, $join, $ignoredKeys, $tableOrAlias);

            // CHECK HERE AND NOT ADD IF ALREADY EXISTS, FULLY QUALIFIED
            if ($this->usesSoftDeletes($wheres, $relation->getModel(), $tableOrAlias)) {
                $join->whereNull("{$tableOrAlias}.{$relation->getModel()->getDeletedAtColumn()}");
            }
        });

        return $query;
    }

    /**
     * Perform the JOIN clause for the Morph (or similar) relationships.
     */
    protected function performJoinForEloquentForMorph(Builder $query, $relation, $joinType, $alias): Builder
    {
        $parentTable = $relation->getParent()->getTable();
        $relationTable = $relation->getModel()->getTable();
        $tableOrAlias = $relationTable;
        $wheres = $query->getQuery()->wheres;

        if ($alias) {
            $tableOrAlias = $alias;
            $relationTable = $relationTable . ' AS ' . $alias;
        }

        $query->{$joinType}($relationTable, function ($join) use ($relation, $tableOrAlias, $parentTable, $wheres) {

            $join->on(
                "{$tableOrAlias}.{$relation->getForeignKeyName()}",
                '=',
                "{$parentTable}.{$relation->getLocalKeyName()}"
            );

            // CHECK HERE AND NOT ADD IF ALREADY EXISTS, FULLY QUALIFIED
            $ignoredKeys = [$relation->getQualifiedForeignKeyName(), "{$parentTable}.{$relation->getLocalKeyName()}"];
            $this->applyExtraConditions($wheres, $relation, $join, $ignoredKeys, $tableOrAlias);

            if ($this->usesSoftDeletes($wheres, $relation->getModel(), $tableOrAlias)) {
                $join->whereNull("{$tableOrAlias}.{$relation->getModel()->getDeletedAtColumn()}");
            }
        });

        return $query;
    }

    /**
     * Perform the JOIN clause for the HasMany (or similar) relationships.
     */
    protected function performJoinForEloquentForHasMany(Builder $query, $relation, $joinType, $alias): Builder
    {
        $parentTable = $relation->getParent()->getTable();
        $relationTable = $relation->getRelated()->getTable();
        $tableOrAlias = $relationTable;
        $wheres = $query->getQuery()->wheres;

        if ($alias) {
            $tableOrAlias = $alias;
            $relationTable = $relationTable . ' AS ' . $alias;
        }

        $query->{$joinType}($relationTable, function ($join) use ($relation, $relationTable, $parentTable, $tableOrAlias, $wheres) {

            $join->on(
                "{$parentTable}.{$relation->getLocalKeyName()}",
                '=',
                "{$tableOrAlias}.{$relation->getForeignKeyName()}"
            );

            // CHECK HERE AND NOT ADD IF ALREADY EXISTS, FULLY QUALIFIED
            $ignoredKeys = ["{$parentTable}.{$relation->getLocalKeyName()}", $relation->getQualifiedForeignKeyName()];
            $this->applyExtraConditions($wheres, $relation, $join, $ignoredKeys, $tableOrAlias);

            if ($this->usesSoftDeletes($wheres, $relation->getRelated(), $tableOrAlias)) {
                $join->whereNull("{$relationTable}.{$relation->getRelated()->getDeletedAtColumn()}");
            }
        });

        return $query;
    }

    /**
     * Perform the JOIN clause for the HasManyThrough relationships.
     */
    protected function performJoinForEloquentForHasManyThrough(Builder $query, $relation, $joinType, $alias): Builder
    {
        $throughTable = $relation->getParent()->getTable();
        $farTable = $relation->getRelated()->getTable();
        $tableOrAlias = $farTable;
        $wheres = $query->getQuery()->wheres;

        if ($alias) {
            $tableOrAlias = $alias;
            $relationTable = $farTable . ' AS ' . $alias;
        }

        $query->{$joinType}($throughTable, function ($join) use ($relation, $throughTable, $farTable, $tableOrAlias, $wheres) {

            $join->on(
                "{$throughTable}.{$relation->getFirstKeyName()}",
                '=',
                $relation->getQualifiedLocalKeyName()
            );

            // CHECK HERE AND NOT ADD IF ALREADY EXISTS, FULLY QUALIFIED
            $ignoredKeys = ["{$throughTable}.{$relation->getFirstKeyName()}", $relation->getQualifiedLocalKeyName(), "{$farTable}.{$relation->getForeignKeyName()}", "{$throughTable}.{$relation->getSecondLocalKeyName()}"];
            $this->applyExtraConditions($wheres, $relation, $join, $ignoredKeys);

            if ($this->usesSoftDeletes($wheres, $relation->getParent(), $tableOrAlias)) {
                $join->whereNull("{$throughTable}.{$relation->getParent()->getDeletedAtColumn()}");
            }
        });

        $query->{$joinType}($tableOrAlias, function ($join) use ($relation, $throughTable, $farTable, $tableOrAlias, $wheres) {

            $join->on(
                "{$tableOrAlias}.{$relation->getForeignKeyName()}",
                '=',
                "{$throughTable}.{$relation->getSecondLocalKeyName()}"
            );

            if ($this->usesSoftDeletes($wheres, $relation->getModel(), $tableOrAlias)) {
                $join->whereNull("{$tableOrAlias}.{$relation->getModel()->getDeletedAtColumn()}");
            }
        });

        return $query;
    }

    /**
     * Perform Join Logic
     *
     * @param Builder $query
     * @param $currentModel
     * @param array $relationsSplit
     * @param string $motherOfAllModelsTable
     * @param $motherOfAllModels
     * @return array
     * @throws Exception
     */
    public function performJoinLogic(Builder $query, $currentModel, array $relationsSplit, string $motherOfAllModelsTable, $motherOfAllModels): array
    {
        $lastModel = null;
        $lastRelationTable = null;

        if (count($relationsSplit) === 1 && strpos($relationsSplit[0], '_columns_only')) {
            $lastRelationTable = $motherOfAllModelsTable;
            $lastModel = $motherOfAllModels;
        } else {

            foreach ($relationsSplit as $index => $relationName) {

                $alias = null;
                $relation = $currentModel->{$relationName}();
                $currentModel = $relation->getRelated();
                $tableName = $currentModel->getTable();

                $relationshipJoined = $this->relationshipIsAlreadyJoined($query, $tableName, $relation);

                if ($relationshipJoined['tables_joined'] && !$relationshipJoined['with_columns']) {

                    $alias = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz"), 0, 3) . time();
                    $this->performJoinForEloquent($query, $relation, $alias);

                } else if (!$relationshipJoined['tables_joined']) {

                    $alias = null;
                    if ($this->getTable() === $tableName) {
                        $alias = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz"), 0, 3) . time();
                    }
                    $this->performJoinForEloquent($query, $relation, $alias);

                }

                if (array_key_last($relationsSplit) === $index) {
                    $lastRelationTable = $alias ?? $tableName;
                    $lastModel = $currentModel;
                }

            }
        }

        return [
            'last_model' => $lastModel,
            'last_relation_table' => $lastRelationTable,
        ];
    }

    /**
     * Check if query has Join
     *
     * @param Builder $query
     * @param string $tableName
     * @return bool
     */
    private function relationshipIsAlreadyJoined(Builder $query, string $tableName, $relation): array
    {
        $relationJoin = null;

        switch (true) {
            case ($relation instanceof BelongsTo):
                $parentTable = $relation->getParent()->getTable();
                $relationTable = $relation->getModel()->getTable();

                $relationJoin = [
                    'rel' => ['BelongsTo'],
                    'type' => ['Column'],
                    'tables' => [$parentTable, $relationTable],
                    'columns' => [
                        'qualified' => [
                            ['type' => 'Column', 'first' => "{$relationTable}.{$relation->getOwnerKeyName()}", 'second' => $relation->getQualifiedForeignKeyName()],
                        ],
                        'non' => [
                            ['type' => 'Column', 'first' => $relation->getOwnerKeyName(), 'second' => $relation->getForeignKeyName()],
                        ],
                    ],
                ];
                break;

            case ($relation instanceof BelongsToMany):
                $parentTable = $relation->getParent()->getTable();
                $relationTable = $relation->getModel()->getTable();

                $relationJoin = [
                    'rel' => ['BelongsToMany'],
                    'type' => ['Column'],
                    'tables' => [$parentTable, $relationTable],
                    'columns' => [
                        'qualified' => [
                            ['type' => 'Column', 'first' => $relation->getQualifiedForeignPivotKeyName(), 'second' => $relation->getQualifiedParentKeyName()],
                            ['type' => 'Column', 'first' => "{$relationTable}.{$relation->getModel()->getKeyName()}", 'second' => $relation->getQualifiedRelatedPivotKeyName()]
                        ],
                        'non' => [
                            ['type' => 'Column', 'first' => $relation->getForeignPivotKeyName(), 'second' => $relation->getParentKeyName()],
                            ['type' => 'Column', 'first' => $relation->getModel()->getKeyName(), 'second' => $relation->getRelatedPivotKeyName()]
                        ],
                    ],
                ];
                break;

            case ($relation instanceof MorphOne || $relation instanceof MorphMany || $relation instanceof MorphOneOrMany || $relation instanceof MorphTo || $relation instanceof MorphPivot || $relation instanceof MorphToMany):
                $parentTable = $relation->getParent()->getTable();
                $relationTable = $relation->getModel()->getTable();

                $relationJoin = [
                    'rel' => ['Morph'],
                    'type' => ['Column'],
                    'tables' => [$parentTable, $relationTable],
                    'columns' => [
                        'qualified' => [
                            ['type' => 'Column', 'first' => "{$relationTable}.{$relation->getForeignKeyName()}", 'second' => "{$parentTable}.{$relation->getLocalKeyName()}"],
                            ['type' => 'Basic', 'column' => $relation->getQualifiedMorphType(), 'value' => $relation->getMorphClass()],
                        ],
                        'non' => [
                            ['type' => 'Column', 'first' => $relation->getForeignKeyName(), 'second' => $relation->getLocalKeyName()],
                            ['type' => 'Basic', 'first' => $relation->getMorphType(), 'second' => $relation->getMorphClass()],
                        ],
                    ],
                ];
                break;

            case ($relation instanceof HasMany || $relation instanceof HasOne || $relation instanceof HasOneOrMany):
                $parentTable = $relation->getParent()->getTable();
                $relationTable = $relation->getModel()->getTable();

                $relationJoin = [
                    'rel' => ['HasMany'],
                    'tables' => [$parentTable, $relationTable],
                    'columns' => [
                        'qualified' => [
                            ['type' => 'Column', 'first' => "{$parentTable}.{$relation->getLocalKeyName()}", 'second' => "{$relationTable}.{$relation->getForeignKeyName()}"],
                        ],
                        'non' => [
                            ['type' => 'Column', 'first' => $relation->getLocalKeyName(), 'second' => $relation->getForeignKeyName()],
                        ],
                    ],
                ];
                break;

            case ($relation instanceof HasManyThrough || $relation instanceof HasOneThrough):
                $parentTable = $relation->getParent()->getTable();
                $relationTable = $relation->getRelated()->getTable();

                $relationJoin = [
                    'rel' => ['HasManyThrough'],
                    'tables' => [$parentTable, $relationTable],
                    'columns' => [
                        'qualified' => [
                            ['type' => 'Column', 'first' => "{$parentTable}.{$relation->getFirstKeyName()}", 'second' => $relation->getQualifiedLocalKeyName()],
                            ['type' => 'Column', 'first' => "{$relationTable}.{$relation->getForeignKeyName()}", 'second' => "{$parentTable}.{$relation->getSecondLocalKeyName()}"],
                        ],
                        'non' => [
                            ['type' => 'Column', 'first' => $relation->getFirstKeyName(), 'second' => $relation->getLocalKeyName()],
                            ['type' => 'Column', 'first' => $relation->getForeignKeyName(), 'second' => $relation->getSecondLocalKeyName()],
                        ],
                    ],
                ];
                break;

            default:
                Log::info("No Relationship Class found => ".$relation::class);
                throw new Exception("No Relationship Class found => ".$relation::class);
        }

        if ($relationJoin) {
            $result = $this->checkForJoinWithColumnsInExisitingQuery($query, $relationJoin);
            return $result;
        }

        return [
            'tables_joined' => false,
            'with_columns' => false,
        ];
    }

    /**
     * Check if columns / values exist in query joins
     *
     * @param Builder $query
     * @param array $relationJoin
     * @return array
     */
    public function checkForJoinWithColumnsInExisitingQuery(Builder $query, array $relationJoin): array
    {
        $tablesJoined = false;
        $withColumns = false;

        $joins = $query->getQuery()->joins;

        if ($joins) {

            foreach ($joins as $join) {

                $baseTable = $this->getTableOrAliasForModel($query, $join->table);
                $alias = null;

                if ((str_contains($baseTable, $relationJoin['tables'][1]) && str_contains(strtolower($baseTable), ' as ')) ||
                    ($baseTable === $relationJoin['tables'][1])) {

                    $tablesJoined = true;

                    if (str_contains(strtolower($baseTable), ' as ')) {
                        $explode = explode(' as ', $baseTable);
                        $baseTable = $explode[0];
                        $alias = $explode[1];
                    }

                    $withColumns = $this->joinsAreEqual($join, $relationJoin, $baseTable, $alias);

                    if ($tablesJoined && $withColumns) {
                        break;
                    }
                }

            }

        }

        return [
            'tables_joined' => $tablesJoined,
            'with_columns' => $withColumns,
        ];
   }

    /**
     * Check if query has Join
     *
     * @param Builder $query
     * @param string $tableName
     * @return false|string
     */
    private function getTableOrAliasForModel(Builder $query, string|Expression $tableName)
    {
        if (is_string($tableName)) {
            $tableName = strtolower($tableName);

            if (str_contains($tableName, ' as ')) {
                $explode = explode(' as ', $tableName);
                $alias = $explode[1];
                return $alias;
            }

            if ($tableName == $tableName) {
                return $tableName;
            }

        } else if ($tableName instanceof Expression) {

            $expression = $tableName->getValue($this->getGrammar());

            if (str_contains($expression, ' as ')) {
                $explode = explode(' as ', $expression);
                $alias = $explode[1];
                return $alias;
            }

            if (str_contains($expression, "from `$tableName`")) {
                return $tableName;
            }
        }

        return false;
    }

    /**
     * Joins are equal
     *
     * @param JoinClause $join
     * @param array $tableToJoin
     * @param string $baseTable
     * @param string $alias
     * @return bool
     */
    private function joinsAreEqual(JoinClause $join, array $relationJoin, string $baseTable, string $alias = null): bool
    {
        switch ($relationJoin['rel']) {
            case 'Morph':
            case 'HasManyThrough':
                $expectedMatches = 2;
                break;

            case 'BelongsTo':
            case 'BelongsToMany':
            case 'HasMany':
            default:
                $expectedMatches = 1;
                break;
        }

        $filteredJoins = Arr::where($join->wheres, function ($item) {
            return in_array($item['type'], ['Column', 'Basic']);
        });

        $matchesFound = 0;

        foreach ($filteredJoins as $whereExpression) {

            $filteredTablesToJoin = Arr::where($relationJoin['columns']['qualified'], function ($item) use ($whereExpression) {
                return $item['type'] === $whereExpression['type'];
            });

            foreach ($filteredTablesToJoin as $tableToJoin) {

                if ($whereExpression['type'] === 'Column') {
                    $first = $whereExpression['first'];
                    $second = $whereExpression['second'];

                    if ($alias) {
                        $first = Str::swap([
                            $alias => $baseTable,
                        ], $first);
                    }

                    if ($tableToJoin['first'] === $first && $tableToJoin['second'] === $second) {
                        $matchesFound++;
                    }
                }

                if ($whereExpression['type'] === 'Basic') {

                    $column = $whereExpression['column'];
                    $value = $whereExpression['value'];

                    if ($alias) {
                        $column = Str::swap([
                            $alias => $baseTable,
                        ], $column);
                    }

                    if ($tableToJoin['column'] === $column && $whereExpression['operator'] === '=' && $tableToJoin['value'] === $value) {
                        $matchesFound++;
                    }

                }

            }

        }

        return $expectedMatches === $matchesFound;
    }

    /**
     * Extra conditions on Relationships
     *
     * @return void
     */
    public function applyExtraConditions(array $wheres, $relation, $join, $ignoredKeys, $alias = null): void
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
                $this->$method($join, $condition, $ignoredKeys, $wheres, $alias);
            } else {
                $method = "apply{$condition['type']}Condition";
                $this->$method($join, $condition, $wheres, $alias);
            }
        }
    }

    /**
     * Apply relationship conditions
     *
     * @param $join
     * @param $condition
     */
    public function applyBasicCondition($join, $condition, $wheres, $alias = null)
    {
        $column = $condition['column'];

        if ($alias) {
            $parts = explode('.', $condition['column']);

            if (count($parts) === 2) {
                $column = "{$alias}.{$parts[1]}";
            } else {
                $column = "{$alias}.{$parts[0]}";
            }
        }

        $filteredWheres = Arr::where($wheres, function ($item) {
            return $item['type'] === 'Basic' && $item['column'] === $column && $item['operator'] === $condition['operator'] && $item['values'] === $condition['value'];
        });

        if (!count($filteredWheres)) {
            $join->where($column, $condition['operator'], $condition['value']);
        }
    }

    /**
     * Apply Null Condition
     *
     * @param $join
     * @param $condition
     * @param $alias
     * @return void
     */
    public function applyNullCondition($join, $condition, $wheres, $alias = null): void
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

        $filteredWheres = Arr::where($wheres, function ($item) use ($column) {
            return $item['type'] === 'Null' && $item['column'] === $column;
        });

        if (!count($filteredWheres)) {
            $join->whereNull($column);
        }
    }

    /**
     * Apply Not Null Condition
     *
     * @param $join
     * @param $condition
     * @param $alias
     * @return void
     */
    public function applyNotNullCondition($join, $condition, $wheres, $alias = null): void
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

        $filteredWheres = Arr::where($wheres, function ($item) use ($column) {
            return $item['type'] === 'NotNull' && $item['column'] === $column;
        });

        if (!count($filteredWheres)) {
            $join->whereNotNull($column);
        }
    }

    /**
     * Apply Nested Condition
     *
     * @param $join
     * @param $condition
     * @param $ignoredKeys
     * @param $alias
     * @return void
     */
    public function applyNestedCondition($join, $condition, $ignoredKeys, $wheres, $alias = null): void
    {
        foreach ($condition['query']->wheres as $condition) {

            if (! in_array($condition['type'], ['Basic', 'Null', 'NotNull', 'Nested'])) {
                continue;
            }

            if (in_array($condition['type'], ['Null', 'NotNull']) && in_array($condition['column'], $ignoredKeys)) {
                continue;
            }

            $method = "apply{$condition['type']}Condition";
            $this->$method($join, $condition, $wheres, $alias);
        }
    }

    /**
     * Checks if the relationship model uses soft deletes.
     *
     * @param $model
     * @return bool
     */
    public function usesSoftDeletes(array $wheres, $model, string $tableOrAlias): bool
    {
        if (!in_array(SoftDeletes::class, class_uses_recursive($model))) {
            return false;
        }

        $column = "{$tableOrAlias}.{$model->getDeletedAtColumn()}";
        $filteredWheres = Arr::where($wheres, function ($item) use ($column){
            return $item['type'] === 'Null' && $item['column'] === $column;
        });

        if (!count($filteredWheres)) {
            return true;
        }

        return false;
    }
}
