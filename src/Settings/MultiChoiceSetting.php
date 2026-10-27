<?php

namespace Bernskiold\ExportEngine\Settings;

use BackedEnum;
use Closure;

/**
 * Select one or more options. The form shows checkboxes.
 *
 * The export keeps a list of the values as strings. When the options come
 * from an enum, the exporter gets enum cases.
 */
class MultiChoiceSetting extends ChoiceSetting
{
    protected mixed $default = [];

    public function rules(ExportSettingsContext $context): array
    {
        $allowed = array_map('strval', array_keys($this->getOptions()));

        return [
            'required',
            'array',
            'min:1',
            function (string $attribute, mixed $value, Closure $fail) use ($allowed) {
                if (is_array($value) && array_diff(array_map('strval', $value), $allowed) !== []) {
                    $fail(__('The :attribute contains an option that cannot be chosen.'));
                }
            },
        ];
    }

    public function type(): string
    {
        return 'multi-choice';
    }

    /**
     * All the options, unless the setting has a default.
     */
    public function defaultValue(ExportSettingsContext $context): mixed
    {
        $default = $this->default instanceof Closure ? ($this->default)($context) : $this->default;

        if ($default === []) {
            $default = array_keys($this->getOptions());
        }

        return array_values(array_map(fn (mixed $value) => (string) ($value instanceof BackedEnum ? $value->value : $value), $default));
    }

    public function cast(mixed $value): mixed
    {
        $values = is_array($value) ? array_values($value) : [];

        if ($this->enum === null) {
            return $values;
        }

        return array_values(array_filter(array_map(fn (mixed $item) => $this->enum::tryFrom((string) $item), $values)));
    }
}
