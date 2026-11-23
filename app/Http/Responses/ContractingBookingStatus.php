<?php

namespace App\Http\Responses;

use App\Support\ProcessSupport;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ContractingBookingStatus
{
    public static function render(Request $request, string $state, int $status = 200): Response
    {
        $response = Inertia::render('Public/ContractingBookingStatus', [
            'state' => $state,
            'whatsappUrl' => ProcessSupport::whatsappUrl('Hola, necesito ayuda con mi cita de contratación en The Velvet Studio.'),
        ])->toResponse($request);

        $response->setStatusCode($status);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    public static function renderException(HttpExceptionInterface $exception, Request $request): ?Response
    {
        if (! $request->routeIs('contracting.booking.show', 'contracting.booking.book')
            || ($request->expectsJson() && ! $request->header('X-Inertia'))) {
            return null;
        }

        if ($exception instanceof InvalidSignatureException && URL::hasCorrectSignature($request)) {
            return self::render($request, 'expired', 410);
        }

        $state = match ($exception->getStatusCode()) {
            403, 404 => 'invalid',
            410, 422 => 'unavailable',
            409 => 'conflict',
            default => null,
        };

        return $state ? self::render($request, $state, $exception->getStatusCode()) : null;
    }
}
