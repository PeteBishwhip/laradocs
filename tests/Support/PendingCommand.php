<?php

declare(strict_types=1);

namespace Laradocs\Tests\Support;

use Illuminate\Testing\PendingCommand as LaravelPendingCommand;
use Mockery;

/**
 * Backports Laravel's later expectsOutputToContain() test helper to Laravel 8.
 */
final class PendingCommand extends LaravelPendingCommand
{
    /** @var array<int, string> */
    private $expectedFragments = [];
    /** @var array<int, string> */
    private $unexpectedFragments = [];

    public function expectsOutputToContain(string $fragment): self
    {
        $this->expectedFragments[] = $fragment;

        return $this;
    }

    public function doesntExpectOutputToContain(string $fragment): self
    {
        $this->unexpectedFragments[] = $fragment;

        return $this;
    }

    protected function mockConsoleOutput()
    {
        $mock = parent::mockConsoleOutput();

        $output = $mock->getOutput();

        foreach ($this->expectedFragments as $index => $fragment) {
            $output->shouldReceive('doWrite')
                ->once()
                ->with(Mockery::on(function ($written) use ($fragment): bool {
                    return strpos((string) $written, $fragment) !== false;
                }), Mockery::any())
                ->andReturnUsing(function () use ($index): void {
                    unset($this->expectedFragments[$index]);
                });
        }

        foreach ($this->unexpectedFragments as $fragment) {
            $output->shouldReceive('doWrite')
                ->never()
                ->with(Mockery::on(function ($written) use ($fragment): bool {
                    return strpos((string) $written, $fragment) !== false;
                }), Mockery::any());
        }

        return $mock;
    }

    protected function verifyExpectations()
    {
        parent::verifyExpectations();

        if ($this->expectedFragments !== []) {
            $this->test->fail('Output containing "' . reset($this->expectedFragments) . '" was not printed.');
        }
    }

    protected function flushExpectations()
    {
        parent::flushExpectations();
        $this->expectedFragments = [];
        $this->unexpectedFragments = [];
    }
}
