<?php
namespace Traversify\Traits;

use Exception;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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
     * @throws Exception
     */
    public function scopeSort(Builder $query, array $sort = []): void
    {
        $joins = $query->getQuery()->joins;

        if (!$sortFilters = $this->sort) {
            Log::error('No column configured to be sorted - ' . $this::class);
            return;
        }

        if (empty($sort)) {
            return;
        }

        if (is_null($query->getQuery()->columns)) {
            $query->select(sprintf('%s.*', $query->getModel()->getTable()));
        }

        sort($sortFilters);

        foreach ($sort as $key => $value) {

            if (!in_array($key, $sortFilters) || !in_array(strtoupper($value), ['ASC', 'DESC'])) {
                continue;
            }

            $currentModel = new self;
            $relationsSplit = explode('.', $key);
            $sortColumn = array_pop($relationsSplit);
            $result['last_relation_table'] = $currentModel->getTable();

            if (count($relationsSplit)) {
                $result = $this->performJoinLogic($query, $currentModel, $relationsSplit, $currentModel->getTable(), $currentModel);
            }

            // For the use of Strict DB Connection
            $tableReflector = DB::connection()->getSchemaBuilder()->getColumnListing($result['last_relation_table']);

            if (in_array($sortColumn, $tableReflector)) {
                $groupBys = $query->getQuery()->groups;
                if ($groupBys && !in_array($result['last_relation_table'].'.'.$sortColumn, $groupBys)) {
                    $query->groupBy($result['last_relation_table'].'.'.$sortColumn);
                }

                $orderBys = $query->getQuery()->orders;
                if (!$orderBys || !in_array($result['last_relation_table'].'.'.$sortColumn, $orderBys)) {
                    $query->orderBy($result['last_relation_table'].'.'.$sortColumn, $value);
                }
            } else {
                // Non Qualified column, or a column rsulting from a calculation
                $orderBys = $query->getQuery()->orders;
                if ($orderBys && !in_array($sortColumn, $orderBys)) {
                    $query->orderBy($result['last_relation_table'].'.'.$sortColumn, $value);
                }
            }

        }

    }
}
