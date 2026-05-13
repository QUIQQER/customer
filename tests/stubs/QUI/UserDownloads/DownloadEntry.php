<?php

namespace QUI\UserDownloads;

use QUI;

if (!class_exists(DownloadEntry::class)) {
    class DownloadEntry
    {
        /**
         * @param QUI\Interfaces\Users\User $User
         */
        public function __construct(protected QUI\Interfaces\Users\User $User)
        {
        }

        public function addUrl(string $url, array $titles): void
        {
        }

        public function removeUrl(string $url): void
        {
        }

        public function update(): void
        {
        }

        public function delete(): void
        {
        }

        public function setTitle(string $lang, string $title): void
        {
        }

        public function setDescription(string $lang, string $description): void
        {
        }

        public function getUrls(): array
        {
            return [];
        }

        public function getQuiqqerMediaUrls(): array
        {
            return [];
        }
    }
}
