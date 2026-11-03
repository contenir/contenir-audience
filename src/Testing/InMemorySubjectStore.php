<?php

declare(strict_types=1);

namespace Contenir\Audience\Testing;

use Contenir\Audience\Subject\Subject;
use Contenir\Audience\Subject\SubjectStoreInterface;
use Override;

/**
 * Sync state held in memory, for tests.
 *
 * @api
 */
final class InMemorySubjectStore implements SubjectStoreInterface
{
    /** @var array<string, Subject> */
    public array $subjects = [];

    #[Override]
    public function find(string $binding, string $emailHash): ?Subject
    {
        return $this->subjects["{$binding}\0{$emailHash}"] ?? null;
    }

    #[Override]
    public function forget(string $binding, string $emailHash): void
    {
        unset($this->subjects["{$binding}\0{$emailHash}"]);
    }

    #[Override]
    public function save(Subject $subject): void
    {
        $this->subjects["{$subject->binding}\0{$subject->emailHash}"] = $subject;
    }
}
