<?php

namespace EduLazaro\Larasources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SourceArgument extends Model
{
    protected $table = 'source_arguments';

    protected $fillable = [
        'name',
        'source_id',
        'argumentable_type',
        'argumentable_id',
    ];

    protected $casts = [
        'value' => 'array', // importante si vas a guardar estructuras o referencias
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(SourceRecord::class);
    }

    public function argument(): MorphTo
    {
        return $this->morphTo('argumentable');
    }
}
