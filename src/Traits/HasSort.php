<?php
namespace Traversify\Traits;

use Exception;
use Illuminate\Support\Str;
use RuntimeException;
use InvalidArgumentException;
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
     * @return void
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function scopeSort(Builder $query, Array $sort = [])
    {
        if(!$sorts = $this->sort) {
            throw new Exception("No column configured to be sorted");
        }

        if(empty($sort)) {
            return;
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
     * @param mixed $sortable
     * @param mixed $sort
     * @return void
     * @throws InvalidArgumentException
     */
    public function createSortQuery(Builder $query, array $sortable, array $sort)
    {
        $sortables = explode('.', $sortable);

        $sortColumn = array_pop($sortables);

        $model = new self;

        foreach($sortables as $relationship) {
            $model = $model->$relationship()->getRelated();
        }

        $keyName = $model->getKeyName();

        $tableName = $model->getTable();

        if (count($sortables) && !$this->relationshipIsAlreadyJoined($query, $tableName)) {

            $tableName = count($sortables) === 1 ? strtolower($sortables[0]) : $tableName;

            $this->performJoinForEloquent($query, $relation);
            $query->performJoinForEloquent(implode('.', $sortables), $tableName);
        }

        $sortColumnAlias = "sort_column_${tableName}_${sortColumn}";

        if(!$query->getQuery()->columns) {
            $query->select($this->getTable() . '.*');
        }

        $query->selectRaw("CONCAT($tableName.$sortColumn, ';',$tableName.$keyName) as $sortColumnAlias");

        $query->orderBy($sortColumnAlias, $sort[$sortable]);
    }
}
