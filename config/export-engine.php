<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Model
    |--------------------------------------------------------------------------
    |
    | The model that keeps the record of each export. To add relations,
    | scopes or columns of your own, extend the package model and set your
    | class here.
    |
    */
    'model' => Bernskiold\ExportEngine\Models\Export::class,

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    |
    | The connection and the table of the export records. A null connection
    | uses the default connection of the application.
    |
    */
    'connection' => env('EXPORT_ENGINE_DB_CONNECTION'),

    'table' => 'exports',

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The model of the person who asks for an export. A null value uses the
    | model of the "users" auth provider.
    |
    */
    'user_model' => null,

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | An exporter always writes its file to a local "staging" disk first,
    | because the spreadsheet and presentation writers need a real path.
    | When the file is complete, the engine moves it to the "disk", where it
    | stays until the export expires. The disk can be local or remote, for
    | example S3. The staging disk must use the local driver.
    |
    */
    'disk' => env('EXPORT_ENGINE_DISK', 'local'),

    'staging' => [
        'disk' => env('EXPORT_ENGINE_STAGING_DISK', 'local'),
        'directory' => 'export-engine',
    ],

    /*
    |--------------------------------------------------------------------------
    | Lifetime
    |--------------------------------------------------------------------------
    |
    | "expire_after_hours" is how long a complete file stays available. The
    | sweep deletes the file after this time, but it keeps the record.
    |
    | "abandoned_after_minutes" is how long an export can stay pending before
    | the sweep marks it as failed. This happens when a worker stops during
    | an export. Make this longer than your longest export.
    |
    | "prune_after_days" is how long the records stay. Use null to keep them
    | always. To prune, schedule `model:prune` with the model above.
    |
    | "staging_files_after_hours" is how old a file on the staging disk must
    | be before the sweep deletes it. Only a stopped export leaves one there.
    |
    */
    'expire_after_hours' => (int) env('EXPORT_ENGINE_EXPIRE_AFTER_HOURS', 168),

    'abandoned_after_minutes' => (int) env('EXPORT_ENGINE_ABANDONED_AFTER_MINUTES', 1440),

    'prune_after_days' => env('EXPORT_ENGINE_PRUNE_AFTER_DAYS'),

    'staging_files_after_hours' => 24,

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Where queued exports run. A null connection or queue uses the default
    | of the application. "tries", "timeout" and "backoff" go to the job.
    | Null values use the settings of the worker.
    |
    */
    'queue' => [
        'connection' => env('EXPORT_ENGINE_QUEUE_CONNECTION'),
        'queue' => env('EXPORT_ENGINE_QUEUE'),
        'tries' => null,
        'timeout' => null,
        'backoff' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Classes that wrap each export while it runs. Each class has a
    | `handle(Export $export, Closure $next)` method. Use them to set the
    | locale of the export, or to record who the work is for. You can also
    | add middleware with `ExportEngine::middleware()`.
    |
    */
    'middleware' => [],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | The notifications to send to the person who asked for a queued export.
    | Each class gets the export in its constructor. Use null to send no
    | notification. An export that runs in the request sends nothing, because
    | the person gets the file at once.
    |
    */
    'notifications' => [
        'finished' => null,
        'failed' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Policy
    |--------------------------------------------------------------------------
    |
    | The policy for the model above. The default lets only the owner of an
    | export see and download it. Use null to register your own policy.
    |
    */
    'policy' => Bernskiold\ExportEngine\Policies\ExportPolicy::class,

    /*
    |--------------------------------------------------------------------------
    | Spreadsheets
    |--------------------------------------------------------------------------
    |
    | "creator" goes into the properties of each workbook.
    |
    */
    'spreadsheets' => [
        'creator' => env('EXPORT_ENGINE_SPREADSHEET_CREATOR', env('APP_NAME', 'Laravel')),
    ],

    /*
    |--------------------------------------------------------------------------
    | File Names
    |--------------------------------------------------------------------------
    |
    | Some callers give a slug, for example "brand-overview", as the name of
    | an export. The engine changes the slug into words. Words in this list
    | stay in capitals, so "cx-report" becomes "CX Report".
    |
    */
    'file_names' => [
        'acronyms' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pending Exports
    |--------------------------------------------------------------------------
    |
    | When an exporter has settings, the engine holds the export in the
    | cache while the person selects them. This is how long it stays there.
    |
    */
    'pending' => [
        'cache_store' => null,
        'minutes' => 60,
    ],

];
