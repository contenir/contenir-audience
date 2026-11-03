<?php

declare(strict_types=1);

namespace Contenir\Audience;

use Contenir\Audience\Binding\Binding;
use Contenir\Audience\Exception\StorageException;
use Contenir\Audience\Service\RemoteEvent;
use Contenir\Audience\Service\RemoteEventKind;
use Contenir\Audience\Subject\Subject;
use Contenir\Audience\Suppression\SuppressionReason;

/**
 * Applies what a service reported through its webhook: whoever left, bounced, complained or was
 * erased is suppressed, so they aren't subscribed again without fresh consent; a changed address
 * leaves its old sync state behind.
 *
 * @api
 */
final readonly class RemoteEvents
{
    public function __construct(
        private Stores $stores,
    ) {}

    private static function reason(RemoteEventKind $kind): ?SuppressionReason
    {
        return match ($kind) {
            RemoteEventKind::Unsubscribed => SuppressionReason::Unsubscribed,
            RemoteEventKind::Cleaned      => SuppressionReason::Cleaned,
            RemoteEventKind::Complaint    => SuppressionReason::Complaint,
            RemoteEventKind::Forgotten    => SuppressionReason::Forgotten,
            RemoteEventKind::EmailChanged => null,
        };
    }

    /**
     * @param list<RemoteEvent> $events
     *
     * @throws StorageException
     */
    public function apply(Binding $binding, array $events): void
    {
        foreach ($events as $event) {
            $hash   = $this->stores->hasher->email($event->email);
            $reason = self::reason($event->kind);
            if (null === $reason) {
                $this->stores->subjects->forget($binding->name, $hash);
                continue;
            }

            $this->stores->suppression->suppress($binding->name, $hash, $reason, $event->at);
            $subject = $this->stores->subjects->find($binding->name, $hash);
            if (null !== $subject) {
                $this->stores->subjects->save(new Subject(
                    $subject->binding,
                    $subject->emailHash,
                    $subject->remoteId,
                    $subject->valuesHash,
                    subscribed: false,
                    syncedAt: $event->at,
                ));
            }
        }
    }
}
