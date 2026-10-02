<?php

namespace App\Exceptions;

use App\Services\NavDataService;

/**
 * El cliente ha pedido un sobre con un cifrado que phpVMS no sabe emitir.
 */
class NavDataUnsupportedCipher extends AbstractHttpException
{
    public function __construct(
        private readonly string $cipher
    ) {
        parent::__construct(
            400,
            'Unsupported NavData envelope cipher "'.$cipher.'"'
        );
    }

    /**
     * Return the RFC 7807 error type (without the URL root)
     */
    public function getErrorType(): string
    {
        return 'navdata-unsupported-cipher';
    }

    /**
     * Get the detailed error string
     */
    public function getErrorDetails(): string
    {
        return 'Send X-NavData-Cipher with one of: '.implode(', ', NavDataService::supportedCiphers());
    }

    /**
     * Return an array with the error details, merged with the RFC7807 response
     */
    public function getErrorMetadata(): array
    {
        return [
            'cipher'    => $this->cipher,
            'supported' => NavDataService::supportedCiphers(),
        ];
    }
}
