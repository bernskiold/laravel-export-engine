<?php

namespace Bernskiold\ExportEngine\Settings;

use Bernskiold\ExportEngine\Contracts\HasExportSettings;
use Bernskiold\ExportEngine\ExportEngine;
use Bernskiold\ExportEngine\Models\Export;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Validator;

/**
 * The settings of an exporter, for one export.
 *
 * The form and the running export both use this class. The form gets the
 * settings to show and the rules to validate them. The export gets the
 * values. Thus the export uses the same defaults that the person saw.
 */
class ExportSettings
{
    /**
     * @param  array<string, ExportSetting>  $settings
     */
    protected function __construct(
        protected array $settings,
        public readonly ExportSettingsContext $context,
    ) {}

    public static function for(?string $exporter, ExportSettingsContext $context): self
    {
        $exporter = ExportEngine::exporterClass($exporter);

        if ($exporter === null || ! is_a($exporter, HasExportSettings::class, true)) {
            return new self([], $context);
        }

        return new self(
            collect($exporter::exportSettings($context))->keyBy('key')->all(),
            $context,
        );
    }

    public static function forExport(Export $export): self
    {
        return self::for($export->exporterClass(), ExportSettingsContext::fromExport($export));
    }

    /**
     * The settings to show for the format of this export.
     *
     * @return array<string, ExportSetting>
     */
    public function offered(): array
    {
        return array_filter(
            $this->settings,
            fn (ExportSetting $setting) => $setting->isOfferedFor($this->context->format),
        );
    }

    public function isEmpty(): bool
    {
        return $this->offered() === [];
    }

    /**
     * The values the form starts with. A setting gets the value the person
     * selected last time, if that value is still valid. Other settings get
     * their defaults.
     *
     * @param  array<string, mixed>  $remembered
     * @return array<string, mixed>
     */
    public function initialValues(array $remembered = []): array
    {
        $values = [];

        foreach ($this->offered() as $key => $setting) {
            $values[$key] = $setting->isRemembered() && array_key_exists($key, $remembered) && $this->accepts($setting, $remembered[$key])
                ? $remembered[$key]
                : $setting->defaultValue($this->context);
        }

        return $values;
    }

    /**
     * The settings to show for the current values, in groups for the form.
     * Settings with the same row go next to each other. Toggles that follow
     * each other go in one group, so that they show as a list.
     *
     * @param  array<string, mixed>  $values
     * @return array<int, array{layout: 'row'|'stack', settings: array<int, ExportSetting>}>
     */
    public function fieldGroups(array $values): array
    {
        $groups = [];

        foreach ($this->offered() as $setting) {
            if (! $setting->isShownFor($values)) {
                continue;
            }

            $last = array_key_last($groups);
            $previous = $last === null ? null : end($groups[$last]['settings']);

            $joinsRow = $setting->getRow() !== null && $previous?->getRow() === $setting->getRow();
            $joinsToggles = $setting instanceof ToggleSetting && $previous instanceof ToggleSetting;

            if ($joinsRow || $joinsToggles) {
                $groups[$last]['settings'][] = $setting;

                continue;
            }

            $groups[] = [
                'layout' => $setting->getRow() !== null ? 'row' : 'stack',
                'settings' => [$setting],
            ];
        }

        return $groups;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(string $prefix = 'settings'): array
    {
        return collect($this->offered())
            ->mapWithKeys(function (ExportSetting $setting, string $key) use ($prefix) {
                $rules = $setting->rules($this->context);

                if ($setting->shownWhen() !== null) {
                    [$otherKey, $value] = $setting->shownWhen();

                    $rules = ["exclude_unless:{$prefix}.{$otherKey},{$value}", ...$rules];
                }

                return ["{$prefix}.{$key}" => $rules];
            })
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(string $prefix = 'settings'): array
    {
        return collect($this->offered())
            ->mapWithKeys(fn (ExportSetting $setting, string $key) => ["{$prefix}.{$key}" => $setting->label])
            ->all();
    }

    /**
     * Only the values of the settings the form showed. The export keeps
     * these values.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function chosen(array $values): array
    {
        return collect($this->offered())
            ->filter(fn (ExportSetting $setting, string $key) => array_key_exists($key, $values) && $setting->isShownFor($values))
            ->map(fn (ExportSetting $setting, string $key) => $values[$key])
            ->all();
    }

    /**
     * The value of a setting for a running export. A setting that the form
     * did not show gets its default. This includes a hidden setting, a
     * setting for a different format, and an export started with no form.
     * A setting that the exporter does not have is null.
     *
     * @param  array<string, mixed>  $stored
     */
    public function value(string $key, array $stored = []): mixed
    {
        $setting = $this->settings[$key] ?? null;

        if ($setting === null) {
            return null;
        }

        $value = $setting->isOfferedFor($this->context->format) && array_key_exists($key, $stored) && $setting->isShownFor($stored)
            ? $stored[$key]
            : $setting->defaultValue($this->context);

        return $setting->cast($value);
    }

    /**
     * The values the person selected the last time they used this exporter.
     *
     * @return array<string, mixed>
     */
    public static function lastChosenBy(?Authenticatable $user, ?string $exporter): array
    {
        if ($user === null || $exporter === null) {
            return [];
        }

        return ExportEngine::model()::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('exporter', ExportEngine::exporterAlias($exporter))
            ->whereNotNull('settings')
            ->latest('id')
            ->value('settings') ?? [];
    }

    protected function accepts(ExportSetting $setting, mixed $value): bool
    {
        return Validator::make(['value' => $value], ['value' => $setting->rules($this->context)])->passes();
    }
}
