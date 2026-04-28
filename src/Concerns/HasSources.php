<?php

namespace EduLazaro\Larasources\Concerns;

use Illuminate\Support\Facades\App;
use EduLazaro\Larasources\Source;
use InvalidArgumentException;

trait HasSources
{
    /**
     * @var array $sources Stores the sources mapping
     */
    protected array $sources = [];

    /**
     * @var array $mockedSources Stores mocked source instances for testing purposes.
     */
    protected array $mockedSources = [];

    /**
     * Mock an source instance for a specific source class.
     *
     * @param string $sourceKey The fully qualified class name of the source.
     * @param mixed $mockSource The mocked source instance.
     * @return $this
     */
    public function getSources(): array
    {
        return $this->sources;
    }

    public function mockSource(string $sourceKey, Source $mockedSource): static
    {
        $this->mockedSources[$sourceKey] = $mockedSource;
    
        return $this;
    }

    /**
     * Resolve and return a Source instance.
     *
     * @param  string|Source  $source
     * @param  array|null     $arguments Optional runtime arguments
     * @param  string|null    $variant   Optional variant key
     * @return Source
     */
    public function source(string|Source $source, ?array $arguments = null, ?string $variant = null): Source
    {
        // If the instance already exists
        if ($source instanceof Source) {
            return $source
                ->setSourceable($this)
                ->setVariantArguments($arguments)
                ->setVariant($variant);
        }

        // We resolve the alias mapping
        if (!class_exists($source)) {
            if (!isset($this->sources[$source])) {
                throw new InvalidArgumentException("Source `{$source}` is not defined in the model's sources array.");
            }

            $source = $this->sources[$source];
        }

        if (App::runningUnitTests() && isset($this->mockedSources[$source])) {
            return $this->mockedSources[$source]
                ->setSourceable($this)
                ->setVariantArguments($arguments)
                ->setVariant($variant);
        }

        return App::make($source)
            ->setSourceable($this)
            ->setVariantArguments($arguments)
            ->setVariant($variant);
    }
}
