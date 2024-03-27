<?php
namespace Traversify\Traits;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;

trait HasSearch
{
    use HasLeftJoin;

    protected $like = 'LIKE';

    /**
     * Initialize search query
     *
     * @param Builder $query
     * @param string $keyword
     * @throws Exception
     */
    public function scopeSearch(Builder $query, string|array $keyword = ''): void
    {
        if (!$searches = $this->search) {
            Log::error('No column configured to be searched - ' . $this::class);
            return;
        }

        if (empty($keyword)) {
            return;
        }

        $key = $this->connection ?: config('database.default');
        if (config('database.connections.' . $key . '.driver') === 'pgsql') {
            $this->like = 'ILIKE';
        }

        $searchables = $this->buildModelFiltersArray();

        if (is_null($query->getQuery()->columns)) {
            $query->select(sprintf('%s.*', $query->getModel()->getTable()));
        }

        $columnList = [];

        $motherOfAllModels = (new self);
        $motherOfAllModelsTable = (new self)->getTable();
        $lastRelationTable = $motherOfAllModelsTable;
        $tableName = null;

        if (count($searchables)) {

            foreach($searchables as $relations => $columns) {

                $lastModel = $motherOfAllModels;
                $lastRelationTable = $motherOfAllModelsTable;
                $relationsSplit = explode('.', $relations);
                $currentModel = new self;

                foreach ($relationsSplit as $index => $relationName) {

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

                    if (array_key_last($relationsSplit) === $index) {
                        $lastRelationTable = $alias ?? $tableName;
                        $lastModel = $currentModel;
                    }

                }

                foreach ($columns as $searchColumn) {
                    $currentColumn = $this->prepSearchId($lastModel, $lastRelationTable, $searchColumn);
                    $columnList[] = $currentColumn;
                }
            }

        }

        $searchColumns = implode(', ', $columnList);

        if (is_array($keyword)) {
            foreach ($keyword as $key) {
                $query->whereRaw("CONCAT_WS(' ', {$searchColumns}) {$this->like} ?", "%{$key}%");
            }
        } else {
            $query->whereRaw("CONCAT_WS(' ', {$searchColumns}) {$this->like} ?", "%{$keyword}%");
        }
    }

    /**
     * Setup ID Search
     *
     * @param string $tableName
     * @param string $searchColumn
     * @return string
     */
    private function prepSearchId($model, string $tableName, string $searchColumn): string
    {
        $column = $tableName.'.'.$searchColumn;
        $id = $model->primaryKey ?? 'id';

        $withPrefix = config('traversify.search_with_prefix') ?? false;

        if ($searchColumn === $id && $withPrefix) {

            if (defined($model::class.'::ID_PREFIX')) {
                $prefix =  strtoupper($model::class::ID_PREFIX);
            } else {
                $prefix =  strtoupper($tableName[0]);
            }

            // Resolve to use the ID_COLUMN constant instead of the primary key for searching
            if (defined($model::class .'::ID_COLUMN')) {
                $searchColumn = $model::class::ID_COLUMN;
            }

            $column = "CONCAT('".$prefix."-'".', '.$tableName.'.'.$searchColumn.")";
        }

        return $column;
    }

    /**
     * Bbuild Model Filters Array
     *
     * @param $searches
     * @return array
     */
    private function buildModelFiltersArray(): array
    {
        $filterRelations = [];

        sort($this->search);

        foreach ($this->search as $item) {

            $relation = $this->getItemRelation($item);

            if (!in_array($relation, $filterRelations)) {
                $filterRelations[] =  $relation;
            }
        }

        $summaryArray = [];

        foreach ($filterRelations as $item) {

            if (!$item['relation']) {
                $parent = new self;
                $item['relation'] = $parent->getTable();
            }

            if (!isset($summaryArray[$item['relation']])) {
                $summaryArray[$item['relation']] = [$item['column']];
            } else {
                array_push($summaryArray[$item['relation']], $item['column']);
            }
        }

        foreach ($summaryArray as $key => $value) {
            $summaryArray[$key] = array_unique($value);
        }

        return $summaryArray;
    }

    /**
     * Get Item Relation
     *
     * @param string $item
     * @return array
     */
    private function getItemRelation(string $item): array
    {

        $itemParts = explode('.', $item);
        $searchColumn = array_pop($itemParts);

        return [
            'column' => $searchColumn,
            'relation' => implode('.', $itemParts)
        ];
    }
}
