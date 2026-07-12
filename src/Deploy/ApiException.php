<?php

declare(strict_types=1);

namespace Laradocs\Deploy;

use RuntimeException;

final class ApiException extends RuntimeException
{
    /**
     * @readonly
     * @var int
     */
    public $status = 0;
    /**
     * @var array<string, mixed>
     * @readonly
     */
    public $body = [];
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        string $message,
        int $status = 0,
        array $body = []
    ) {
        $this->status = $status;
        $this->body = $body;
        parent::__construct($message);
    }

    /**
     * The most useful human-facing message we can extract from a failed
     * response: a recorded deployment failure, a top-level message, or the
     * first validation error.
     */
    public function userMessage(): string
    {
        return $this->deploymentMessage()
            ?? $this->firstValidationError()
            ?? $this->topMessage();
    }

    private function deploymentMessage(): ?string
    {
        $deployment = $this->body['deployment'] ?? null;

        return is_array($deployment) && isset($deployment['message']) && is_string($deployment['message'])
            ? $deployment['message']
            : null;
    }

    private function firstValidationError(): ?string
    {
        $errors = $this->body['errors'] ?? null;

        if (! is_array($errors)) {
            return null;
        }

        $first = collect($errors)->flatten()->first();

        return is_string($first) ? $first : null;
    }

    private function topMessage(): string
    {
        return is_string($this->body['message'] ?? null) ? $this->body['message'] : $this->getMessage();
    }
}
