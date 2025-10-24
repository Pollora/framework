<?php

namespace Pollora\Logging\Domain\Models;

readonly class WordPressError
{
    public function __construct(
        public WordPressErrorType $type,
        public string $function,
        public string $message,
        public string $version,
        public ?string $replacement = null,
        public array $context = []
    ) {}

    public function getLogLevel(): string
    {
        return $this->type->getLogLevel();
    }

    public function getLogMessage(): string
    {
        return $this->type->getLogMessage($this->function);
    }

    public function getLogContext(): array
    {
        $context = [
            'type' => $this->type->value,
            'function' => $this->function,
            'version' => $this->version,
            'message' => $this->message,
        ];

        if ($this->replacement !== null) {
            $context['replacement'] = $this->replacement;
        }

        return array_merge($context, $this->context);
    }

    public static function doingItWrong(
        string $function,
        string $message,
        string $version,
        array $context = []
    ): self {
        return new self(
            WordPressErrorType::DOING_IT_WRONG,
            $function,
            strip_tags($message),
            $version,
            null,
            $context
        );
    }

    public static function deprecatedFunction(
        string $function,
        string $replacement,
        string $version,
        array $context = []
    ): self {
        return new self(
            WordPressErrorType::DEPRECATED_FUNCTION,
            $function,
            '',
            $version,
            $replacement ?: 'no alternative available',
            $context
        );
    }

    public static function deprecatedArgument(
        string $function,
        string $message,
        string $version,
        array $context = []
    ): self {
        return new self(
            WordPressErrorType::DEPRECATED_ARGUMENT,
            $function,
            strip_tags($message),
            $version,
            null,
            $context
        );
    }
}