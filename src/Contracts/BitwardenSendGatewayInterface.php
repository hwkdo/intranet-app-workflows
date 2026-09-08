<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Contracts;

interface BitwardenSendGatewayInterface
{
    /**
     * Erstellt einen Bitwarden Send und gibt die Access-URL zurück.
     *
     * @throws \Throwable
     */
    public function createSend(
        string $name,
        string $content,
        ?int $maxAccessCount = null,
        ?int $deleteInDays = null,
    ): string;
}
