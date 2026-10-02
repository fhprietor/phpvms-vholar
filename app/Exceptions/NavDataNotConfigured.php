<?php

namespace App\Exceptions;

/**
 * Se ha pedido la clave de NavData pero el staff no ha configurado el servicio
 * (URL + clave) en Admin > Settings.
 */
class NavDataNotConfigured extends AbstractHttpException
{
    public function __construct()
    {
        parent::__construct(
            503,
            'The NavData API credentials are not configured'
        );
    }

    /**
     * Return the RFC 7807 error type (without the URL root)
     */
    public function getErrorType(): string
    {
        return 'navdata-not-configured';
    }

    /**
     * Get the detailed error string
     */
    public function getErrorDetails(): string
    {
        return 'An administrator must set the NavData API URL and API key in Admin > Settings before ACARS clients can request them.';
    }

    /**
     * Return an array with the error details, merged with the RFC7807 response
     */
    public function getErrorMetadata(): array
    {
        return [
            'settings' => [
                'general.navdata_api_url',
                'general.navdata_api_key',
            ],
        ];
    }
}
