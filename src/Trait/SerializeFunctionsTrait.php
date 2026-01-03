<?php declare(strict_types=1);

namespace Tbessenreither\PhpMultithread\Trait;


trait SerializeFunctionsTrait
{

    public function getSerialized(): string
    {
        return serialize($this);
    }

    public static function fromSerialized(string $serialized): self
    {
        return unserialize(trim($serialized));
    }

}
