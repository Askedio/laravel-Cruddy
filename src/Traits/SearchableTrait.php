<?php

namespace Askedio\Laravel5ApiController\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Trait SearchableTrait.
 *
 * @property array $searchable
 * @property string $table
 * @property string $primaryKey
 *
 * @method string getTable()
 */
trait SearchableTrait
{
    /**
     * @var array
     */
    protected $search_bindings = [];

    /**
     * Creates the search scope.
     *
     * @param \Illuminate\Database\Eloquent\Builder $q
     * @param string                                $search
     * @param float|null                            $threshold
     * @param bool                                  $entireText
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch(Builder $qry, $search, $threshold = null, $entireText = null)
    {
        return $this->scopeSearchRestricted($qry, $search, $threshold, $entireText);
    }

    public function scopeSearchRestricted(Builder $qry, $search, $threshold = null, $entireText = null)
    {
        $query = clone $qry;
        $query->select($this->getTable().'.*');
        $this->makeJoins($query);

        $search = mb_strtolower(trim($search));
        $words  = explode(' ', $search);

        $selects               = [];
        $this->search_bindings = [];
        $relevanceCount        = 0;

        foreach ($this->getColumns() as $column => $relevance) {
            $relevanceCount += $relevance;
            $queries = $this->getSearchQueriesForColumn($column, $relevance, $words);

            if ($entireText) {
                $queries[] = $this->getSearchQuery($column, $relevance, [$search], 30, '', '%');
            }

            foreach ($queries as $select) {
                $selects[] = $select;
            }
        }

        $this->addSelectsToQuery($query, $selects);

        // Default the threshold if no value was passed.
        if (is_null($threshold)) {
            $threshold = $relevanceCount / 4;
        }

        $this->filterQueryWithRelevance($query, $selects, $threshold);

        $this->makeGroupBy($query);

        $this->addBindingsToQuery($query, $this->search_bindings);

        $this->mergeQueries($query, $qry);

        return $qry;
    }

    /**
     * Returns database driver Ex: mysql, pgsql, sqlite.
     *
     * @return array
     */
    protected function getDatabaseDriver()
    {
        $key = $this->connection ?: config('database.default');

        return config('database.connections.'.$key.'.driver');
    }

    /**
     * Returns the search columns.
     *
     * @return array
     */
    protected function getColumns()
    {
        if (isset($this->searchable) && array_key_exists('columns', $this->searchable)) {
            return $this->searchable['columns'];
        }

        return DB::connection()->getSchemaBuilder()->getColumnListing($this->getTable());
    }

    /**
     * Returns whether or not to keep duplicates.
     *
     * @return array
     */
    protected function getGroupBy()
    {
        if (isset($this->searchable) && array_key_exists('groupBy', $this->searchable)) {
            return $this->searchable['groupBy'];
        }

        return false;
    }

    /**
     * Returns the tables that are to be joined.
     *
     * @return array
     */
    protected function getJoins()
    {
        return array_get($this->searchable, 'joins', []);
    }

    /**
     * Adds the sql joins to the query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    protected function makeJoins(Builder $query)
    {
        foreach ($this->getJoins() as $table => $keys) {
            $query->leftJoin($table, function ($join) use ($keys) {
                $join->on($keys[0], '=', $keys[1]);
                if (array_key_exists(2, $keys) && array_key_exists(3, $keys)) {
                    $join->where($keys[2], '=', $keys[3]);
                }
            });
        }
    }

    /**
     * Makes the query not repeat the results.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    protected function makeGroupBy(Builder $query)
    {
        if ($groupBy = $this->getGroupBy()) {
            $query->groupBy($groupBy);

            return $query;
        }

        $columns = $this->getTable().'.'.$this->primaryKey;

        $query->groupBy($columns);

        $joins = array_keys(($this->getJoins()));

        foreach (array_keys($this->getColumns()) as $column) {
            array_map(function ($join) use ($column, $query) {
                if (str_contains($column, $join)) {
                    $query->groupBy($column);
                }
            }, $joins);
        }
    }

    /**
     * Puts all the select clauses to the main query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array                                 $selects
     */
    protected function addSelectsToQuery(Builder $query, array $selects)
    {
        $selects = new Expression('max('.implode(' + ', $selects).') as relevance');
        $query->addSelect($selects);
    }

    /**
     * Adds the relevance filter to the query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array                                 $selects
     * @param float                                 $relevanceCount
     */
    protected function filterQueryWithRelevance(Builder $query, array $selects, $relevanceCount)
    {
        $comparator = $this->getDatabaseDriver() !== 'mysql' ? implode(' + ', $selects) : 'relevance';

        $relevanceCount = number_format($relevanceCount, 2, '.', '');

        $query->havingRaw("$comparator > $relevanceCount");
        $query->orderBy('relevance', 'desc');

        // add bindings to postgres
    }

    /**
     * Returns the search queries for the specified column.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string                                $column
     * @param float                                 $relevance
     * @param array                                 $words
     *
     * @return array
     */
    protected function getSearchQueriesForColumn($column, $relevance, array $words)
    {
        $queries = [];

        $queries[] = $this->getSearchQuery($column, $relevance, $words, 15);
        $queries[] = $this->getSearchQuery($column, $relevance, $words, 5, '', '%');
        $queries[] = $this->getSearchQuery($column, $relevance, $words, 1, '%', '%');

        return $queries;
    }

    /**
     * Returns the sql string for the given parameters.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string                                $column
     * @param string                                $relevance
     * @param array                                 $words
     * @param string                                $compare
     * @param float                                 $relevanceMultiplier
     * @param string                                $preWord
     * @param string                                $postWord
     *
     * @return string
     */
    protected function getSearchQuery($column, $relevance, array $words, $relevanceMultiplier, $preWord = '', $postWord = '')
    {
        $likeComparator = $this->getDatabaseDriver() === 'pgsql' ? 'ILIKE' : 'LIKE';
        $cases          = [];

        foreach ($words as $word) {
            $cases[]                 = $this->getCaseCompare($column, $likeComparator, $relevance * $relevanceMultiplier);
            $this->search_bindings[] = $preWord.$word.$postWord;
        }

        return implode(' + ', $cases);
    }

    /**
     * Returns the comparison string.
     *
     * @param string $column
     * @param string $compare
     * @param float  $relevance
     *
     * @return string
     */
    protected function getCaseCompare($column, $compare, $relevance)
    {
        /* commented out for CI
        }
         */
        $column = str_replace('.', '`.`', $column);
        $field  = 'LOWER(`'.$column.'`) '.$compare.' ?';

        return '(case when '.$field.' then '.$relevance.' else 0 end)';
    }

    /**
     * Adds the bindings to the query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array                                 $bindings
     */
    protected function addBindingsToQuery(Builder $query, array $bindings)
    {
        $count = $this->getDatabaseDriver() !== 'mysql' ? 2 : 1;
        for ($i = 0; $i < $count; $i++) {
            foreach ($bindings as $binding) {
                $type = $i === 0 ? 'where' : 'having';
                $query->addBinding($binding, $type);
            }
        }
    }

    /**
     * Merge our cloned query builder with the original one.
     *
     * @param \Illuminate\Database\Eloquent\Builder $clone
     * @param \Illuminate\Database\Eloquent\Builder $original
     */
    protected function mergeQueries(Builder $clone, Builder $original)
    {
        $tableName = DB::connection($this->connection)->getTablePrefix().$this->getTable();

        $original->from(DB::connection($this->connection)->raw("({$clone->toSql()}) as `{$tableName}`"));

        $original->mergeBindings($clone->getQuery());
    }
}
