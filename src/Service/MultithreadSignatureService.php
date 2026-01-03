<?php declare(strict_types=1);

namespace Tbessenreither\PhpMultithread\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;


class MultithreadSignatureService
{

    private const REHASH_SALT = 'ar~#jjs5:EsH+U$grQ7§<JJdCyx9@aH}!SXRfWYQ?2x6rrc#_VMBxCzAW%P7up{?';
    private string $appSecretRehashed;

    public function __construct(
        #[Autowire('%env(APP_SECRET)%')]
        string $appSecret
    ) {
        $this->appSecretRehashed = hash('sha256', $appSecret . self::REHASH_SALT);
    }

    public function signString(string $string): string
    {
        return $this->getSignature($string) . ':' . base64_encode($string);
    }

    public function verify(string $data): ?string
    {
        $dataParts = explode(':', $data);
        if (count($dataParts) !== 2) {
            return null;
        }
        $expectedSignature = $dataParts[0];
        $base64EncodedString = $dataParts[1];

        $decodedString = base64_decode($base64EncodedString);
        $calculatedSignature = $this->getSignature($decodedString);

        if ($calculatedSignature !== $expectedSignature) {
            return null;
        }

        return $decodedString;
    }

    private function getSignature(string $string): string
    {
        return hash_hmac('sha256', $string, $this->appSecretRehashed);
    }

}
