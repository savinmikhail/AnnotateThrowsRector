<?php

namespace App;

use RuntimeException;

final class InterClassPropagationResolvesAliasedThrows
{
    public function __construct(private ExternalServiceWithAlias $externalService)
    {
    }

    public function run(): void
    {
        $this->externalService->process();
    }
}

final class ExternalServiceWithAlias
{
    /**
     * @throws RuntimeException
     */
    public function process(): void
    {
    }
}

?>
-----
<?php

namespace App;

use RuntimeException;

final class InterClassPropagationResolvesAliasedThrows
{
    public function __construct(private ExternalServiceWithAlias $externalService)
    {
    }

    /**
     * @throws \RuntimeException
     */
    public function run(): void
    {
        $this->externalService->process();
    }
}

final class ExternalServiceWithAlias
{
    /**
     * @throws RuntimeException
     */
    public function process(): void
    {
    }
}

?>
