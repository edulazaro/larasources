<?php

namespace EduLazaro\Larasources\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class UsesOrigin
{
    public function __construct(
        public string|null $name = null,
        public string|null $originClass = null
    ) {
        if (is_string($name) && class_exists($name) && is_null($originClass)) {
            $this->originClass = $name;
            $this->name = null;
        }
    }
}
