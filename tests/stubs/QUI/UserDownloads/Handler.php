<?php

namespace QUI\UserDownloads;

if (!class_exists(Handler::class)) {
    class Handler
    {
        public static function getDownloadEntryById(int|string $id): DownloadEntry
        {
            return new DownloadEntry(new \QUI\Users\Nobody());
        }

        public static function addDownloadEntry(DownloadEntry $DownloadEntry): int
        {
            return 0;
        }
    }
}
