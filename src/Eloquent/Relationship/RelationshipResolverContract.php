<?php

declare(strict_types=1);

namespace Deluxetech\LaRepo\Eloquent\Relationship;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Deluxetech\LaRepo\Contracts\CriteriaContract;
use Illuminate\Database\Eloquent\Relations\Relation;

interface RelationshipResolverContract
{
    /**
     * @param Collection<int,Model> $records
     */
    public function resolveOnRecords(
        Collection $records,
        string $relation,
        ?CriteriaContract $criteria = null,
    ): void;

    public function resolveOnQuery(
        Relation|Builder $query,
        string $relation,
        ?CriteriaContract $criteria = null,
    ): void;
}