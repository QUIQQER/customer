<?php

namespace QUI\ERP\Order\ProcessingStatus;

if (!class_exists(Handler::class)) {
    class Handler
    {
        public function getCancelledStatus(): Status
        {
            return new Status();
        }
    }
}
