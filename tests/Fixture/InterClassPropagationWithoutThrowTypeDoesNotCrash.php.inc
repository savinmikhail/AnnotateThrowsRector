<?php

final class InterClassPropagationWithoutThrowTypeDoesNotCrash
{
    public function __construct(private ExternalServiceWithoutThrowType $externalService)
    {
    }

    public function run(): void
    {
        $this->externalService->process();
    }
}

final class ExternalServiceWithoutThrowType
{
    public function process(): void
    {
    }
}

?>
-----
<?php

final class InterClassPropagationWithoutThrowTypeDoesNotCrash
{
    public function __construct(private ExternalServiceWithoutThrowType $externalService)
    {
    }

    public function run(): void
    {
        $this->externalService->process();
    }
}

final class ExternalServiceWithoutThrowType
{
    public function process(): void
    {
    }
}

?>
