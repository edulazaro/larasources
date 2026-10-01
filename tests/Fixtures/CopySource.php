<?php

namespace EduLazaro\Larasources\Tests\Fixtures;

use EduLazaro\Larasources\Attributes\UsesOrigin;
use EduLazaro\Larasources\Source;

#[UsesOrigin(CopywriterOrigin::class)]
class CopySource extends Source
{
    protected $fillable = ['content'];
}
