<?php

namespace QUI\ERP\DemoData\DTO;

if (!class_exists(DemoDataCreationContext::class, false)) {
    final readonly class DemoDataCreationContext
    {
        public function __construct(
            private DemoDataReferenceCollection $dependencyData,
            private array $dateRanges = []
        ) {
        }

        public function getAllDependencyData(): DemoDataReferenceCollection
        {
            return $this->dependencyData;
        }

        public function getDateRanges(): array
        {
            return $this->dateRanges;
        }
    }
}
