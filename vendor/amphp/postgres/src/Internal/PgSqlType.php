<?php declare(strict_types=1);

namespace Amp\Postgres\Internal;

/** @internal */
final class PgSqlType
{
    private static ?self $default = null;

    public function __construct(
        public readonly string $category,
        public readonly string $name,
        public readonly string $delimiter,
        public readonly int $element,
    ) {
    }

    public static function getDefaultType(): self
    {
        return self::$default ??= new self('S', 'text', ',', 0);
    }
}
