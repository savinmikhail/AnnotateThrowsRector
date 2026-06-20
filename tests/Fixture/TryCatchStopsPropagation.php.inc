<?php

final class TryCatchStopsPropagation
{
    public function process(): void
    {
        try {
            $this->normalize();
        } catch (RuntimeException) {
        }
    }

    private function normalize(): void
    {
        throw new RuntimeException('boom');
    }
}

?>
-----
<?php

final class TryCatchStopsPropagation
{
    public function process(): void
    {
        try {
            $this->normalize();
        } catch (RuntimeException) {
        }
    }

    private function normalize(): void
    {
        throw new RuntimeException('boom');
    }
}

?>
