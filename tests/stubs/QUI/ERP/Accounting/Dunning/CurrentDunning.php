<?php

namespace QUI\ERP\Accounting\Dunning;

if (!class_exists(CurrentDunning::class)) {
    class CurrentDunning
    {
        public function getDunningLevel(): DunningLevel
        {
            return new DunningLevel();
        }
    }
}
