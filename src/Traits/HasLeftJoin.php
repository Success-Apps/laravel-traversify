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

        // Cache in Memory the join methods
        $this->cachedJoins[] = [
            'rel' => ['BelongsTo'],
            'tables' => [$parentTable, $relationTable],
            'columns' => [
                'qualified' => [
                    ["{$relation->getModel()->getTable()}.{$relation->getOwnerKeyName()}", $relation->getForeignKeyName()],
                ],
                'non' => [
                    [$relation->getOwnerKeyName(), $relation->getQualifiedForeignKeyName()],
                ],
            ],
            'alias' => $alias,
        ];

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

        // Cache in Memory the join methods
        $this->cachedJoins[] = [
            'rel' => ['BelongsToMany'],
            'tables' => [$parentTable, $relation->getModel()->getTable()],
            'columns' => [
                'qualified' => [
                    [$relation->getQualifiedForeignPivotKeyName(), $relation->getQualifiedParentKeyName()],
                    ["{$relation->getModel()->getTable()}.{$relation->getModel()->getKeyName()}", $relation->getQualifiedRelatedPivotKeyName()],
                ],
                'non' => [
                    [$relation->getForeignPivotKeyName(), $relation->getParentKeyName()],
                    [$relation->getForeignPivotKeyName(), $relation->getParentKeyName()],
                ],
            ],
            'alias' => $alias,
        ];

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

        $this->cachedJoins[] = [
            'rel' => ['MorphOne'],
            'tables' => [$parentTable, $relationTable],
            'columns' => [
                'qualified' => [
                    ["{$relation->getModel()->getTable()}.{$relation->getForeignKeyName()}", "{$parentTable}.{$relation->getLocalKeyName()}"],
                ],
                'non' => [
                    [$relation->getForeignKeyName(), $relation->getLocalKeyName()],
                ],
            ],
            'alias' => $alias,
        ];

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

        $this->cachedJoins[] = [
            'rel' => ['HasMany'],
            'tables' => [$parentTable, $relationTable],
            'columns' => [
                'qualified' => [
                    ["{$parentTable}.{$relation->getLocalKeyName()}", "{$relation->getRelated()->getTable()}.{$relation->getForeignKeyName()}"],
                ],
                'non' => [
                    [$relation->getLocalKeyName(), $relation->getForeignKeyName()],
                ],
            ],
            'alias' => $alias,
        ];

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

        $this->cachedJoins[] = [
            'rel' => ['HasManyThrough'],
            'tables' => [$throughTable, $farTable],
            'columns' => [
                'qualified' => [
                    ["{$throughTable}.{$relation->getFirstKeyName()}", $relation->getQualifiedLocalKeyName()],
                    ["{$relation->getRelated()->getTable()}.{$relation->getForeignKeyName()}", "{$throughTable}.{$relation->getSecondLocalKeyName()}"],
                ],
                'non' => [
                    [$relation->getFirstKeyName(), $relation->getLocalKeyName()],
                    [$relation->getForeignKeyName(), $relation->getSecondLocalKeyName()],
                ],
            ],
            'alias' => $alias,
        ];

        return $query;
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
                    'tables' => [$parentTable, $relationTable],
                    'columns' => [
                        'qualified' => [
                            ["{$relationTable}.{$relation->getOwnerKeyName()}", $relation->getQualifiedForeignKeyName()],
                        ],
                        'non' => [
                            [$relation->getOwnerKeyName(), $relation->getQualifiedForeignKeyName()],
                        ],
                    ],
                ];
                break;

            case ($relation instanceof BelongsToMany):
                $parentTable = $relation->getParent()->getTable();
                $relationTable = $relation->getModel()->getTable();

                $relationJoin = [
                    'rel' => ['BelongsToMany'],
                    'tables' => [$parentTable, $relationTable],
                    'columns' => [
                        'qualified' => [
                            ["{$relationTable}.{$relation->getForeignKeyName()}", "{$parentTable}.{$relation->getLocalKeyName()}"],
                        ],
                        'non' => [
                            [$relation->getForeignKeyName(), $relation->getLocalKeyName()],
                        ],
                    ],
                ];
                break;

            case ($relation instanceof MorphOne || $relation instanceof MorphMany || $relation instanceof MorphOneOrMany || $relation instanceof MorphTo || $relation instanceof MorphPivot || $relation instanceof MorphToMany):
                $parentTable = $relation->getParent()->getTable();
                $relationTable = $relation->getModel()->getTable();

                $relationJoin = [
                    'rel' => ['MorphOne'],
                    'tables' => [$parentTable, $relationTable],
                    'columns' => [
                        'qualified' => [
                            ["{$relationTable}.{$relation->getForeignKeyName()}", "{$parentTable}.{$relation->getLocalKeyName()}"],
                        ],
                        'non' => [
                            [$relation->getForeignKeyName(), $relation->getLocalKeyName()],
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
                            ["{$parentTable}.{$relation->getLocalKeyName()}", "{$relationTable}.{$relation->getForeignKeyName()}"],
                        ],
                        'non' => [
                            [$relation->getLocalKeyName(), $relation->getForeignKeyName()],
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
                            ["{$parentTable}.{$relation->getFirstKeyName()}", $relation->getQualifiedLocalKeyName()],
                            ["{$relationTable}.{$relation->getForeignKeyName()}", "{$parentTable}.{$relation->getSecondLocalKeyName()}"],
                        ],
                        'non' => [
                            [$relation->getFirstKeyName(), $relation->getLocalKeyName()],
                            [$relation->getForeignKeyName(), $relation->getSecondLocalKeyName()],
                        ],
                    ],
                ];
                break;

            default:
                Log::info("No Relationship Class found => ".$relation::class);
                throw new Exception("No Relationship Class found => ".$relation::class);
        }

        if ($relationJoin) {
            return $this->checkForJoinWithColumnsInExisitingQuery($query, $relationJoin);
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
        $tablesJoined = $this->tableExistsInJoins($relationJoin);
        $withSameColumns = false;

        if ($tablesJoined['tables_joined']) {
            foreach ($this->cachedJoins as $join) {

                if ($this->joinsAreEqual($relationJoin, $join)) {
                    $withSameColumns = true;
                    break;
                }
            }
        }

        return [
            'table_exists' => $tablesJoined['table_exists'],
            'tables_joined' => $tablesJoined['tables_joined'],
            'with_columns' => $withSameColumns,
        ];
   }

    /**
     * Check if tables exist in join
     *
     * @param array $relationJoin
     * @param array $joins
     * @return bool
     */
    private function tableExistsInJoins(array $relationJoin): array
    {
        // Extract the two tables from $relationJoin
        $table1 = $relationJoin['tables'][0];
        $table2 = $relationJoin['tables'][1];

        $result = [
            'table_exists' => false,
            'tables_joined' => false,
        ];

        // Loop through each join definition in $joins
        if ($this->cachedJoins) {
            foreach ($this->cachedJoins as $join) {
                // Check if both tables are present in the current join's 'tables'
                if (in_array($table2, $join['tables'])) {
                    $result['table_exists'] = true; // Tables found in current join, exit loop
                }
                if (in_array($table1, $join['tables']) && in_array($table2, $join['tables'])) {
                    $result['tables_joined'] = true; // Tables found in current join, exit loop
                }
            }
        }

        // If loop completes without finding tables, return false
        return $result;
    }

    /**
     * Joins Are Equal
     *
     * @param array $join1
     * @param array $join2
     * @return bool
     */
    private function joinsAreEqual(array $join1, array $join2): bool
    {
        $equalCols = false;

        foreach ($join1['columns']['non'] as $cols1) {
            foreach ($join2['columns']['non'] as $cols2) {
                if (in_array($cols1[0], $cols2) && in_array($cols1[1], $cols2)) {
                    $equalCols = true;
                    break;
                }
            }

            if ($equalCols) {
                break;
            };
        }

        return $equalCols;
    }

    /**
     * Check if this table been already joined already
     *
     * @param Builder $query
     * @param string $tableName
     * @return bool
     */
    private function getTableOrAliasForModel(Builder $query, string $tableName): string
    {
        $tableJoins = collect($query->getQuery()->joins)->pluck('table');

        $alias = null;

        foreach($tableJoins as $tableJoin) {
            if (is_string($tableJoin)) {
                $tableJoin = strtolower($tableJoin);

                if (str_contains($tableJoin, ' as ')) {

                    $explode = explode(' as ', $tableJoin);
                    $tableJoin = $explode[0];
                    $alias = $explode[1];

                    if ($tableJoin == $tableName) {
                        return $alias;
                    }
                }

                if ($tableJoin == $tableName) {
                    return $tableName;
                }
            } else if ($tableJoin instanceof Expression) {
                $expression = $tableJoin->getValue($this->getGrammar());

                if (str_contains($expression, "as `$alias`")) {
                    return $alias;
                }

                if (str_contains($expression, "from `$tableName`")) {
                    return $tableName;
                }
            }
        };

        return $alias;
    }

    /**
     * Extra conditions on Relationships
     *
     * @return void
     */
    public function applyExtraConditions($relation, $join, $ignoredKeys, $alias = null): void
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

    /**
     * Apply Null Condition
     *
     * @param $join
     * @param $condition
     * @param $alias
     * @return void
     */
    public function applyNullCondition($join, $condition, $alias = null): void
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

    /**
     * Apply Not Null Condition
     *
     * @param $join
     * @param $condition
     * @param $alias
     * @return void
     */
    public function applyNotNullCondition($join, $condition, $alias = null): void
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

    /**
     * Apply Nested Condition
     *
     * @param $join
     * @param $condition
     * @param $ignoredKeys
     * @param $alias
     * @return void
     */
    public function applyNestedCondition($join, $condition, $ignoredKeys, $alias = null): void
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
     *
     * @param $model
     * @return bool
     */
    public function usesSoftDeletes($model): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model));
    }
}
