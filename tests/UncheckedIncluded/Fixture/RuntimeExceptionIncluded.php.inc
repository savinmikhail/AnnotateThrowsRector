<?php

final class RuntimeExceptionIncluded
{
    public function run(): void
    {
        throw new RuntimeException('boom');
    }
}

?>
-----
<?php

final class RuntimeExceptionIncluded
{
    /**
     * @throws \RuntimeException
     */
    public function run(): void
    {
        throw new RuntimeException('boom');
    }
}

?>
