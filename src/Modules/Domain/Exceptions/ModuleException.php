<?php

declare(strict_types=1);

namespace Pollora\Modules\Domain\Exceptions;

use Exception;

final class ModuleException extends Exception
{
    public static function notFound(string $name): self
    {
        return new self(sprintf('Module [%s] not found.', $name));
    }

    public static function alreadyEnabled(string $name): self
    {
        return new self(sprintf('Module [%s] is already enabled.', $name));
    }

    public static function alreadyDisabled(string $name): self
    {
        return new self(sprintf('Module [%s] is already disabled.', $name));
    }

    public static function cannotEnable(string $name, string $reason = ''): static
    {
        $message = sprintf('Cannot enable module [%s]', $name);
        if ($reason !== '' && $reason !== '0') {
            $message .= ': '.$reason;
        }

        return new self($message);
    }

    public static function cannotDisable(string $name, string $reason = ''): static
    {
        $message = sprintf('Cannot disable module [%s]', $name);
        if ($reason !== '' && $reason !== '0') {
            $message .= ': '.$reason;
        }

        return new self($message);
    }

    /**
     * @param  string  $repository  The GitHub repository whose information could not be read
     */
    public static function repositoryUnreachable(string $repository): self
    {
        return new self('Unable to fetch repository information for '.$repository.'. Check the repository name and that GitHub is reachable.');
    }

    public static function archiveNotDownloaded(string $url): self
    {
        return new self('Unable to download archive from '.$url.'. Check the release exists and that GitHub is reachable.');
    }

    public static function archiveNotReadable(string $path): self
    {
        return new self('Unable to open ZIP archive: '.$path.'. The download may be incomplete: try again.');
    }

    public static function archiveEmpty(string $path): self
    {
        return new self('No extracted folder found in '.$path.'. The archive has no module folder: check the release asset.');
    }
}
