<?php

final class DirectCheckedException
{
    public function run(): void
    {
        throw new JsonException('boom');
    }
}

?>
-----
<?php

final class DirectCheckedException
{
    /**
     * @throws \JsonException
     */
    public function run(): void
    {
        throw new JsonException('boom');
    }
}

?>
