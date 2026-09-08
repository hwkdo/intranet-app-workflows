<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions;

use Hwkdo\IntranetAppWorkflows\Enums\ActionRunStatus;
use Illuminate\Support\Carbon;

final class ActionResult
{
    /**
     * @param  array<string, mixed>  $output
     * @param  list<string>  $messages
     * @param  list<string>  $errors
     */
    public function __construct(
        public readonly ActionRunStatus $status,
        public readonly string $message = '',
        public readonly array $output = [],
        public readonly array $messages = [],
        public readonly array $errors = [],
        public readonly bool $retryable = false,
        public readonly ?Carbon $waitingUntil = null,
    ) {
        if (! $status->isTerminal() && $status !== ActionRunStatus::Waiting) {
            throw new \InvalidArgumentException('ActionResult status must be terminal or waiting.');
        }

        if ($status === ActionRunStatus::Waiting && $waitingUntil === null) {
            throw new \InvalidArgumentException('Waiting ActionResult requires waitingUntil.');
        }
    }

    public static function succeeded(string $message = 'OK', array $output = [], array $messages = []): self
    {
        return new self(
            status: ActionRunStatus::Succeeded,
            message: $message,
            output: $output,
            messages: $messages !== [] ? $messages : ($message !== '' ? [$message] : []),
        );
    }

    public static function failed(string $message, array $errors = [], bool $retryable = true): self
    {
        return new self(
            status: ActionRunStatus::Failed,
            message: $message,
            errors: $errors !== [] ? $errors : [$message],
            retryable: $retryable,
        );
    }

    public static function skipped(string $message = 'Skipped'): self
    {
        return new self(
            status: ActionRunStatus::Skipped,
            message: $message,
            messages: [$message],
        );
    }

    public static function partial(string $message, array $messages = [], array $errors = [], array $output = []): self
    {
        return new self(
            status: ActionRunStatus::Partial,
            message: $message,
            output: $output,
            messages: $messages,
            errors: $errors,
            retryable: true,
        );
    }

    /**
     * @param  array<string, mixed>  $output
     * @param  list<string>  $messages
     */
    public static function waiting(
        string $message,
        Carbon $until,
        array $output = [],
        array $messages = [],
    ): self {
        return new self(
            status: ActionRunStatus::Waiting,
            message: $message,
            output: $output,
            messages: $messages !== [] ? $messages : [$message],
            waitingUntil: $until,
        );
    }
}
