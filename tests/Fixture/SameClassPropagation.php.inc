<?php

final class SameClassPropagation
{
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

final class SameClassPropagation
{
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
