<?php


namespace LucianoJr\LaravelApiQueryHandler;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use LucianoJr\LaravelApiQueryHandler\Exceptions\UnknownFieldException;
use Schema;

class CollectionHandler
{
    const DESC_DIRECTION = 'desc';

    const ASC_DIRECTION = 'asc';

    protected $collection;

    protected $uriParser;

    protected $wheres = [];

    protected $orderBy = [];

    protected $limit;

    protected $page = 1;

    protected $offset = 0;

    protected $columns = ['*'];

    protected $relationColumns = [];

    protected $includes = [];

    protected $groupBy = [];

    protected $excludedParameters = [];

    protected $result;

    protected $perPage;

    public function __construct(Collection $collection, Request $request)
    {
        $this->orderBy = config('laravel-api-query-handler.orderBy', [
            'column' => 'id',
            'direction' => 'asc'
        ]);

        $this->perPage = config('laravel-api-query-handler.perPage', 9);

        $this->uriParser = new UriHandler($request);

        $this->collection = $collection;
    }

    public function handle()
    {
        $this->prepare();

        if ($this->hasWheres()) {
            array_map([$this, 'addWhereToQuery'], $this->wheres);
        }

        array_map([$this, 'addOrderByToQuery'], $this->orderBy);

        if ($this->hasGroupBy()) {
            $this->collection = $this->collection->sortBy($this->groupBy);
        }

        if ($this->hasLimit()) {
            $this->collection = $this->collection->take($this->limit);
        }

//        $this->collection = $this->collection->load($this->includes);
//dd($this->collection);
//        $this->collection = $this->collection->map(function ($item) {
//            dd($item);
//            $classOfObject = get_class($item);
//            $newItem = new $classOfObject();
//
//            foreach ($item->relationships as $key) {
//                $newItem->setAttribute($key, $item->{$key}());
//            }
//
//            foreach ($this->columns as $key) {
//                $newItem->setAttribute($key, $item->{$key});
//            }
//
//            return $newItem;
//        });

        return $this;
    }

    public function getIncludes()
    {
        return $this->includes;
    }

    public function all()
    {
        return $this->collection->all();
    }

    public function paginate()
    {
        $paginator = new \LucianoJr\LaravelApiQueryHandler\Paginator(
            $this->collection->forPage($this->page, $this->perPage),
            $this->collection->count(), $this->perPage,
            Paginator::resolveCurrentPage(),
            ['path' => Paginator::resolveCurrentPath()]
        );
        foreach ($this->uriParser->getQueryParameters() as $arrParameter) {
            if ($arrParameter['key'] == 'page') {
                continue;
            }

            $paginator->appends($arrParameter['key'], $arrParameter['value']);
        }

        return $paginator;
    }

    protected function prepare()
    {
        $this->setWheres($this->uriParser->whereParameters());

        $constantParameters = $this->uriParser->constantParameters();

        array_map([$this, 'prepareConstant'], $constantParameters);

        if ($this->hasIncludes() && $this->hasRelationColumns()) {
            $this->fixRelationColumns();
        }

        return $this;
    }

    public function hasColumns(): bool
    {
        return (empty($this->columns) || $this->columns[0] == ['*']);
    }

