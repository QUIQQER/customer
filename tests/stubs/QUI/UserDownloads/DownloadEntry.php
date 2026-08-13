<?php

namespace QUI\UserDownloads;

use QUI;

if (!class_exists(DownloadEntry::class)) {
    class DownloadEntry
    {
        /** @var list<array{url: string, titles: array<string, string>}> */
        private array $urls = [];
        /** @var array<string, string> */
        private array $titles = [];
        /** @var array<string, string> */
        private array $descriptions = [];

        /**
         * @param QUI\Interfaces\Users\User $User
         */
        public function __construct(protected QUI\Interfaces\Users\User $User)
        {
        }

        public function addUrl(string $url, array $titles): void
        {
            $this->urls[] = [
                'url' => $url,
                'titles' => $titles
            ];
        }

        public function removeUrl(string $url): void
        {
            $this->urls = array_values(array_filter(
                $this->urls,
                static fn(array $entry): bool => $entry['url'] !== $url
            ));
        }

        public function update(): void
        {
        }

        public function delete(): void
        {
        }

        public function setTitle(string $lang, string $title): void
        {
            $this->titles[$lang] = $title;
        }

        public function setDescription(string $lang, string $description): void
        {
            $this->descriptions[$lang] = $description;
        }

        public function getUrls(): array
        {
            return $this->urls;
        }

        public function getQuiqqerMediaUrls(): array
        {
            return [];
        }
    }
}
