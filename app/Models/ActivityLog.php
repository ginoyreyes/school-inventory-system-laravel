<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [
        'actor_id', 'actor_name', 'actor_username', 'actor_role',
        'action', 'entity_type', 'entity_id', 'entity_label',
        'details', 'ip', 'user_agent',
    ];
}