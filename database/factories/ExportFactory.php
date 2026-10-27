<?php

namespace Bernskiold\ExportEngine\Database\Factories;

use Bernskiold\ExportEngine\Enums\ExportStatus;
use Bernskiold\ExportEngine\ExportEngine;
use Bernskiold\ExportEngine\Models\Export;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @extends Factory<Export>
 */
class ExportFactory extends Factory
{
    /**
     * The model in the config, so the factory also makes your own model.
     */
    public function modelName(): string
    {
        return ExportEngine::model();
    }

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'download_name' => 'Export - '.now()->format('Y-m-d H-i').'.csv',
            'format' => 'csv',
            'status' => ExportStatus::Requested,
            'is_queued' => false,
            'filters' => [],
            'options' => [],
        ];
    }

    public function generating(): static
    {
        return $this->state(fn () => [
            'status' => ExportStatus::Generating,
            'started_at' => now(),
        ]);
    }

    /**
     * An available export, with a file on the export disk.
     */
    public function available(string $contents = 'contents'): static
    {
        return $this->state(fn () => [
            'status' => ExportStatus::Available,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'expires_at' => now()->addHours(Export::expireAfterHours()),
            'disk' => Export::defaultDisk(),
        ])->afterCreating(function (Export $export) use ($contents) {
            $file = "factory/{$export->uuid}.{$export->format}";

            Storage::disk($export->disk)->put($file, $contents);

            $export->forceFill(['file' => $file])->saveQuietly();
        });
    }

    public function failed(string $reason = 'The export could not be completed.'): static
    {
        return $this->state(fn () => [
            'status' => ExportStatus::Failed,
            'started_at' => now()->subMinute(),
            'failed_at' => now(),
            'failure_reason' => $reason,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => ExportStatus::Expired,
            'started_at' => now()->subDays(8),
            'finished_at' => now()->subDays(8),
            'expires_at' => now()->subDay(),
        ]);
    }

    public function queued(): static
    {
        return $this->state(fn () => ['is_queued' => true]);
    }
}
