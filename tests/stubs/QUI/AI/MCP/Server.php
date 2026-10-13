<?php

namespace QUI\AI\MCP;

use QUI\Interfaces\Users\User;
use QUI\Users\Nobody;

if (!class_exists(Server::class)) {
    class Server
    {
        private static ?User $RequestUser = null;

        public static function getRequestUser(): User
        {
            return self::$RequestUser ?? new Nobody();
        }
    }
}
