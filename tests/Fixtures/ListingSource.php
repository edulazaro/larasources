<?php

namespace EduLazaro\Larasources\Tests\Fixtures;

use EduLazaro\Larasources\Attributes\UsesOrigin;
use EduLazaro\Larasources\Source;

#[UsesOrigin(HttpListingOrigin::class)]
class ListingSource extends Source
{
    protected $fillable = ['title'];

    public function build(): void
    {
        $this->fill(['title' => 'A flat with a view']);
    }
}
