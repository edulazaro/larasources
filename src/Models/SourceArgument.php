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

    public function source(): BelongsTo
    {
        return $this->belongsTo(SourceRecord::class);
    }

    public function argumentable(): MorphTo
    {
        return $this->morphTo('argumentable');
    }

    /**
     * @deprecated Use argumentable(), which is what `$argument->argumentable` resolves.
     */
    public function argument(): MorphTo
    {
        return $this->argumentable();
    }
}
