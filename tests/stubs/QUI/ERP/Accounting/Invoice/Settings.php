<?php

namespace QUI\ERP\Accounting\Invoice;

if (!class_exists(Settings::class)) {
    class Settings
    {
        public static function getInstance(): self
        {
            return new self();
        }

        public function get(string $section, string $key): mixed
        {
            return false;
        }
    }
}
