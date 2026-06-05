<?php

declare(strict_types=1);

namespace KopiBot\Contracts;

interface PluginInterface
{
    public function getName(): string;

    public function getVersion(): string;

    public function getAuthor(): string;

    public function register(): void;
}
