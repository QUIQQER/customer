<?php

namespace Mcp\Schema\Result;

if (!class_exists(CallToolResult::class)) {
    class CallToolResult
    {
        /** @param array<mixed> $content */
        public function __construct(public array $content = [], public bool $isError = false)
        {
        }
    }
}
