<?php
namespace Traversify\Traits;

use Exception;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\SoftDeletes;
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
     * @param String $keyword
     * @throws Exception
     */
    public function scopeSearch(Builder $query, String $keyword = '')
    {
        if (!$searches = $this->search) {
            throw new Exception('No column configured to be searched');
        }

        if (empty($keyword)) {
            return;
        }

        $key = $this->connection ?: config('database.default');

        if(config('database.connections.' . $key . '.driver') == 'pgsql') {
            $this->like = 'ILIKE';
        }

        $searchableList = $this->buildModelFiltersArray('search');

        if (is_null($query->getSelect())) {
            $query->select(sprintf('%s.*', $query->getModel()->getTable()));
        }

        $columnList = [];

        $motherOfAllRelationsTable = (new self)->getTable();
        $lastRelationTable = $motherOfAllRelationsTable;
        $tableName = null;

        foreach($searchableList as $relations => $columns) {

            $lastRelationTable = $motherOfAllRelationsTable;

            $relationsSplit = explode('.', $relations);

            $currentModel = new self;

            foreach ($relationsSplit as $index => $relationName) {

                if ($relationName != $motherOfAllRelationsTable) {

                    $relation = $currentModel->{$relationName}();
                    $currentModel = $relation->getRelated();
                    $tableName = $currentModel->getTable();

                    $alias = null;

                    if (!$this->relationshipIsAlreadyJoined($query, $tableName)) {

                        if ($tableName == $motherOfAllRelationsTable) {

                            $alias = 'A'.time();
                        }

                        $this->performJoinForEloquent($query, $relation, $alias);
                    } else {

                        $tableName = $this->getTableOrAliasForModel($query, $tableName);
                    }

                    if (array_key_last($relationsSplit) == $index) {
                        $lastRelationTable = $alias ?? $tableName;
                    }
                }
            }

            foreach ($columns as $searchColumn) {

                $currentColumn = $this->prepSearchId($lastRelationTable, $searchColumn);

                array_push($columnList, $currentColumn);
            }
        }

        $searchColumns = implode(', ', $columnList);

        return $query->whereRaw("CONCAT_WS(' ', {$searchColumns}) {$this->like} ?", "%{$keyword}%");
    }

    /**
     * Setup ID Search
     *
     * @param $tableName
     * @param $searchColumn
     * @return string
     */
    private function prepSearchId($tableName, $searchColumn) {

        $column = $tableName.'.'.$searchColumn;

        if ($searchColumn == 'id') {

            $prefix =  strtoupper(substr($tableName, 0, 1));

            $column = "CONCAT('".$prefix."'".', '.$tableName.'.'.$searchColumn.")";
        }

        return $column;
    }
}

