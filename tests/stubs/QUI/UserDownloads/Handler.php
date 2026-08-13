<?php

namespace QUI\UserDownloads;

if (!class_exists(Handler::class)) {
    class Handler
    {
        /** @var array<int|string, DownloadEntry> */
        private static array $entries = [];
        private static int $nextId = 1;

        public static function getDownloadEntryById(int|string $id): DownloadEntry
        {
            if (!isset(self::$entries[$id])) {
                throw new Exception('Download entry not found.');
            }

            return self::$entries[$id];
        }

        public static function addDownloadEntry(DownloadEntry $DownloadEntry): int
        {
            $id = self::$nextId++;
            self::$entries[$id] = $DownloadEntry;

            return $id;
        }

        public static function reset(): void
        {
            self::$entries = [];
            self::$nextId = 1;
        }
    }
}
