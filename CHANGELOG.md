# Changelog

All notable changes to `laravel-export-engine` are in this file.

## Unreleased

- Each Excel writer builds its file in its own temporary folder. The engine deletes the folder when the export fails, when a queued job times out and when PHP stops with a fatal error. Before, a failed export left its OpenSpout folder in the temporary directory of the system.
- A failed export no longer finishes its partial file, and deletes it.
- New option `spreadsheets.temp_directory`.
- `SimpleExporter` opens its writer the same way as `SpreadsheetExporter`, so its Excel files get the creator and shared strings.

## 1.0.0 - 2026-10-26

- First release.
