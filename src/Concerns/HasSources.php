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
     * Declare the source mapping.
     *
     * Use this instead of the `$sources` property when the mapping is static:
     * PHP rejects redeclaring a trait property with a different default, so a
     * model that writes `protected array $sources = [...]` fails to compose and
     * has to fill the property in its constructor.
     *
     * @return array<string, class-string<Source>>
     */
    protected function sources(): array
    {
        return [];
    }

    /**
     * The effective source mapping: the property first, the method on top.
     *
     * @return array<string, class-string<Source>>
     */
    public function getSources(): array
    {
        return array_merge($this->sources, $this->sources());
    }

    /**
     * Mock an source instance for a specific source class.
     *
     * @param string $sourceKey The fully qualified class name of the source.
     * @param mixed $mockSource The mocked source instance.
     * @return $this
     */
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
            $sources = $this->getSources();

            if (!isset($sources[$source])) {
                throw new InvalidArgumentException("Source `{$source}` is not defined in the model's sources array.");
            }

            $source = $sources[$source];
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
