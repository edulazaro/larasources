<?php

namespace EduLazaro\Larasources\Tests\Fixtures;

use EduLazaro\Larasources\Origins\AgentOrigin;

class CopywriterOrigin extends AgentOrigin
{
    public static string $useProvider = 'anthropic';

    public static function getAlias(): string
    {
        return 'copywriter';
    }

    public function __construct(\EduLazaro\Larasources\Source $source)
    {
        parent::__construct($source);

        $this->provider = static::$useProvider;
    }

    protected function prompt(array $arguments): string
    {
        return 'Describe ' . ($arguments['subject'] ?? 'nothing');
    }
}
