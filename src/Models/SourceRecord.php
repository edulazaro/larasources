<?php

namespace EduLazaro\Larasources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use EduLazaro\Larasources\Source;
use EduLazaro\Larasources\Enums\OriginStatus;

class SourceRecord extends Model
{
    protected $table = 'sources';

    protected $fillable = [
        'sourceable_type', 'sourceable_id', 'name', 'variant', 'signature', 'arguments', 'attributes', 'origin', 'status',
    ];

    protected $casts = [
        'attributes' => 'array',
        'arguments' => 'array',
        'status' => OriginStatus::class,
    ];

    public function sourceable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Records the origin had not finished with, which `reconcile()` resolves.
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', OriginStatus::Processing);
    }

    public function sourceArguments()
    {
        // The foreign key is `source_id`, not the `source_record_id` Eloquent
        // would infer from the model name.
        return $this->hasMany(SourceArgument::class, 'source_id');
    }

    public function getArgumentsAttribute(): array
    {
        $arguments = $this->getRawOriginal('arguments') ?? [];
        
        if (is_string($arguments)) {
            $arguments = json_decode($arguments, true) ?? [];
        }
    
        foreach ($this->sourceArguments as $argument) {
            $value = $argument->argumentable;

            if ($value instanceof SourceRecord) {
                $arguments[$argument->name] = $value->attributes ?? [];
            } else {
                $arguments[$argument->name] = $value;
            }
        }
    
        return $arguments;
    }
    
    /**
     * Turn this row back into its source.
     *
     * The variant and the row itself travel with it, or the source would look
     * like a different one: `persist()` keys on the variant, so a source built
     * without it writes a second row instead of updating this one.
     */
    public function toSource(string $sourceClass): Source
    {
        return (new $sourceClass($this->getAttribute('attributes') ?? []))
            ->setVariant($this->variant)
            ->setSourceable($this->sourceable)
            ->setRecord($this);
    }
}
