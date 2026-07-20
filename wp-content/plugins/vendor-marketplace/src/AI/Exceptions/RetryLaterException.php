<?php
namespace Vendor\AI\Exceptions;

class RetryLaterException extends \RuntimeException
{
    private int $delayMs;

    public function __construct(int $delayMs, string $message = '')
    {
        parent::__construct($message ?: 'Retry later');
        $this->delayMs = $delayMs;
    }

    public function getDelayMs(): int
    {
        return $this->delayMs;
    }
}