    /**
     * @return array
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @param array $columns
     * @return QueryBuilder
     */
    public function setColumns(array $columns): QueryBuilder
    {
        $this->columns = $columns;
        return $this;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return $this->query;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    public function setQuery(\Illuminate\Database\Eloquent\Builder $query)
    {
        $this->query = $query;
    }

    private function prepareConstant($parameter)
    {
        if (!$this->uriParser->hasQueryParameter($parameter)) {
            return;
        }

        $callback = [$this, $this->setterMethodName($parameter)];

        $callbackParameter = $this->uriParser->queryParameter($parameter);

        call_user_func($callback, $callbackParameter['value']);
    }

    private function setIncludes($includes)
    {
        $this->includes = array_filter(explode(',', $includes));
    }

    private function setPage($page)
    {
        $this->page = (int)$page;

        $this->offset = ($page - 1) * $this->limit;
    }

    private function setFields($columns)
    {
        $columns = array_filter(explode(',', $columns));

        $this->columns = $this->relationColumns = [];

        array_map([$this, 'setField'], $columns);
    }

    private function setField($column)
    {
        if ($this->isRelationColumn($column)) {
            return $this->appendRelationColumn($column);
        }

        $this->columns[] = $column;
    }

    private function appendRelationColumn($keyAndColumn)
    {
        list($key, $column) = explode('.', $keyAndColumn);

        $this->relationColumns[$key][] = $column;
    }

    private function fixRelationColumns()
    {
        $keys = array_keys($this->relationColumns);

        $callback = [$this, 'fixRelationColumn'];

        array_map($callback, $keys, $this->relationColumns);
    }

    private function fixRelationColumn($key, $columns)
    {
        $index = array_search($key, $this->includes);

        unset($this->includes[$index]);

        $foo = $this->closureRelationColumns([$key => $columns]);

        $this->includes[$key] = $foo[$key];

    }

    private function closureRelationColumns($columns)
    {
        $tables = $columns;
        $column2 = [];
        foreach ($tables as $table => $columns) {
            foreach ($columns as $column) {
                $column2[$table][] = $table . '.' . $column;
            }
        }
        $relations = [];
        foreach ($column2 as $relationshipKey => $fields) {
            $relations[$relationshipKey] = function ($query) use ($fields) {
                $query->select($fields);
            };
        }
        return $relations;
    }

    private function setOrderBy($order)
    {
        $this->orderBy = [];

        $orders = array_filter(explode('|', $order));

        array_map([$this, 'appendOrderBy'], $orders);
    }

    private function appendOrderBy($order)
    {
        if ($order == 'random') {
            $this->orderBy[] = 'random';
            return;
        }

        list($column, $direction) = explode(',', $order);

        $this->orderBy[] = [
            'column' => $column,
            'direction' => $direction,
        ];
    }

    private function setGroupBy($groups)
    {
        $this->groupBy = array_filter(explode(',', $groups));
    }

    private function setLimit($limit)
    {
        $limit = ($limit == 'unlimited') ? null : (int)$limit;

        $this->limit = $limit;
    }

    private function setWheres($parameters)
    {
        $this->wheres = $parameters;
    }

    private function addWhereToQuery($where)
    {
        extract($where);

        // For array values (whereIn, whereNotIn)
        if (isset($values)) {
            $value = $values;
        }
        if (!isset($operator)) {
            $operator = '';
        }

        /** @var mixed $key */
        if ($this->hasCustomFilter($key)) {
            /** @var string $type */
            return $this->applyCustomFilter($key, $operator, $value, $type);
        }

        if (!$this->hasTableColumn($key)) {
            throw new UnknownFieldException("Unknown fields '{$key}' requested");
        }

        /** @var string $type */
        if ($type == 'In') {
            $this->collection = $this->collection->whereIn($key, $value);
        } else if ($type == 'NotIn') {
            $this->collection = $this->collection->whereNotIn($key, $value);
        } else {
            if ($value == '[null]') {
                if ($operator == '=') {
                    $this->collection = $this->collection->whereNull($key);
                } else {
                    $this->collection = $this->collection->whereNotNull($key);
                }
            } else {
                if (!empty(strstr($value, '%'))) {
                    /** TODO: Make the search with like **/
                    $this->collection = $this->collection->where($key, $operator, $value);
                } else {
                    $this->collection = $this->collection->where($key, $operator, $value);
                }

            }
        }
    }

    private function addOrderByToQuery($order)
    {
        if ($order == 'random') {
            $this->collection = $this->collection->shuffle();
            return $this->collection;
        }

        extract($order);

        /** @var string $column */
        /** @var string $direction */
        switch ($direction) {
            case self::ASC_DIRECTION:
                $this->collection = $this->collection->sortBy($column);
                break;
            case self::DESC_DIRECTION:
                $this->collection = $this->collection->sortByDesc($column);
                break;
        }
    }

    private function applyCustomFilter($key, $operator, $value, $type = 'Basic')
    {
        $callback = [$this, $this->customFilterName($key)];

        $this->query = call_user_func($callback, $this->query, $value, $operator, $type);
    }

    private function isRelationColumn($column)
    {
        return (count(explode('.', $column)) > 1);
    }

    private function hasWheres()
    {
        return (count($this->wheres) > 0);
    }

    private function hasIncludes()
    {
        return (count($this->includes) > 0);
    }

    private function hasGroupBy()
    {
        return (count($this->groupBy) > 0);
    }

    private function hasLimit()
    {
        return ($this->limit);
    }

    private function hasRelationColumns()
    {
        return (count($this->relationColumns) > 0);
    }

    private function hasTableColumn($column)
    {
        if ($this->collection)
        return (Schema::hasColumn($this->collection->first()->getTable(), $column));
    }

    private function hasCustomFilter($key)
    {
        $methodName = $this->customFilterName($key);

        return (method_exists($this, $methodName));
    }

    private function setterMethodName($key)
    {
        return 'set' . studly_case($key);
    }

    private function customFilterName($key)
    {
        return 'filterBy' . studly_case($key);
    }
}
