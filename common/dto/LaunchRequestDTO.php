<?php

namespace common\dto;

final readonly class LaunchRequestDTO
{
    public function __construct(
        public string $mode,
        public ?string $registrationKey = null,
        public ?string $learnerId = null,
        public ?string $learnerName = null,
        public ?string $courseRef = null,
    ) {
    }
}
