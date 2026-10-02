<?php

namespace EduLazaro\Larasources\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use EduLazaro\Larasources\Source;
use EduLazaro\Larasources\Enums\OriginStatus;

class SourceRecord extends Model
{
    protected $table = 'sources';

    protected $fillable = [
        'sourceable_type', 'sourceable_id', 'name', 'variant', 'signature', 'arguments', 'attributes', 'origin', 'status', 'external_id',
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

    /**
     * Rows that do not belong to a model yet, identified by their external id.
     */
    public function scopeUnattached($query)
    {
        return $query->whereNull('sourceable_id');
    }

    /**
     * Give this row the model it belongs to.
     *
     * A model holds one row per source and variant, so an existing one is a
     * conflict the caller has to resolve: either that row is the good one, or
     * it goes before this one takes its place. Silently merging them would
     * throw away whichever payload the caller cared less about, and only the
     * caller knows which that is.
     */
    public function attachTo(Model $model): static
    {
        $existing = static::query()
            ->where('sourceable_type', $model->getMorphClass())
            ->where('sourceable_id', $model->getKey())
            ->where('name', $this->name)
            ->where('variant', $this->variant)
            ->whereKeyNot($this->getKey())
            ->first();

        if ($existing) {
            throw new RuntimeException(sprintf(
                '%s [%s] already has a `%s` source%s, stored as row %s.',
                $model->getMorphClass(),
                $model->getKey(),
                $this->name,
                $this->variant ? " for variant `{$this->variant}`" : '',
                $existing->getKey(),
            ));
        }

        $this->sourceable()->associate($model);
        $this->save();

        return $this;
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
                // `getAttribute()` and not `->attributes`: Eloquent's attribute
                // bag is a protected property, and from inside this class PHP
                // reads it directly instead of going through the cast, which
                // hands over the whole row rather than the payload.
                $arguments[$argument->name] = $value->getAttribute('attributes') ?? [];
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
