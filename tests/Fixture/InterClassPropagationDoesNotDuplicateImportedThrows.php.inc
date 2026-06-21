<?php

namespace App;

use App\Contracts\TransportExceptionInterface;

final class ExternalServiceWithImportedThrows
{
    /**
     * @throws TransportExceptionInterface
     */
    public function process(): void
    {
    }
}

final class InterClassPropagationDoesNotDuplicateImportedThrows
{
    public function __construct(private ExternalServiceWithImportedThrows $externalService)
    {
    }

    public function run(): void
    {
        $this->externalService->process();
    }
}

namespace App\Contracts;

interface TransportExceptionInterface extends \Throwable {}

?>
-----
<?php

namespace App;

use App\Contracts\TransportExceptionInterface;

final class ExternalServiceWithImportedThrows
{
    /**
     * @throws TransportExceptionInterface
     */
    public function process(): void
    {
    }
}

final class InterClassPropagationDoesNotDuplicateImportedThrows
{
    public function __construct(private ExternalServiceWithImportedThrows $externalService)
    {
    }

    /**
     * @throws \App\Contracts\TransportExceptionInterface
     */
    public function run(): void
    {
        $this->externalService->process();
    }
}

namespace App\Contracts;

interface TransportExceptionInterface extends \Throwable {}

?>
