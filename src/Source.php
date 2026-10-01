<?php

namespace EduLazaro\Larasources;

use ArrayAccess;
use JsonSerializable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use EduLazaro\Larasources\Models\SourceRecord;
use EduLazaro\Larasources\Enums\OriginStatus;
use EduLazaro\Larasources\Exceptions\MassAssignmentException;
use EduLazaro\Larasources\Exceptions\OriginException;
use ReflectionClass;
use RuntimeException;
use EduLazaro\Larasources\Attributes\UsesOrigin;
use Illuminate\Database\Eloquent\Relations\Relation;

abstract class Source implements ArrayAccess, Arrayable, Jsonable, JsonSerializable
{
    protected bool $fetched = false;

    /**
     * Whether the stored record has been looked up for this instance.
     */
    protected bool $recordResolved = false;


    /**
     * Link to the parent Laravel model (e.g. Article, Property, etc.).
     */
    protected $sourceable;

    /**
     * Optional argument mapping (lowest precedence).
     */
    protected array $arguments = [];

    /**
     * Variant arguments array 
     */
    protected array $variantArguments = [];

    protected ?string $variant = null;


    /**
     * Link to the SourceRecord in DB, if loaded from DB.
     */
    protected ?SourceRecord $record = null;


    /**
     * The model's attributes.
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * The attributes that should be visible in arrays.
     *
     * @var array
     */
    protected $visible = [];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [];

    protected array $relations = [];

    protected $origin;

    /**
     * Indicates whether attributes are snake cased on arrays.
     *
     * @var bool
     */
    public static $snakeAttributes = true;

    /**
     * Indicates if all mass assignment is enabled.
     *
     * @var bool
     */
    protected static $unguarded = false;

    /**
     * The cache of the mutated attributes for each class.
     *
     * @var array
     */
    protected static $mutatorCache = [];

   /**
     * Create a new Eloquent model instance.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    protected function arguments(): array
    {
        return [];
    }

    public function build(): void
    {
        //
    }

    public function setVariantArguments(?array $variantArguments): static
    {
        if ($variantArguments !== null) {
            $this->variantArguments = $variantArguments;
        }
    
        return $this;
    }

    public function setVariant(?string $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    /**
     * This source's name: the key it is mapped under on the model, falling back
     * to the class basename. It identifies the cached record and the config
     * block, so changing it orphans existing records.
     */
    public function name(): string
    {
        $sources = $this->sourceable && method_exists($this->sourceable, 'getSources')
            ? $this->sourceable->getSources()
            : [];

        return array_search(static::class, $sources) ?: class_basename(static::class);
    }

    /**
     * This source's settings.
     *
     * Same idea as the origin's `config()`, under `larasources.sources.{name}`:
     * the caller only names the key. Override it when the settings are not
     * static, or to serve them from the origin's integration.
     */
    public function config(?string $key = null, mixed $default = null): mixed
    {
        $configKey = 'larasources.sources.' . $this->name();

        if ($key) {
            $configKey .= '.' . $key;
        }

        return Config::get($configKey, $default);
    }

    /**
     * Resolve the argument map into actual values.
     *
     * The map declares which attribute of the sourceable feeds each argument,
     * as in `['city_id' => 'external_id']`, and the resolved array carries the
     * value of `$city->external_id`. Precedence, from lowest to highest: the
     * `$arguments` property, the `arguments()` method, the stored record and
     * the runtime arguments passed to `source()`.
     */
    public function resolveArguments(): array
    {
        $argumentMap = array_merge($this->arguments, $this->arguments());

        $resolvedArguments = [];

        foreach ($argumentMap as $argumentName => $sourceableAttributeName) {
            $resolvedArguments[$argumentName] = $this->sourceable
                ? data_get($this->sourceable, $sourceableAttributeName)
                : null;
        }

        if ($record = $this->record()) {
            $resolvedArguments = array_merge($resolvedArguments, $record->arguments);
        }

        return empty($this->variantArguments) ? $resolvedArguments : array_merge($resolvedArguments, $this->variantArguments);
    }
    
