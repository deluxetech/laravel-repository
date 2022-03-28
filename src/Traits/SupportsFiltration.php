<?php

namespace Deluxetech\LaRepo\Traits;

use Illuminate\Support\Facades\App;
use Deluxetech\LaRepo\FilterFactory;
use Deluxetech\LaRepo\Enums\FilterOperator;
use Deluxetech\LaRepo\Enums\BooleanOperator;
use Deluxetech\LaRepo\Contracts\FilterContract;
use Deluxetech\LaRepo\Contracts\FilterOptimizerContract;
use Deluxetech\LaRepo\Contracts\FiltersCollectionContract;
use Deluxetech\LaRepo\Contracts\FiltersCollectionFormatterContract;

trait SupportsFiltration
{
    /**
     * The data filtration params.
     *
     * @var FiltersCollectionContract|null
     */
    protected ?FiltersCollectionContract $filters = null;

    /** @inheritdoc */
    public function getFilters(): ?FiltersCollectionContract
    {
        return $this->filters;
    }

    /** @inheritdoc */
    public function setFiltersRaw(string $rawStr): static
    {
        $dataArr = App::make(FiltersCollectionFormatterContract::class)->parse($rawStr);

        if (!$dataArr) {
            throw new \Exception(__('larepo::exceptions.invalid_filters_string'));
        }

        $filters = App::make(FiltersCollectionContract::class);

        foreach ($dataArr as $filterData) {
            $filter = $this->createFilter($filterData);
            $filters->add($filter);
        }

        App::make(FilterOptimizerContract::class)->optimize($filters);
        $this->setFilters($filters);

        return $this;
    }

    /** @inheritdoc */
    public function setFilters(?FiltersCollectionContract $filters): static
    {
        $this->filters = $filters;

        return $this;
    }

    /** @inheritdoc */
    public function where(string $attr, string $operator, mixed $value = null): static
    {
        $this->addFilter($attr, $operator, $value, BooleanOperator::AND);

        return $this;
    }

    /** @inheritdoc */
    public function orWhere(string $attr, string $operator, mixed $value = null): static
    {
        $this->addFilter($attr, $operator, $value, BooleanOperator::OR);

        return $this;
    }

    /**
     * Adds a new filter.
     *
     * @param  string $attr
     * @param  string $operator
     * @param  mixed  $value
     * @param  string $boolean
     * @return void
     */
    protected function addFilter(string $attr, string $operator, mixed $value, string $boolean): void
    {
        if (is_null($this->filters)) {
            $this->setFilters(App::make(FiltersCollectionContract::class));
        }

        $filter = FilterFactory::create($operator, $attr, $value, $boolean);
        $this->filters->add($filter);
    }

    /**
     * Creates a repository filter object from the given associative array.
     *
     * @param  array $data
     * @return FiltersCollectionContract|FilterContract
     */
    protected function createFilter(array $data): FiltersCollectionContract|FilterContract
    {
        $boolean = $data['boolean'] ?? BooleanOperator::AND;

        if (isset($data['items'])) {
            $collection = App::makeWith(FiltersCollectionContract::class, [$boolean]);

            foreach ($data['items'] as $item) {
                $item = $this->createFilter($item);
                $collection->add($item);
            }

            return $collection;
        } else {
            $attr = $data['attr'];
            $operator = $data['operator'];
            $value = $data['value'] ?? null;

            if (
                $operator === FilterOperator::EXISTS ||
                $operator === FilterOperator::DOES_NOT_EXIST
            ) {
                $value = $this->createFilter(['items' => $value]);
            }

            return FilterFactory::create($operator, $attr, $value, $boolean);
        }
    }
}
