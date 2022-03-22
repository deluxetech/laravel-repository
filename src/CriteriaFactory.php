<?php

namespace Deluxetech\LaRepo;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use Deluxetech\LaRepo\Rules\RepositorySorting;
use Deluxetech\LaRepo\Contracts\SortingContract;
use Deluxetech\LaRepo\Contracts\CriteriaContract;
use Deluxetech\LaRepo\Rules\RepositoryFiltration;
use Deluxetech\LaRepo\Rules\RepositoryTextSearch;
use Deluxetech\LaRepo\Contracts\TextSearchContract;
use Deluxetech\LaRepo\Contracts\LoadContextContract;
use Deluxetech\LaRepo\Contracts\FiltersCollectionContract;

final class CriteriaFactory
{
    /**
     * Creates a new criteria object.
     *
     * @param  TextSearchContract|string|null $textSearch
     * @param  SortingContract|string|null $sorting
     * @param  FiltersCollectionContract|string|null $filters
     * @param  LoadContextContract|null $context
     * @return CriteriaContract
     */
    public static function create(
        TextSearchContract|string|null $textSearch = null,
        SortingContract|string|null $sorting = null,
        FiltersCollectionContract|string|null $filters = null,
        ?LoadContextContract $context = null
    ): CriteriaContract {
        return App::makeWith(CriteriaContract::class, [
            'textSearch' => $textSearch,
            'sorting' => $sorting,
            'filters' => $filters,
            'context' => $context,
        ]);
    }

    /**
     * Creates a new criteria object using parameters passed via request.
     *
     * @param  LoadContextContract|null $context
     * @param  string|null $textSearchKey
     * @param  string|null $sortingKey
     * @param  string|null $filtersKey
     * @return CriteriaContract
     */
    public static function createFromRequest(
        ?LoadContextContract $context = null,
        ?string $textSearchKey = null,
        ?string $sortingKey = null,
        ?string $filtersKey = null
    ): CriteriaContract {
        $textSearchKey ??= Config::get('larepo.request_text_search_key');
        $sortingKey ??= Config::get('larepo.request_sorting_key');
        $filtersKey ??= Config::get('larepo.request_filters_key');

        $textSearch = Request::input($textSearchKey);
        $sorting = Request::input($sortingKey);
        $filters = Request::input($filtersKey);

        self::validate(
            $textSearchKey,
            $sortingKey,
            $filtersKey,
            $textSearch,
            $sorting,
            $filters
        );

        return self::create($textSearch, $sorting, $filters, $context);
    }

    /**
     * Validates criteria params.
     *
     * @param  string $textSearchKey
     * @param  string $sortingKey
     * @param  string $filtersKey
     * @param  string|null $textSearch
     * @param  string|null $sorting
     * @param  string|null $filters
     * @return void
     * @throws ValidationException
     */
    protected static function validate(
        string $textSearchKey,
        string $sortingKey,
        string $filtersKey,
        ?string $textSearch = null,
        ?string $sorting = null,
        ?string $filters = null
    ): void {
        $data = $rules = [];

        if (!is_null($textSearch)) {
            $data[$textSearchKey] = $textSearch;
            $rules[$textSearchKey] = [new RepositoryTextSearch()];
        }

        if (!is_null($sorting)) {
            $data[$sortingKey] = $sorting;
            $rules[$sortingKey] = [new RepositorySorting()];
        }

        if (!is_null($filters)) {
            $data[$filtersKey] = $filters;
            $rules[$filtersKey] = [new RepositoryFiltration()];
        }

        if ($data && $rules) {
            Validator::make($data, $rules)->validate();
        }
    }
}
