<?php

namespace QUI\ERP\Order\ProcessingStatus;

if (!class_exists(Status::class)) {
    class Status
    {
        public function getId(): int
        {
            return 0;
        }
    }
}