    /**
     * Override the origin manually.
     */
    public function setOrigin(object|string $origin): static
    {
        $this->origin = is_string($origin)
            ? new $origin($this)
            : $origin;

        return $this;
    }

    /**
     * Set the parent model this source is attached to.
     */
    public function setSourceable($sourceable): static
    {
        $this->sourceable = $sourceable;
        return $this;
    }

    /**
     * Get the parent model.
     */
    public function getSourceable()
    {
        return $this->sourceable;
    }

    /**
     * Attach a SourceRecord to this instance.
     */
    public function setRecord(SourceRecord $record): static
    {
        $this->record = $record;
        $this->recordResolved = true;

        // Only adopt the record's model when the source has none: replacing it
        // would swap the caller's instance for a freshly loaded one, and load
        // the morph relation for nothing.
        $this->sourceable ??= $record->sourceable;

        return $this;
    }

    /**
     * The stored record of this source, if there is one.
     *
     * Looked up in the table the first time it is asked for, so it answers
     * "is this stored?" on a source that has not read anything yet. It never
     * calls the origin.
     */
    public function record(): ?SourceRecord
    {
        return $this->findRecord();
    }

    /**
     * Alias of record().
     */
    /**
     * Alias of record().
     */
    public function getRecord(): ?SourceRecord
    {
        return $this->record();
    }


    /**
     * Resolve the Source instance from DB or origin.
     */
    public static function resolveFromModel(object $model, string $name): static
    {
        $sources = method_exists($model, 'getSources') ? $model->getSources() : [];
        $sourceClass = $sources[$name] ?? $name;

        $record = SourceRecord::where('sourceable_type', $model->getMorphClass())
            ->where('sourceable_id', $model->getKey())
            ->where('name', $name)
            ->first();

        if ($record) {
            return (new $sourceClass($record->attributes))->setRecord($record);
        }

        // fallback to origin
        $source = new $sourceClass();
        $source->setSourceable($model);
        return $source->fetch();
    }

    /**
     * Build this source, write it to the origin and persist the record.
     *
     * The record is written only once the origin has returned, so a write that
     * fails leaves the stored data exactly as it was. Failures arrive as
     * exceptions, which is what a queued job wants: it catches, backs off and
     * retries. Use `trySave()` when the caller would rather branch than catch.
     */
    public function save(): static
    {
        $this->build();

        $result = $this->pushToOrigin();

        if ($result->failed()) {
            $exception = $result->exception;

            if ($exception instanceof OriginException) {
                throw $exception;
            }

            throw new OriginException($result->message ?? 'The origin did not save ' . static::class . '.');
        }

        return $this->persist($result->status);
    }

    /**
     * Save without throwing, for callers that render instead of retrying.
     *
     * Nothing is persisted unless the origin took the data, and the result
     * carries the reason when it did not, which a boolean could not.
     */
    public function trySave(): OriginResult
    {
        $this->build();

        try {
            $result = $this->pushToOrigin();
        } catch (OriginException $e) {
            return new OriginResult(
                status: OriginStatus::Failed,
                message: $e->getMessage(),
                exception: $e,
            );
        }

        if ($result->ok()) {
            $this->persist($result->status);
        }

        return $result;
    }

    /**
     * Write to the origin and normalise what it reports back.
     *
     * An origin that returns its response array is saying `Saved`. Deciding
     * whether a foreign service accepted the data is the origin's job: it is
     * the only side that understands that API, so the package never reads the
     * response to find out.
     */
    protected function pushToOrigin(): OriginResult
    {
        $returned = $this->origin()->save($this->toArray());

        return $returned instanceof OriginResult
            ? $returned
            : new OriginResult(status: OriginStatus::Saved, data: $returned);
    }

