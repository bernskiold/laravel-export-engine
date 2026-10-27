<?php

namespace Bernskiold\ExportEngine\Settings;

use BackedEnum;
use Closure;

/**
 * One question an export asks before the engine makes it.
 *
 * The export keeps each value in its plain form: a string, a number or a
 * boolean. `cast()` changes the value into the type the exporter uses.
 */
abstract class ExportSetting
{
    protected ?string $description = null;

    protected ?string $note = null;

    protected ?string $row = null;

    protected mixed $default = null;

    /**
     * @var array<int, string>|null
     */
    protected ?array $formats = null;

    protected bool $visible = true;

    protected bool $remembered = true;

    /**
     * The key of a different setting, and the value it must have before the
     * form shows this setting.
     *
     * @var array{0: string, 1: mixed}|null
     */
    protected ?array $shownWhen = null;

    final public function __construct(
        public readonly string $key,
        public readonly string $label,
    ) {
        $this->boot();
    }

    public static function make(string $key, string $label): static
    {
        return new static($key, $label);
    }

    protected function boot(): void
    {
        //
    }

    /**
     * The validation rules for the value of this setting.
     *
     * @return array<int, mixed>
     */
    abstract public function rules(ExportSettingsContext $context): array;

    /**
     * The kind of field for the setting, for example "toggle". The engine
     * has no forms. The application uses this value to select the field
     * it shows.
     */
    abstract public function type(): string;

    public function description(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * A line below the field, for information the person must see. The
     * description shows only in the tooltip of the label.
     */
    public function note(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    /**
     * Put this setting next to the other settings with the same row, for
     * example a width and a height.
     */
    public function inRow(string $row): static
    {
        $this->row = $row;

        return $this;
    }

    public function getRow(): ?string
    {
        return $this->row;
    }

    /**
     * @param  mixed|Closure(ExportSettingsContext): mixed  $default
     */
    public function default(mixed $default): static
    {
        $this->default = $default;

        return $this;
    }

    /**
     * Show this setting only for these formats. For example, a CSV file
     * has no sheets.
     */
    public function onlyForFormats(string ...$formats): static
    {
        $this->formats = $formats;

        return $this;
    }

    public function visibleWhen(bool $visible): static
    {
        $this->visible = $visible;

        return $this;
    }

    /**
     * Whether the form starts from the value the person selected last time,
     * or always from the default. Do not remember a setting that gets its
     * default from the page. If you do, a value from an earlier day replaces
     * the value that is on the screen now.
     */
    public function remembered(bool $remembered = true): static
    {
        $this->remembered = $remembered;

        return $this;
    }

    /**
     * Show this setting only when a different setting has this value, for
     * example the width of a custom size. `visibleWhen()` is set before the
     * form opens. This method follows the values while the person changes
     * them.
     */
    public function showWhen(string $key, mixed $value): static
    {
        $this->shownWhen = [$key, $value instanceof BackedEnum ? $value->value : $value];

        return $this;
    }

    /**
     * @return array{0: string, 1: mixed}|null
     */
    public function shownWhen(): ?array
    {
        return $this->shownWhen;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function isShownFor(array $values): bool
    {
        if ($this->shownWhen === null) {
            return true;
        }

        [$key, $value] = $this->shownWhen;

        return (string) ($values[$key] ?? '') === (string) $value;
    }

    public function isRemembered(): bool
    {
        return $this->remembered;
    }

    public function isOfferedFor(string $format): bool
    {
        if (! $this->visible) {
            return false;
        }

        return $this->formats === null || in_array($format, $this->formats, true);
    }

    /**
     * The default, in the plain form that the export keeps.
     */
    public function defaultValue(ExportSettingsContext $context): mixed
    {
        $default = $this->default instanceof Closure ? ($this->default)($context) : $this->default;

        return $default instanceof BackedEnum ? $default->value : $default;
    }

    /**
     * Change a kept value into the type that the exporter uses.
     */
    public function cast(mixed $value): mixed
    {
        return $value;
    }
}
