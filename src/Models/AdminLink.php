<?php

namespace Posio\CabinetKit\Models;

use Illuminate\Database\Eloquent\Model;

class AdminLink extends Model
{
    protected $table = 'admin_links';

    protected $fillable = [
        'order_id',
        'name',
        'icon',
        'link',
        'route',
        'permissions',
        'is_header',
        'is_published',
    ];

    protected $casts = [
        'is_header' => 'bool',
        'is_published' => 'bool',
    ];
}
