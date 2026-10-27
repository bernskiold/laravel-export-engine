<?php

namespace Bernskiold\ExportEngine\Settings;

/**
 * Turn something on or off.
 */
class ToggleSetting extends ExportSetting
{
    protected mixed $default = false;

    public function rules(ExportSettingsContext $context): array
    {
        return ['boolean'];
    }

    public function type(): string
    {
        return 'toggle';
    }

    public function defaultValue(ExportSettingsContext $context): mixed
    {
        return (bool) parent::defaultValue($context);
    }

    public function cast(mixed $value): mixed
    {
        return (bool) $value;
    }
}
