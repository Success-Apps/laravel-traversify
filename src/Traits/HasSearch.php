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
    public function scopeSearch(Builder $query, string $keyword = '')
    {
        if (!$searches = $this->search) {
            throw new Exception('No column configured to be searched - ' . $this::class);
        }

        if (empty($keyword)) {
            return;
        }

        $key = $this->connection ?: config('database.default');

        if (config('database.connections.' . $key . '.driver') == 'pgsql') {
            $this->like = 'ILIKE';
        }

        $searchableList = $this->buildModelFiltersArray();

        if (is_null($query->getQuery()->columns)) {
            $query->select(sprintf('%s.*', $query->getModel()->getTable()));
        }

        $columnList = [];

        $motherOfAllModels = (new self);
        $motherOfAllModelsTable = (new self)->getTable();
        $lastRelationTable = $motherOfAllModelsTable;
        $tableName = null;

        foreach($searchableList as $relations => $columns) {

            $lastModel = $motherOfAllModels;
            $lastRelationTable = $motherOfAllModelsTable;

            $relationsSplit = explode('.', $relations);

            $currentModel = new self;

            foreach ($relationsSplit as $index => $relationName) {

                if ($relationName != $motherOfAllModelsTable) {

                    $relation = $currentModel->{$relationName}();
                    $currentModel = $relation->getRelated();
                    $tableName = $currentModel->getTable();

//                    $alias = null;
//
//                    if (!$this->relationshipIsAlreadyJoined($query, $tableName)) {
//
//                        if ($tableName == $motherOfAllModelsTable) {
//
//                            $alias = 'A'.time();
//                        }
//
//                        $this->performJoinForEloquent($query, $relation, $alias);
//                    } else {
//
//                        $tableName = $this->getTableOrAliasForModel($query, $tableName);
//                    }

                    $alias = 'A'.time();
                    $tableName = $this->getTableOrAliasForModel($query, $tableName);

                    if (array_key_last($relationsSplit) == $index) {
                        $lastRelationTable = $alias ?? $tableName;
                        $lastModel = $currentModel;
                    }
                }
            }

            foreach ($columns as $searchColumn) {

                $currentColumn = $this->prepSearchId($lastModel, $lastRelationTable, $searchColumn);

                array_push($columnList, $currentColumn);
            }
        }

        $searchColumns = implode(', ', $columnList);

        $query->whereRaw("CONCAT_WS(' ', {$searchColumns}) {$this->like} ?", "%{$keyword}%");
    }

    /**
     * Setup ID Search
     *
     * @param string $tableName
     * @param string $searchColumn
     * @return string
     */
    private function prepSearchId($model, string $tableName, string $searchColumn) {

        $column = $tableName.'.'.$searchColumn;

        if ($searchColumn == 'id') {

            if (defined($model::class.'::ID_PREFIX')) {
                $prefix =  strtoupper($model::class::ID_PREFIX);
            } else {
                $prefix =  strtoupper(substr($tableName, 0, 1));
            }

            $column = "CONCAT('".$prefix."-'".', '.$tableName.'.'.$searchColumn.")";
        }

        return $column;
    }

    /**
     * Sort Searches
     *
     * @param $searches
     * @return array
     */
    private function buildModelFiltersArray()
    {
        $filterRelations = [];

        sort($this->search);

        foreach ($this->search as $item) {

            $relation = $this->getItemRelation($item);

            if (!in_array($relation, $filterRelations)) {
                $filterRelations[] = $relation;
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

                $summaryArray[$item['relation']] = $item['column'];
            }
        }

        return $summaryArray;
    }

    /**
     * Sort Searches
     *
     * @param string $item
     * @return array
     */
    private function getItemRelation(string $item)
    {

        $itemParts = explode('.', $item);
        $searchColumn = array_pop($itemParts);

        return [
            'column' => $searchColumn,
            'relation' => implode('.', $itemParts)
        ];
    }
}

