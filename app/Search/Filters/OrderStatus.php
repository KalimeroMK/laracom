<?php

namespace App\Search\Filters;

use Illuminate\Database\Eloquent\Builder;

class OrderStatus {

    /**
     * Apply a given search value to the builder instance.
     *
     * @param Builder $builder
     * @param mixed $value
     * @return Builder $builder
     */
    public static function apply(Builder $builder, $value) {
                
        return $builder->where('order_status_id', $value);
    }

}