    /**
     * Write this source's record: one row per model, source and variant.
     *
     * `$status` is what the origin last said about this data. It defaults to
     * `Saved` because the other caller is `fetch()`, and data read from the
     * origin is data the origin has.
     */
    public function persist(?OriginStatus $status = null): static
    {
        $sourceable = $this->getSourceable();

        if (!$sourceable || !method_exists($sourceable, 'getKey')) {
            return $this;
        }

        $name = $this->name();

        $record = SourceRecord::updateOrCreate(
            [
                'sourceable_type' => $sourceable->getMorphClass(),
                'sourceable_id' => $sourceable->getKey(),
                'name' => $name,
                'variant' => $this->variant,
            ],
            [
                'signature' => md5(json_encode($this->toArray())),
                'origin' => $this->origin()::getAlias(),
                'attributes' => $this->toArray(),
                'status' => ($status ?? OriginStatus::Saved)->value,
            ]
        );

        return $this->setRecord($record);
    }

    /**
     * Delete this resource at the origin and drop the local record.
     *
     * Failure is an exception, as everywhere else: it propagates and the record
     * stays. An origin that instead returns a literal `false` is saying it did
     * not delete anything, and the record stays for that too. Clearing it then
     * would leave the database claiming the resource is gone while the service
     * still has it, which is the one direction that must never happen.
     */
    public function delete(): bool
    {
        if (method_exists($this->origin(), 'delete') && $this->origin()->delete() === false) {
            return false;
        }

        return $this->clear();
    }
    
    public function clear(): bool
    {
        $record = $this->record();

        if (!$record) {
            return false;
        }

        $deleted = (bool) $record->delete();

        // The row is gone: a later record() must not hand back the stale object
        // it was just asked to delete, nor go looking for it again.
        $this->record = null;
        $this->recordResolved = true;

        return $deleted;
    }

    /**
     * Fetch fresh data from the origin and write it through to the cache.
     *
     * Reading from the origin always refreshes the `sources` record: the cache
     * is the local picture of the origin, so leaving it behind after a live
     * read would be lying. When the source is not attached to a model there is
     * nothing to persist and `persist()` returns early.
     */
    public function fetch(): static
    {
        $data = $this->origin()->fetch($this->resolveArguments());
        $this->fetched = true;

        $this->fill($data);

        return $this->persist();
    }

    /**
     * Re-read from the origin when the stored record is still `processing`.
     *
     * The origin had not finished with the data when the record was written,
     * so what is stored is what we sent. This is the only thing that turns it
     * into `saved`, and it converges on whatever the service really ended up
     * with, even if it rejected the payload in the end.
     *
     * Opt in, and chainable: nothing in this package calls an origin while you
     * read. Put it where the latency is yours to spend, which is a scheduled
     * command and not a page render, over `SourceRecord::processing()`.
     *
     * A record that is not processing, or no record at all, is left alone. A
     * failure is the caller's to handle: looping over many of these, you decide
     * whether one service being down stops the rest.
     */
    public function reconcile(): static
    {
        $record = $this->findRecord();

        if (!$record) {
            return $this;
        }

        $this->setRecord($record);

        if ($record->status !== OriginStatus::Processing) {
            return $this;
        }

        return $this->fetch();
    }

    /**
     * The stored record for this source, looked up once per instance.
     *
     * A fresh source has nothing loaded, so answering "is this stored?" means
     * going to the table. Never to the origin: one query, and the miss is
     * remembered so a loop does not repeat it.
     */
    protected function findRecord(): ?SourceRecord
    {
        if ($this->recordResolved) {
            return $this->record;
        }

        $this->recordResolved = true;

        $sourceable = $this->getSourceable();

        if (!$sourceable || !method_exists($sourceable, 'getKey')) {
            return null;
        }

        return $this->record = SourceRecord::where('sourceable_type', $sourceable->getMorphClass())
            ->where('sourceable_id', $sourceable->getKey())
            ->where('name', $this->name())
            ->where('variant', $this->variant)
            ->first();
    }

    /**
     * Get the resolved origin instance, using the stored origin or fallback.
     */
    public function origin()
    {
        if (! $this->origin) {
            $ref = new ReflectionClass(static::class);
    
            $attr = $ref->getAttributes(UsesOrigin::class)[0] ?? null;
    
            if (! $attr) {
                throw new RuntimeException("Missing #[UsesOrigin] on " . static::class);
            }
    
            /** @var UsesOrigin $instance */
            $instance = $attr->newInstance();
    
            $this->origin = new $instance->originClass($this);
        }
    
        return $this->origin;
    }

