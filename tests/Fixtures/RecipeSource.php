<?php

namespace EduLazaro\Larasources\Tests\Fixtures;

use EduLazaro\Larasources\Attributes\UsesOrigin;
use EduLazaro\Larasources\Source;

#[UsesOrigin(HtmlRecipeOrigin::class)]
class RecipeSource extends Source
{
    protected $fillable = ['title', 'author', 'yield', 'source'];
}
