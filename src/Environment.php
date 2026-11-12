<?php

declare(strict_types=1);

namespace CodeConjure\Gls;

enum Environment: string
{
    case Production = 'production';
    case Test = 'test';

    public static function fromSandboxFlag(bool $sandbox): self
    {
        return $sandbox ? self::Test : self::Production;
    }
}