    /**
     * Regenerate this source's content (e.g. AI prompt).
     */
    public function regenerate(): static
    {
        $data = $this->origin()->regenerate();
        return new static($data);
    }


    /**
     * Dynamic casting support for collections.
     */
    protected function castAttribute($key, $value)
    {
        $cast = $this->casts[$key] ?? null;

        if ($cast === 'collection') {
            return collect($value);
        }

        if (Str::startsWith($cast, 'collection:')) {
            $class = Str::after($cast, 'collection:');
            return collect($value)->map(fn ($item) => new $class($item));
        }

        if (is_null($value)) {
            return $value;
        }

        switch ($this->getCastType($key)) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'real':
            case 'float':
            case 'double':
                return (float) $value;
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'object':
                return $this->fromJson($value, true);
            case 'array':
            case 'json':
                return $this->fromJson($value);
            case 'collection':
                return new Collection($this->fromJson($value));
            default:
                return $value;
        }
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param  array  $attributes
     * @return $this
     *
     * @throws \EduLazaro\Larasources\Exceptions\MassAssignmentException
     */
    public function fill(array $attributes)
    {
        $totallyGuarded = $this->totallyGuarded();

        foreach ($this->fillableFromArray($attributes) as $key => $value) {
            // The developers may choose to place some attributes in the "fillable"
            // array, which means only those attributes may be set through mass
            // assignment to the model, and all others will just be ignored.
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            } elseif ($totallyGuarded) {
                throw new MassAssignmentException($key);
            }
        }

