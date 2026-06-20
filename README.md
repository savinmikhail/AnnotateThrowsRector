## AnnotateThrowsRector

Rector extension that adds missing `@throws` tags for:

- direct `throw` expressions in class methods
- propagation through same-class calls like `$this->foo()`, `self::foo()`, `static::foo()`
- methods that call other same-class methods already documented with `@throws`

Current MVP scope:

- class methods only
- same-class propagation only
- caught exceptions inside `try/catch` are not propagated
- existing `@throws` tags are preserved and deduplicated
- no removal of stale `@throws` tags yet

## Install

```bash
composer require --dev savinmikhail/annotate_throws_rector
```

## Configure

```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use SavinMikhail\AnnotateThrowsRector\AnnotateThrowsRector;

return RectorConfig::configure()
    ->withRules([
        AnnotateThrowsRector::class,
    ]);
```

## Example

```php
final class InterviewRunner
{
    public function process(): void
    {
        $this->normalize();
    }

    public function normalize(): void
    {
        throw new \RuntimeException('Boom');
    }
}
```

becomes

```php
final class InterviewRunner
{
    /**
     * @throws \RuntimeException
     */
    public function process(): void
    {
        $this->normalize();
    }

    /**
     * @throws \RuntimeException
     */
    public function normalize(): void
    {
        throw new \RuntimeException('Boom');
    }
}
```
