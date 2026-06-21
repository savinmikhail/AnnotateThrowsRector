<?php

namespace App;

interface TransportExceptionInterface extends \Throwable {}

final class ExternalServiceWithDescription
{
    /**
     * @throws \App\TransportExceptionInterface When an unsupported option is passed
     */
    public function process(): void
    {
    }
}

final class InterClassPropagationIgnoresThrowsDescriptions
{
    public function __construct(private ExternalServiceWithDescription $externalService)
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

final class ExternalServiceWithDescription
{
    /**
     * @throws \App\TransportExceptionInterface When an unsupported option is passed
     */
    public function process(): void
    {
    }
}

final class InterClassPropagationIgnoresThrowsDescriptions
{
    public function __construct(private ExternalServiceWithDescription $externalService)
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
