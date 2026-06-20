<?php

final class ExistingThrowsNoDuplicate
{
    /**
     * @throws \RuntimeException
     */
    public function process(): void
    {
        $this->normalize();
    }

    private function normalize(): void
    {
        throw new RuntimeException('boom');
    }
}

?>
-----
<?php

final class ExistingThrowsNoDuplicate
{
    /**
     * @throws \RuntimeException
     */
    public function process(): void
    {
        $this->normalize();
    }

    private function normalize(): void
    {
        throw new RuntimeException('boom');
    }
}

?>
