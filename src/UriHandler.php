<?php

namespace LucianoJr\LaravelApiQueryHandler;

use Illuminate\Http\Request;

class UriHandler
{
    const PATTERN = '/!=|=|<=|<|>=|>/';

    const ARRAY_QUERY_PATTERN = '/(.*)\[\]/';

    protected $request;

    protected $constantParameters = [
        'order_by',
        'group_by',
        'limit',
        'page',
        'fields',
        'includes',
    ];

    protected $uri;

    protected $queryUri;

    protected $queryParameters = [];

    /**
     * UriHandler constructor.
     *
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;

        $this->uri = $request->getRequestUri();

        $this->setQueryUri($this->uri);

        if ($this->hasQueryUri()) {
            $this->setQueryParameters();
        }
    }

    public static function getPattern()
    {
        return self::PATTERN;
    }

    public static function getArrayQueryPattern()
    {
        return self::ARRAY_QUERY_PATTERN;
    }

    public function queryParameter($key)
    {
        $keys = array_pluck($this->queryParameters, 'key');

        $queryParameters = array_combine($keys, $this->queryParameters);

        return $queryParameters[$key];
    }

    public function constantParameters()
    {
        return $this->constantParameters;
    }

    public function whereParameters()
    {
        return array_filter(
            $this->queryParameters,
            function ($queryParameter) {
                $key = $queryParameter['key'];
                return (!in_array($key, $this->constantParameters));
            }
        );
    }

    private function setQueryUri($uri)
    {
        $explode = explode('?', $uri);

        $this->queryUri = (isset($explode[1])) ? rawurldecode($explode[1]) : null;
    }

    private function setQueryParameters()
    {
        $queryParameters = [];
        foreach ($this->request->all() as $key => $value) {
            $values = $value;
            if (is_array($value)) {
                $values = implode(',', array_keys($value));
            }
            $queryParameters[] = $key . '=' . $values;
        }
        array_map([$this, 'appendQueryParameter'], $queryParameters);
    }

    /**
     * @return array
     */
    public function getQueryParameters(): array
    {
        return $this->queryParameters;
    }

    private function appendQueryParameter($parameter)
    {
        // whereIn expression
        preg_match(self::ARRAY_QUERY_PATTERN, $parameter, $arrayMatches);
        if (count($arrayMatches) > 0) {
            $this->appendQueryParameterAsWhereIn($parameter, $arrayMatches[1]);
            return;
        }

        // basic where expression
        $this->appendQueryParameterAsBasicWhere($parameter);
    }

    private function appendQueryParameterAsBasicWhere($parameter)
    {
        preg_match(self::PATTERN, $parameter, $matches);

        $operator = $matches[0];

        list($key, $value) = explode($operator, $parameter);

        if (!$this->isConstantParameter($key) && $this->isLikeQuery($value)) {
            $operator = 'like';
            $value = str_replace('*', '%', $value);
        }

        $this->queryParameters[] = [
            'type' => 'Basic',
            'key' => $key,
            'operator' => $operator,
            'value' => $value
        ];
    }

    private function appendQueryParameterAsWhereIn($parameter, $key)
    {
        if (str_contains($parameter, '!=')) {
            $type = 'NotIn';
            $seperator = '!=';
        } else {
            $type = 'In';
            $seperator = '=';
        }

        $index = null;
        foreach ($this->queryParameters as $_index => $queryParameter) {
            if ($queryParameter['type'] == $type && $queryParameter['key'] == $key) {
                $index = $_index;
                break;
            }
        }

        if ($index !== null) {
            $this->queryParameters[$index]['values'][] = explode($seperator, $parameter)[1];
        } else {
            $this->queryParameters[] = [
                'type' => $type,
                'key' => $key,
                'values' => [explode($seperator, $parameter)[1]]
            ];
        }
    }

    public function hasQueryUri()
    {
        return ($this->queryUri);
    }

    public function getQueryUri()
    {
        return $this->queryUri;
    }

    public function hasQueryParameters()
    {
        return (count($this->queryParameters) > 0);
    }

    public function hasQueryParameter($key)
    {
        $keys = array_pluck($this->queryParameters, 'key');

        return (in_array($key, $keys));
    }

    private function isLikeQuery($query)
    {
        $pattern = "/^\*|\*$/";

        return (preg_match($pattern, $query, $matches));
    }

    private function isConstantParameter($key)
    {
        return (in_array($key, $this->constantParameters));
    }
}
