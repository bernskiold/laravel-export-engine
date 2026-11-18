# Changelog

All notable changes to `laravel-export-engine` are in this file.

## Unreleased

- Each Excel writer builds its file in its own temporary folder. The engine deletes the folder when the export fails, when a queued job times out and when PHP stops with a fatal error. Before, a failed export left its OpenSpout folder in the temporary directory of the system.
- A failed export no longer finishes its partial file, and deletes it.
- New option `spreadsheets.temp_directory`.
- New limits `spreadsheets.max_rows`, `spreadsheets.max_cells` and `spreadsheets.min_free_disk_mb`, all off by default. An export over a limit fails with `ExportTooLarge`, and the person sees why.
- New contract `EstimatesSize`. The engine asks the exporter for its rows before an Excel export starts, and refuses an export over the limits.
- New `Export::draft()`, which makes an export without saving it.
- `OpenSpout::createWriter()` returns a `GuardedWriter`, which extends `SimpleExcelWriter`.
- `SimpleExporter` opens its writer the same way as `SpreadsheetExporter`, so its Excel files get the creator and shared strings.

## 1.0.0 - 2026-10-26

- First release.
