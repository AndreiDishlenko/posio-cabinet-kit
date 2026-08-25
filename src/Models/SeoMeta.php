<?php

namespace Posio\CabinetKit\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SeoMeta extends Model
{
	use HasFactory;
	use SoftDeletes;

    protected $table = 'seo_meta';

    protected $guarded = [];
}
