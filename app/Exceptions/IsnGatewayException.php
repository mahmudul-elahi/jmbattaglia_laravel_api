<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class IsnGatewayException extends HttpException
{
    public function __construct(public readonly string $kind, public readonly ?string $remoteId = null)
    {
        parent::__construct(match ($kind) {
            'PERMISSION' => 403,'REJECTED' => 400,'NOT_FOUND' => 404,'AMBIGUOUS' => 409,default => 503
        }, match ($kind) {
            'PERMISSION' => 'ISN credentials do not permit this operation.','REJECTED' => 'ISN rejected the request.','NOT_FOUND' => 'ISN resource not found.','AMBIGUOUS' => 'ISN write outcome is uncertain. Inspect the existing attempt before retrying.',default => 'ISN is unavailable or not configured.'
        });
    }
}
