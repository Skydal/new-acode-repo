<?php
namespace VMP\Core\Queue\Exceptions;

defined('ABSPATH') || exit;

class RetryLaterException extends \RuntimeException
{
    // Optionally include a $delaySeconds override in future
}
