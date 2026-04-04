<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

abstract class BriefPlanEvent
{
    public function __construct(
        public readonly Model $subject,       // Brief hoặc Plan instance
        public readonly User  $causer,        // user thực hiện action
        public readonly array $context = [],  // extra data: comment, version, ...
    ) {}
}
