## AnnotateThrowsRector

Rector extension that adds missing `@throws` tags for:

- direct `throw` expressions in class methods
- propagation through same-class calls like `$this->foo()`, `self::foo()`, `static::foo()`
- propagation through inter-class calls when the callee already has `@throws`

Current MVP scope:

- class methods only
- inter-class propagation relies on existing callee docblocks
- repeated Rector runs can gradually converge the call graph across files
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
