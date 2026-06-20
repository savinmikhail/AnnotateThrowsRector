<?php

namespace App;

interface TransportExceptionInterface extends \Throwable {}

final class ExternalServiceWithInterface
{
    /**
     * @throws \App\TransportExceptionInterface
     */
    public function process(): void
    {
    }
}

final class InterClassPropagationAddsInterfaces
{
    public function __construct(private ExternalServiceWithInterface $externalService)
    {
    }

    public function run(): void
    {
        $this->externalService->process();
    }
}

?>
-----
<?php

namespace App;

interface TransportExceptionInterface extends \Throwable {}

final class ExternalServiceWithInterface
{
    /**
     * @throws \App\TransportExceptionInterface
     */
    public function process(): void
    {
    }
}

final class InterClassPropagationAddsInterfaces
{
    public function __construct(private ExternalServiceWithInterface $externalService)
    {
    }

    /**
     * @throws \App\TransportExceptionInterface
     */
    public function run(): void
    {
        $this->externalService->process();
    }
}

?>
