<?php

use Bernskiold\ExportEngine\Data\ExportOptions;
use Bernskiold\ExportEngine\Data\ListingFilters;
use Bernskiold\ExportEngine\Tests\Fixtures\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;

class PeopleListingForListingTest extends ListingFilters
{
    public function __construct(
        public ?string $search = null,
        public bool $onlyActive = true,
    ) {}

    public function applyTo(Builder $query, ?Authenticatable $user = null): Builder
    {
        return $query->when($this->search, fn (Builder $query) => $query->where('name', 'like', "%{$this->search}%"));
    }
}

it('makes a listing again from its array', function () {
    $listing = new PeopleListingForListingTest(search: 'Ada', onlyActive: false);

    expect(PeopleListingForListingTest::fromArray($listing->toArray()))->toEqual($listing);
});

it('ignores the values the listing does not know', function () {
    $listing = PeopleListingForListingTest::fromArray(['search' => 'Ada', 'removed_filter' => [1]]);

    expect($listing->search)->toBe('Ada')
        ->and($listing->toArray())->not->toHaveKey('removed_filter');
});

it('makes a listing from its class name', function () {
    expect(ListingFilters::resolve(PeopleListingForListingTest::class, ['search' => 'Ada']))
        ->toBeInstanceOf(PeopleListingForListingTest::class)
        ->search->toBe('Ada');
});

it('does not make anything that is not a listing', function (mixed $class) {
    expect(ListingFilters::resolve($class, []))->toBeNull();
})->with([
    'no listing' => null,
    'a different class' => User::class,
    'the base class' => ListingFilters::class,
    'not a class' => 'nonsense',
]);

it('gives the listing class to the export as an option', function () {
    expect(ExportOptions::from(['listing' => 'old', 'chart' => 'bar'])->withListing(new PeopleListingForListingTest)->toArray())
        ->toBe(['listing' => PeopleListingForListingTest::class, 'chart' => 'bar']);
});

it('adds an option only when the exporter does not set it', function () {
    expect(ExportOptions::from(['locale' => 'sv'])->withDefault('locale', 'de')->withDefault('order', ['a'])->withDefault('empty', [])->toArray())
        ->toBe(['order' => ['a'], 'locale' => 'sv']);
});
