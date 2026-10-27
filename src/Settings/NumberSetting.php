<?php

namespace Bernskiold\ExportEngine\Settings;

/**
 * A whole number between a minimum and a maximum, for example a width in
 * pixels.
 */
class NumberSetting extends ExportSetting
{
    protected int $min = 0;

    protected int $max = PHP_INT_MAX;

    protected ?string $suffix = null;

    public function between(int $min, int $max): static
    {
        $this->min = $min;
        $this->max = $max;

        return $this;
    }

    public function suffix(?string $suffix): static
    {
        $this->suffix = $suffix;

        return $this;
    }

    public function getMin(): int
    {
        return $this->min;
    }

    public function getMax(): int
    {
        return $this->max;
    }

    public function getSuffix(): ?string
    {
        return $this->suffix;
    }

    public function rules(ExportSettingsContext $context): array
    {
        return ['required', 'integer', "min:{$this->min}", "max:{$this->max}"];
    }

    public function type(): string
    {
        return 'number';
    }

    public function cast(mixed $value): mixed
    {
        return $value === null ? null : (int) $value;
    }
}
