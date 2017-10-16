<?php

namespace LucianoJr\LaravelApiQueryHandler;


use Illuminate\Pagination\LengthAwarePaginator;

class Paginator extends LengthAwarePaginator
{
    public function getMeta()
    {
        $meta = parent::toArray();

        array_pull($meta, 'data');

        return $meta;
    }

}
