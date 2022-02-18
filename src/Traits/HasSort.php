<?php
namespace Traversify\Traits;

use Exception;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;

trait HasSort
{
    use HasLeftJoin;

    private $joined = [];

    /**
     * Initialize sorts
     *
     * @param Builder $query
     * @param array $sort
     * @return Builder|void
     * @throws Exception
     */
    public function scopeSort(Builder $query, array $sort = [])
    {
        if (!$sorts = $this->sort) {
            throw new Exception("No column configured to be sorted");
        }

        if (empty($sort)) {
            return;
        }

        if (is_null($query->getSelect())) {
            $query->select(sprintf('%s.*', $query->getModel()->getTable()));
        }

        foreach($sorts as $sortable) {

            if (in_array($sortable, array_keys($sort)) && in_array(strtoupper($sort[$sortable]), ['ASC', 'DESC'])) {

                $this->createSortQuery($query, $sortable, $sort);
            }
        }
    }

    /**
     *
     * @param Builder $query
     * @param string $sortable
     * @param mixed $sort
     * @return void
     * @throws InvalidArgumentException
     */
    public function createSortQuery(Builder $query, string $sortable, array $sort)
    {
        $sortables = explode('.', $sortable);
        $sortColumn = array_pop($sortables);

        $motherOfAllRelationsTable = (new self)->getTable();
        $lastRelationTable = $motherOfAllRelationsTable;
        $currentModel = new self;

        if (count($sortables)) {

            foreach ($sortables as $index => $relationName) {

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

                    if (array_key_last($sortables) == $index) {
                        $lastRelationTable = $alias ?? $tableName;
                    }
                }
            }
        }

        $query->orderBy($lastRelationTable.'.'.$sortColumn, $sort[$sortable]);
    }
}
