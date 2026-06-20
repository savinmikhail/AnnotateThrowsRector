<?php

final class DirectThrowMethod
{
    public function run(): void
    {
        throw new RuntimeException('boom');
    }
}

?>
-----
<?php

final class DirectThrowMethod
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
