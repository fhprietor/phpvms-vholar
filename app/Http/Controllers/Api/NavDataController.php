<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Controller;
use App\Exceptions\NavDataNotConfigured;
use App\Exceptions\NavDataUnsupportedCipher;
use App\Models\User;
use App\Services\NavDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Entrega las credenciales del servicio NavData al cliente ACARS autenticado.
 *
 * phpVMS no actua de proxy: solo sella y entrega la URL y la clave, y a partir
 * de ahi el cliente se entiende directamente con NavData. La clave nunca viaja
 * en claro (ver App\Services\NavDataService).
 */
class NavDataController extends Controller
{
    /** Header opcional con el que el cliente elige el sobre. */
    public const CIPHER_HEADER = 'X-NavData-Cipher';

    public function __construct(
        private readonly NavDataService $navDataSvc
    ) {}

    /**
     * Devuelve el sobre con la URL y la clave de NavData.
     */
    public function get(Request $request): JsonResponse
    {
        $cipher = $this->resolveCipher($request);

        if (!$this->navDataSvc->isConfigured()) {
            throw new NavDataNotConfigured();
        }

        /** @var User $user */
        $user = Auth::user();
        $envelope = $this->navDataSvc->sealForUser($user, $cipher);

        $this->logDelivery($request, $user, $envelope);

        return response()->json(['data' => $envelope], 200, [
            'Cache-Control' => 'no-store, private',
            'Pragma'        => 'no-cache',
        ]);
    }

    /**
     * El cliente moderno pide aes-256-gcm y el de .NET Framework 4.8.1
     * aes-256-cbc-hmac-sha256; sin header se usa el defecto.
     */
    private function resolveCipher(Request $request): string
    {
        $cipher = trim((string) $request->header(self::CIPHER_HEADER, ''));

        if ($cipher === '') {
            return NavDataService::DEFAULT_CIPHER;
        }

        if (!$this->navDataSvc->supportsCipher($cipher)) {
            throw new NavDataUnsupportedCipher($cipher);
        }

        return $cipher;
    }

    /**
     * Deja constancia de quien se ha llevado la clave: sin esto una entrega
     * queda invisible en la auditoria.
     */
    private function logDelivery(Request $request, User $user, array $envelope): void
    {
        activity()
            ->useLog('navdata')
            ->causedBy($user)
            ->withProperties([
                'pilot_id'   => $user->pilot_id,
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
                'cipher'     => $envelope['cipher'],
                'key_id'     => $envelope['key_id'],
                'expires_at' => $envelope['expires_at'],
            ])
            ->log('NavData API credentials delivered');
    }
}
