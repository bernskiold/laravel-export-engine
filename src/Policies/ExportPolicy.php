<?php

namespace Bernskiold\ExportEngine\Policies;

use Bernskiold\ExportEngine\Models\Export;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Only the person who asked for an export can see it and download it.
 */
class ExportPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return true;
    }

    public function view(Authenticatable $user, Export $export): bool
    {
        return (string) $export->user_id === (string) $user->getAuthIdentifier();
    }

    public function download(Authenticatable $user, Export $export): bool
    {
        return $this->view($user, $export);
    }
}
