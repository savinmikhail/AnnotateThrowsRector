<?php

final class InterClassPropagationFromExistingThrows
{
    public function __construct(private ExternalService $externalService)
    {
    }

    public function run(): void
    {
        $this->externalService->process();
    }
}

final class ExternalService
{
    /**
     * @throws \RuntimeException
     */
    public function process(): void
    {
    }
}

?>
-----
<?php

final class InterClassPropagationFromExistingThrows
{
    public function __construct(private ExternalService $externalService)
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

final class ExternalService
{
    /**
     * @throws \RuntimeException
     */
    public function process(): void
    {
    }
}

?>
