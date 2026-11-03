<?php

declare(strict_types=1);

namespace Contenir\Audience\Service;

use Contenir\Audience\Binding\Binding;
use Psr\Http\Message\ServerRequestInterface;

/**
 * One service's webhook: registering it for a binding's list, and reading what each call reports.
 *
 * @api
 */
interface WebhookReceiverInterface
{
    /**
     * What a call reports, after checking it came from the service and is for the binding's list.
     *
     * @return list<RemoteEvent>
     *
     * @throws InvalidWebhook
     */
    public function parse(ServerRequestInterface $request, Binding $binding): array;

    /**
     * Registers $url for the binding's list, and answers with the service's id for the webhook.
     *
     * @throws ConnectionRefused
     */
    public function register(Binding $binding, string $url): string;
}
