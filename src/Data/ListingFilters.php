<?php

namespace Bernskiold\ExportEngine\Data;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use ReflectionClass;
use ReflectionParameter;

/**
 * The search and filters of a table. The table and its export use the same
 * listing, so they narrow the same query.
 *
 * The table makes a listing from its properties and gives `toArray()` to
 * the export as its filters. The exporter makes the listing again with
 * `fromArray()` and applies it for the person who asked for the export. A
 * queued export runs with no signed-in user, so `applyTo()` must use
 * `$user` and not `auth()->user()`.
 *
 * Declare the state of a listing as promoted constructor properties.
 * `fromArray()` ignores the values that the constructor does not know.
 *
 * @implements Arrayable<string, mixed>
 */
abstract class ListingFilters implements Arrayable
{
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    abstract public function applyTo(Builder $query, ?Authenticatable $user = null): Builder;

    /**
     * Make the listing from its class name, for an exporter that serves
     * more than one table. A value that is not a listing class gives null.
     * Thus a changed option cannot make the engine create a different class.
     *
     * @param  array<string, mixed>  $data
     */
    public static function resolve(mixed $class, array $data): ?self
    {
        if (! is_string($class) || ! is_subclass_of($class, self::class)) {
            return null;
        }

        return $class::fromArray($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $class = new ReflectionClass(static::class);

        $parameters = collect($class->getConstructor()?->getParameters() ?? [])
            ->map(fn (ReflectionParameter $parameter) => $parameter->getName())
            ->all();

        return $class->newInstanceArgs(collect($data)->only($parameters)->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
