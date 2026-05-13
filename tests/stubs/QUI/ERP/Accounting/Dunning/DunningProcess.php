<?php

namespace QUI\ERP\Accounting\Dunning;

if (!class_exists(DunningProcess::class)) {
    class DunningProcess
    {
        public function getCurrentDunning(): ?CurrentDunning
        {
            return null;
        }
    }
}
