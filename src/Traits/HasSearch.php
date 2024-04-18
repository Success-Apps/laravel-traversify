<?php
namespace Traversify\Traits;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
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
        $joins = $query->getQuery()->joins;

        if (!$this->search || !count($this->search)) {
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

        $searchables = $this->buildModelSearchColumnsArray();

        if (is_null($query->getQuery()->columns)) {
            $query->select(sprintf('%s.*', $query->getModel()->getTable()));
        }

        $columnList = [];

        $motherOfAllModels = (new self);
        $motherOfAllModelsTable = (new self)->getTable();

        if (count($searchables)) {

            foreach($searchables as $relations => $columns) {

                $relationsSplit = explode('.', $relations);
                $currentModel = new self;

                $result = $this->performJoinLogic($query, $currentModel, $relationsSplit, $motherOfAllModelsTable, $motherOfAllModels);

                foreach ($columns as $searchColumn) {
                    $currentColumn = $this->prepSearchId($result['last_model'], $result['last_relation_table'], $searchColumn);
                    $columnList[] = $currentColumn;
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

    }
    
    public function scopeSetSearch($query, array $search)
    {
        $this->search = $search;
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
     * Build Model Filters Array
     *
     * @return array
     */
    private function buildModelSearchColumnsArray(): array
    {
        $filterRelations = [];

        sort($this->search);

        foreach ($this->search as $item) {

            $relation = $this->getSearchItemRelation($item);

            if (!in_array($relation, $filterRelations)) {
                $filterRelations[] =  $relation;
            }
        }

        $summaryArray = [];

        foreach ($filterRelations as $item) {

            if (!$item['relation']) {
                $parent = new self;
                $item['relation'] = $parent->getTable() . '_columns_only';
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
    private function getSearchItemRelation(string $item): array
    {

        $itemParts = explode('.', $item);
        $searchColumn = array_pop($itemParts);

        return [
            'column' => $searchColumn,
            'relation' => implode('.', $itemParts)
        ];
    }
}
