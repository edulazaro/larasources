<?php

namespace EduLazaro\Larasources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use EduLazaro\Larasources\Source;

class SourceRecord extends Model
{
    protected $table = 'sources';

    protected $fillable = [
        'sourceable_type', 'sourceable_id', 'name', 'variant', 'signature', 'arguments', 'attributes', 'origin',
    ];

    protected $casts = [
        'attributes' => 'array',
        'arguments' => 'array',
    ];

    public function sourceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function sourceArguments()
    {
        return $this->hasMany(SourceArgument::class);
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
     * Returns the data as a source class instance.
     */
    public function toSource(string $sourceClass): Source
    {
        return (new $sourceClass($this->getAttribute('attributes') ?? []))->setSourceable($this->sourceable);
    }
}
