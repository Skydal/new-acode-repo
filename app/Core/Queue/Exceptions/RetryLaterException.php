<?php
namespace VMP\Core\Queue\Exceptions;

defined('ABSPATH') || exit;

class RetryLaterException extends \RuntimeException
{
    private ?int $delaySeconds;

    public function __construct(string $message = '', ?int $delaySeconds = null, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->delaySeconds = $delaySeconds;
    }

    public function getDelaySeconds(): ?int
    {
        return $this->delaySeconds;
    }
}
