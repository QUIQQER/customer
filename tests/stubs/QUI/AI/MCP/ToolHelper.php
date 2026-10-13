<?php

namespace QUI\AI\MCP;

use Mcp\Schema\Result\CallToolResult;

if (!class_exists(ToolHelper::class)) {
    class ToolHelper
    {
        private static mixed $LastException = null;

        public static function parseExceptionToResult(mixed $Exception): CallToolResult
        {
            self::$LastException = $Exception;

            return new CallToolResult([], true);
        }

        public static function getLastException(): mixed
        {
            return self::$LastException;
        }
    }
}