        return $this;
    }

    /**
     * Fill the model with an array of attributes. Force mass assignment.
     *
     * @param  array  $attributes
     * @return $this
     */
    public function forceFill(array $attributes)
    {
        // Since some versions of PHP have a bug that prevents it from properly
        // binding the late static context in a closure, we will first store
        // the model in a variable, which we will then use in the closure.
        $model = $this;

        return static::unguarded(function () use ($model, $attributes) {
            return $model->fill($attributes);
        });
    }

    /**
     * Get the fillable attributes of a given array.
     *
     * @param  array  $attributes
     * @return array
     */
    protected function fillableFromArray(array $attributes)
    {
        if (count($this->fillable) > 0 && ! static::$unguarded) {
            return array_intersect_key($attributes, array_flip($this->fillable));
        }

        return $attributes;
    }

    /**
     * Create a new instance of the given model.
     *
     * @param  array  $attributes
     * @param  bool   $exists
     * @return static
     */
    public function newInstance($attributes = [])
    {
        $model = new static((array) $attributes);

        return $model;
    }

    /**
     * Create a collection of models from plain arrays.
     *
     * @param  array  $items
     * @return array
     */
    public static function hydrate(array $items)
    {
        $instance = new static;

        $items = array_map(function ($item) use ($instance) {
            return $instance->newInstance($item);
        }, $items);

        return $items;
    }

    /**
     * Get the hidden attributes for the model.
     *
     * @return array
     */
    public function getHidden()
    {
        return $this->hidden;
    }

    /**
     * Set the hidden attributes for the model.
     *
     * @param  array  $hidden
     * @return $this
     */
    public function setHidden(array $hidden)
    {
        $this->hidden = $hidden;

        return $this;
    }

    /**
     * Add hidden attributes for the model.
     *
     * @param  array|string|null  $attributes
     * @return void
     */
    public function addHidden($attributes = null)
    {
        $attributes = is_array($attributes) ? $attributes : func_get_args();

        $this->hidden = array_merge($this->hidden, $attributes);
    }

    /**
     * Make the given, typically hidden, attributes visible.
     *
     * @param  array|string  $attributes
     * @return $this
     */
    public function withHidden($attributes)
    {
        $this->hidden = array_diff($this->hidden, (array) $attributes);

        return $this;
    }

    /**
     * Get the visible attributes for the model.
     *
     * @return array
     */
    public function getVisible()
    {
        return $this->visible;
    }

    /**
     * Set the visible attributes for the model.
     *
     * @param  array  $visible
     * @return $this
     */
    public function setVisible(array $visible)
    {
        $this->visible = $visible;

        return $this;
    }

    /**
     * Add visible attributes for the model.
     *
     * @param  array|string|null  $attributes
     * @return void
     */
    public function addVisible($attributes = null)
    {
        $attributes = is_array($attributes) ? $attributes : func_get_args();

        $this->visible = array_merge($this->visible, $attributes);
    }

    /**
     * Set the accessors to append to model arrays.
     *
     * @param  array  $appends
     * @return $this
     */
    public function setAppends(array $appends)
    {
        $this->appends = $appends;

        return $this;
    }

    /**
     * Get the fillable attributes for the model.
     *
     * @return array
     */
    public function getFillable()
    {
        return $this->fillable;
    }

    /**
     * Set the fillable attributes for the model.
     *
     * @param  array  $fillable
     * @return $this
     */
    public function fillable(array $fillable)
    {
        $this->fillable = $fillable;

        return $this;
    }

    /**
     * Get the guarded attributes for the model.
     *
     * @return array
     */
    public function getGuarded()
    {
        return $this->guarded;
    }

    /**
     * Set the guarded attributes for the model.
     *
     * @param  array  $guarded
     * @return $this
     */
    public function guard(array $guarded)
    {
        $this->guarded = $guarded;

        return $this;
    }

    /**
     * Disable all mass assignable restrictions.
     *
     * @param  bool  $state
     * @return void
     */
    public static function unguard($state = true)
    {
        static::$unguarded = $state;
    }

    /**
     * Enable the mass assignment restrictions.
     *
     * @return void
     */
    public static function reguard()
    {
        static::$unguarded = false;
    }

    /**
     * Determine if current state is "unguarded".
     *
     * @return bool
     */
    public static function isUnguarded()
    {
        return static::$unguarded;
    }

    /**
     * Run the given callable while being unguarded.
     *
     * @param  callable  $callback
     * @return mixed
     */
    public static function unguarded(callable $callback)
    {
        if (static::$unguarded) {
            return $callback();
        }

        static::unguard();

        $result = $callback();

        static::reguard();

        return $result;
    }

    /**
     * Determine if the given attribute may be mass assigned.
     *
     * @param  string  $key
     * @return bool
     */
    public function isFillable($key)
    {
        if (static::$unguarded) {
            return true;
        }

        // If the key is in the "fillable" array, we can of course assume that it's
        // a fillable attribute. Otherwise, we will check the guarded array when
        // we need to determine if the attribute is black-listed on the model.
        if (in_array($key, $this->fillable)) {
            return true;
        }

        if ($this->isGuarded($key)) {
            return false;
        }

        return empty($this->fillable);
    }

    /**
     * Determine if the given key is guarded.
     *
     * @param  string  $key
     * @return bool
     */
    public function isGuarded($key): bool
    {
        return in_array($key, $this->guarded) || $this->guarded == ['*'];
    }

    /**
     * Determine if the model is totally guarded.
     *
     * @return bool
     */
    public function totallyGuarded(): bool
    {
        return count($this->fillable) == 0 && $this->guarded == ['*'];
    }

    /**
     * Convert the model instance to JSON.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Convert the model instance to an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->attributesToArray();
    }

    /**
     * Convert the model's attributes to an array.
     *
     * @return array
     */
    public function attributesToArray()
    {
        $attributes = $this->getArrayableAttributes();

        $mutatedAttributes = $this->getMutatedAttributes();

        // We want to spin through all the mutated attributes for this model and call
        // the mutator for the attribute. We cache off every mutated attributes so
        // we don't have to constantly check on attributes that actually change.
        foreach ($mutatedAttributes as $key) {
            if (! array_key_exists($key, $attributes)) {
                continue;
            }

            $attributes[$key] = $this->mutateAttributeForArray(
                $key, $attributes[$key]
            );
        }

        // Next we will handle any casts that have been setup for this model and cast
        // the values to their appropriate type. If the attribute has a mutator we
        // will not perform the cast on those attributes to avoid any confusion.
        foreach ($this->casts as $key => $value) {
            if (! array_key_exists($key, $attributes) ||
                in_array($key, $mutatedAttributes)) {
                continue;
            }

            $attributes[$key] = $this->castAttribute(
                $key, $attributes[$key]
            );
        }

        // Here we will grab all of the appended, calculated attributes to this model
        // as these attributes are not really in the attributes array, but are run
        // when we need to array or JSON the model for convenience to the coder.
        foreach ($this->getArrayableAppends() as $key) {
            $attributes[$key] = $this->mutateAttributeForArray($key, null);
        }

        return $attributes;
    }

    /**
     * Get an attribute array of all arrayable attributes.
     *
     * @return array
     */
    protected function getArrayableAttributes()
    {
        return $this->getArrayableItems($this->attributes);
    }

    /**
     * Get all of the appendable values that are arrayable.
     *
     * @return array
     */
    protected function getArrayableAppends()
    {
        if (! count($this->appends)) {
            return [];
        }

        return $this->getArrayableItems(
            array_combine($this->appends, $this->appends)
        );
    }

    /**
     * Get an attribute array of all arrayable values.
     *
     * @param  array  $values
     * @return array
     */
    protected function getArrayableItems(array $values)
    {
        if (count($this->getVisible()) > 0) {
            return array_intersect_key($values, array_flip($this->getVisible()));
        }

        return array_diff_key($values, array_flip($this->getHidden()));
    }

    /**
     * Get an attribute from the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        return $this->getAttributeValue($key);
    }

    /**
     * Get a plain attribute (not a relationship).
     *
     * @param  string  $key
     * @return mixed
     */
    protected function getAttributeValue($key)
    {
        $value = $this->getAttributeFromArray($key);

        if ($this->hasGetMutator($key)) {
            return $this->mutateAttribute($key, $value);
        }

        if ($this->hasCast($key)) {
            $value = $this->castAttribute($key, $value);
        }

        return $value;
    }

    /**
     * Get an attribute from the $attributes array.
     *
     * @param  string  $key
     * @return mixed
     */
    protected function getAttributeFromArray($key)
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }
    }

    /**
     * Determine if a get mutator exists for an attribute.
     *
     * @param  string  $key
     * @return bool
     */
    public function hasGetMutator($key)
    {
        return method_exists($this, 'get'.Str::studly($key).'Attribute');
    }

    /**
     * Get the value of an attribute using its mutator.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return mixed
     */
    protected function mutateAttribute($key, $value)
    {
        return $this->{'get'.Str::studly($key).'Attribute'}($value);
    }

    /**
     * Get the value of an attribute using its mutator for array conversion.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return mixed
     */
    protected function mutateAttributeForArray($key, $value)
    {
        $value = $this->mutateAttribute($key, $value);

        return $value instanceof Arrayable ? $value->toArray() : $value;
    }

    /**
     * Determine whether an attribute should be casted to a native type.
     *
     * @param  string  $key
     * @return bool
     */
    protected function hasCast($key)
    {
        return array_key_exists($key, $this->casts);
    }

    /**
     * Determine whether a value is JSON castable for inbound manipulation.
     *
     * @param  string  $key
     * @return bool
     */
    protected function isJsonCastable($key)
    {
        $castables = ['array', 'json', 'object', 'collection'];
        return $this->hasCast($key) &&
            in_array($this->getCastType($key), $castables, true);
    }

    /**
     * Get the type of cast for a model attribute.
     *
     * @param  string  $key
     * @return string
     */
    protected function getCastType($key)
    {
        return trim(strtolower($this->casts[$key]));
    }

    /**
     * Set a given attribute on the model.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return $this
     */
    public function setAttribute($key, $value)
    {
        // First we will check for the presence of a mutator for the set operation
        // which simply lets the developers tweak the attribute as it is set on
        // the model, such as "json_encoding" an listing of data for storage.
        if ($this->hasSetMutator($key)) {
            $method = 'set'.Str::studly($key).'Attribute';

            return $this->{$method}($value);
        }

        if ($this->isJsonCastable($key) && ! is_null($value)) {
            $value = $this->asJson($value);
        }

        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Determine if a set mutator exists for an attribute.
     *
     * @param  string  $key
     * @return bool
     */
    public function hasSetMutator($key)
    {
        return method_exists($this, 'set'.Str::studly($key).'Attribute');
    }

    /**
     * Encode the given value as JSON.
     *
     * @param  mixed  $value
     * @return string
     */
    protected function asJson($value)
    {
        return json_encode($value);
    }

    /**
     * Decode the given JSON back into an array or object.
     *
     * @param  string  $value
     * @param  bool  $asObject
     * @return mixed
     */
    public function fromJson($value, $asObject = false)
    {
        return json_decode($value, ! $asObject);
    }

    /**
     * Get all of the current attributes on the model.
     *
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Get the mutated attributes for a given instance.
     *
     * @return array
     */
    public function getMutatedAttributes()
    {
        $class = get_class($this);

        if (! isset(static::$mutatorCache[$class])) {
            static::cacheMutatedAttributes($class);
        }

        return static::$mutatorCache[$class];
    }

    /**
     * Extract and cache all the mutated attributes of a class.
     *
     * @param string $class
     * @return void
     */
    public static function cacheMutatedAttributes($class)
    {
        $mutatedAttributes = [];

        // Here we will extract all of the mutated attributes so that we can quickly
        // spin through them after we export models to their array form, which we
        // need to be fast. This'll let us know the attributes that can mutate.
        if (preg_match_all('/(?<=^|;)get([^;]+?)Attribute(;|$)/', implode(';', get_class_methods($class)), $matches)) {
            foreach ($matches[1] as $match) {
                if (static::$snakeAttributes) {
                    $match = Str::snake($match);
                }

                $mutatedAttributes[] = lcfirst($match);
            }
        }

        static::$mutatorCache[$class] = $mutatedAttributes;
    }

    /**
     * Dynamically retrieve attributes on the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->getAttribute($key);
        }

        if (! $this->fetched) {
            $this->autoload();
            return $this->getAttribute($key);
        }

        return null;
    }

    /**
     * Autoload source data: first from DB, then from origin.
     */
    protected function autoload(): void
    {
        $this->fetched = true;

        if ($this->sourceable && method_exists($this->sourceable, 'getKey')) {
            $record = $this->findRecord();

            if ($record) {
                $this->fill($record->attributes ?? []);

                return;
            }
        }

        try {
            $this->fetch();
        } catch (\Throwable $e) {
            $this->build();
            if (!empty($this->attributes)) {
                $this->persist();
            }
        }
    }

    /**
     * Dynamically set attributes on the model.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function __set($key, $value)
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Determine if the given attribute exists.
     *
     * @param  mixed  $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->$offset);
    }

    /**
     * Get the value for a given offset.
     *
     * @param  mixed  $offset
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->$offset;
    }

    /**
     * Set the value for a given offset.
     *
     * @param  mixed  $offset
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->$offset = $value;
    }

    /**
     * Unset the value for a given offset.
     *
     * @param  mixed  $offset
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->$offset);
    }

    /**
     * Determine if an attribute exists on the model.
     *
     * @param  string  $key
     * @return bool
     */
    public function __isset($key)
    {
        return (isset($this->attributes[$key]) || isset($this->relations[$key])) ||
            ($this->hasGetMutator($key) && ! is_null($this->getAttributeValue($key)));
    }

    /**
     * Unset an attribute on the model.
     *
     * @param  string  $key
     * @return void
     */
    public function __unset($key)
    {
        unset($this->attributes[$key]);
    }

    /**
     * Handle dynamic static method calls into the method.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        $instance = new static;

        return call_user_func_array([$instance, $method], $parameters);
    }

    /**
     * Convert the model to its string representation.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }
}
