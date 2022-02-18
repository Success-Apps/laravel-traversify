<?php
namespace Traversify\Traits;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

trait HasAutoload
{
    public function scopeAutoload(Builder $query, Array $load = [])
    {
        if (!$autoloads = $this->autoload) {
            throw new Exception('No column configured to be autoloaded');
        }

        if (empty($load)) {
            return;
        }

        $clean = [];

        foreach($autoloads as $autoload) {

            if (in_array($autoload, array_values($load))) {

                $clean[] = $autoload;
            }
        }

        if (!empty($clean)) {
            $query->with($clean);
        }
    }
}
