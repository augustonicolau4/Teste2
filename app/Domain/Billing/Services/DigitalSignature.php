<?php

namespace App\Domain\Billing\Services;

use RuntimeException;

/**
 * Assinatura digital assimétrica RSA/OpenSSL dos documentos fiscais.
 *
 * A chave privada é gerada (2048 bits) caso não exista em storage/app/keys.
 * A assinatura é aplicada sobre o hash do documento (cadeia de hash).
 */
class DigitalSignature
{
    private ?string $privateKeyPath;
    private ?string $publicKeyPath;
    private ?string $passphrase;

    public function __construct(
        ?string $privateKeyPath = null,
        ?string $publicKeyPath = null,
        ?string $passphrase = null,
    ) {
        $this->privateKeyPath = $privateKeyPath ?? config('autogest.signature.private_key_path');
        $this->publicKeyPath  = $publicKeyPath ?? config('autogest.signature.public_key_path');
        $this->passphrase     = $passphrase ?? (string) config('autogest.signature.passphrase', '');
    }

    /**
     * Assina um hash/documento. Devolve a assinatura em Base64.
     */
    public function sign(string $data): string
    {
        $privateKey = $this->loadPrivateKey();

        $signature = '';
        $ok = openssl_sign($data, $signature, $privateKey, config('autogest.signature.algorithm', OPENSSL_ALGO_SHA256));

        if (! $ok) {
            throw new RuntimeException('Falha ao assinar o documento com a chave privada RSA.');
        }

        return base64_encode($signature);
    }

    /**
     * Verifica uma assinatura contra o conteúdo original.
     */
    public function verify(string $data, string $base64Signature): bool
    {
        $publicKey = $this->loadPublicKey();

        return openssl_verify(
            $data,
            base64_decode($base64Signature),
            $publicKey,
            config('autogest.signature.algorithm', OPENSSL_ALGO_SHA256),
        ) === 1;
    }

    /**
     * Garante a existência do par de chaves, gerando-o se necessário.
     *
     * @return array{private: string, public: string}
     */
    public function ensureKeysExist(): array
    {
        if (! file_exists($this->privateKeyPath) || ! file_exists($this->publicKeyPath)) {
            $this->generateKeyPair();
        }

        return ['private' => $this->privateKeyPath, 'public' => $this->publicKeyPath];
    }

    private function generateKeyPair(): void
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($resource === false) {
            throw new RuntimeException('Não foi possível gerar o par de chaves RSA (OpenSSL).');
        }

        $exportArgs = [$resource, &$privateKeyPem];
        if ($this->passphrase !== '') {
            $exportArgs[] = $this->passphrase;
        }

        openssl_pkey_export(...$exportArgs);

        $details = openssl_pkey_get_details($resource);
        $publicKeyPem = $details['key'] ?? null;

        if (! $privateKeyPem || ! $publicKeyPem) {
            throw new RuntimeException('Falha ao exportar as chaves RSA.');
        }

        $this->ensureDirectory(dirname($this->privateKeyPath));
        file_put_contents($this->privateKeyPath, $privateKeyPem);
        chmod($this->privateKeyPath, 0600);
        file_put_contents($this->publicKeyPath, $publicKeyPem);
    }

    private function loadPrivateKey(): \OpenSSLAsymmetricKey
    {
        $this->ensureKeysExist();

        $key = openssl_pkey_get_private(
            file_get_contents($this->privateKeyPath),
            $this->passphrase ?: null,
        );

        if ($key === false) {
            throw new RuntimeException('Não foi possível carregar a chave privada RSA.');
        }

        return $key;
    }

    private function loadPublicKey(): \OpenSSLAsymmetricKey
    {
        $this->ensureKeysExist();

        $key = openssl_pkey_get_public(file_get_contents($this->publicKeyPath));

        if ($key === false) {
            throw new RuntimeException('Não foi possível carregar a chave pública RSA.');
        }

        return $key;
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
    }
}
