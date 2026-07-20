<?php
namespace Vendor\AI\Exceptions;

class ProviderException extends \RuntimeException {}
class AllProvidersFailedException extends \RuntimeException {}
class CircuitOpenException extends \RuntimeException {}
class JobCancelledException extends \RuntimeException {}
class DeadlineExceededException extends \RuntimeException {}
