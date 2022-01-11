<?php
namespace Traversify\Traits;

use Exception;
use RuntimeException;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Kirschbaum\PowerJoins\PowerJoins;
use Illuminate\Database\Eloquent\Builder;

trait HasSearch
{
    use PowerJoins;

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

        $sortedSearches = $this->sortSearches($this->search);

        $columns = [];

        foreach($sortedSearches as $searchable) {

            $searchables = explode('.', $searchable);

            $searchColumn = array_pop($searchables);

            if (count($searchables)) {

                $model = new self;

                foreach($searchables as $key => $relationship) {

                    $model = $model->$relationship()->getRelated();

                    $tableName = $model->getTable();
                    $alias = time();

                    if ($key === array_key_last($searchables)) {

                        $column = $this->prepSearchId($tableName, $searchColumn);

                        array_push($columns, $column);
                    }

                    $tableJoins = collect($query->getQuery()->joins)->pluck('table');

                    [$joined, $cleanJoinList] = $this->isJoined($tableJoins, $tableName);

                    if(!$joined) {
                        if (count($searchables) === 1) {
                            $query->leftJoinRelationship(implode('.', $searchables), $alias);
                        } else {
                            $query->leftJoinRelationshipUsingAlias(implode('.', $searchables), $alias);
                        }
                    }
                }

            } else {

                $tableName = $this->getTable();

                $column = $this->prepSearchId($tableName, $searchColumn);

                array_push($columns, $column);
            }
        }

        $columns = implode(', ', $columns);

        return $query->whereRaw("CONCAT_WS(' ', {$columns}) {$this->like} ?", "%{$keyword}%");
    }

    /**
     * Sort Searches
     *
     * @param $searches
     * @return array
     */
    private function sortSearches($searches) {

        $counted = [];
        $i = 0;

        foreach ($searches as $search) {
            array_push($counted, ['index'=> $i, 'count' => substr_count($search, '.')]);
            $i++;
        }

        array_multisort(array_column($counted, 'count'), SORT_DESC, $counted);

        $sorted = [];

        foreach ($counted as $count) {
            array_push($sorted, $searches[$count['index']]);
        }

        return  $sorted;
    }

    /**
     * Check if query has Join
     *
     * @param $tableJoins
     * @param $tableName
     * @return array
     */
    private function isJoined($tableJoins, $tableName) {

        $newJoins = $tableJoins->map(function ($join) {

            if (str_contains($join, ' as ')) {
                $explode = explode(' as ', $join);
                $join = $explode[0];
            }

            return $join;

        });

        if ($newJoins->contains($tableName)) {
            return [
                true,
                $newJoins->unique()
            ];
        }

        return [
            false,
            $newJoins->unique()
        ];
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

            $column = "'".$prefix."'".', '.$tableName.'.'.$searchColumn;
        }

        return $column;
    }

}

