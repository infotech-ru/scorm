<?php

namespace common\exceptions;

use common\enums\ApiErrorCode;
use RuntimeException;

class ScormPackageException extends RuntimeException
{
    public function __construct(private readonly ApiErrorCode $errorCode, string $message)
    {
        parent::__construct($message);
    }

    public function getErrorCode(): ApiErrorCode
    {
        return $this->errorCode;
    }
}
