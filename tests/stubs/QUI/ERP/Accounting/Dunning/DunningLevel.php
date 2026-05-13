<?php

namespace QUI\ERP\Accounting\Dunning;

if (!class_exists(DunningLevel::class)) {
    class DunningLevel
    {
        public function getLevel(): int
        {
            return 0;
        }
    }
}
