<?php

namespace Bernskiold\ExportEngine\Settings;

use BackedEnum;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Select one option. The form shows cards for a small number of options,
 * and a dropdown for a long list.
 *
 * The export keeps the values as strings, because the browser sends
 * strings. Thus a kept value is equal to a new value.
 */
class ChoiceSetting extends ExportSetting
{
    /**
     * @var array<string, string>|Closure(): array<string, string>
     */
    protected array|Closure $options = [];

    /**
     * @var array<string, string>
     */
    protected array $optionDescriptions = [];

    /**
     * @var class-string<BackedEnum>|null
     */
    protected ?string $enum = null;

    protected bool $dropdown = false;

    protected bool $segmented = false;

    protected int $columns = 1;

    /**
     * @param  array<string|int, string>|Closure(): array<string|int, string>|class-string<BackedEnum>  $options
     */
    public function options(array|Closure|string $options): static
    {
        if (is_string($options)) {
            return $this->enum($options);
        }

        $this->options = $options;

        return $this;
    }

    /**
     * Show the cases of an enum as the options, or only some of the cases.
     * The form uses `label()` and `description()` when the enum has them.
     * The exporter gets the case.
     *
     * @param  class-string<BackedEnum>  $enum
     * @param  array<int, BackedEnum>|null  $cases
     */
    public function enum(string $enum, ?array $cases = null): static
    {
        $this->enum = $enum;

        foreach ($cases ?? $enum::cases() as $case) {
            $this->options[(string) $case->value] = method_exists($case, 'label') ? $case->label() : $case->name;

            if (method_exists($case, 'description')) {
                $this->optionDescriptions[(string) $case->value] = $case->description();
            }
        }

        return $this;
    }

    public function dropdown(bool $dropdown = true): static
    {
        $this->dropdown = $dropdown;

        return $this;
    }

    /**
     * Show the options as a segmented control, for a small number of short
     * options.
     */
    public function segmented(bool $segmented = true): static
    {
        $this->segmented = $segmented;

        return $this;
    }

    /**
     * Put the option cards next to each other, for short options.
     */
    public function columns(int $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    public function getColumns(): int
    {
        return $this->columns;
    }

    /**
     * @return array<string, string>
     */
    public function getOptions(): array
    {
        if ($this->options instanceof Closure) {
            $this->options = ($this->options)();
        }

        return $this->options;
    }

    /**
     * The options in the shape that the radio cards use.
     *
     * @return array<int, array{value: string, label: string, description: string}>
     */
    public function radioOptions(): array
    {
        $options = $this->getOptions();

        return array_map(fn (string|int $value) => [
            'value' => (string) $value,
            'label' => $options[$value],
            'description' => $this->optionDescriptions[(string) $value] ?? '',
        ], array_keys($options));
    }

    public function rules(ExportSettingsContext $context): array
    {
        return [
            'required',
            Rule::in(array_map('strval', array_keys($this->getOptions()))),
        ];
    }

    /**
     * "dropdown" for a long list, "segmented" for a segmented control, and
     * "choice" for cards.
     */
    public function type(): string
    {
        return match (true) {
            $this->dropdown => 'dropdown',
            $this->segmented => 'segmented',
            default => 'choice',
        };
    }

    public function defaultValue(ExportSettingsContext $context): mixed
    {
        $default = parent::defaultValue($context);

        return $default === null ? null : (string) $default;
    }

    public function cast(mixed $value): mixed
    {
        if ($this->enum === null || $value === null) {
            return $value;
        }

        return $this->enum::tryFrom($value);
    }
}
