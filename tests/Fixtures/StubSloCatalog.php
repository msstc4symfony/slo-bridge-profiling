<?php

declare(strict_types=1);

namespace Msstc4Symfony\SloBridgeProfiling\Test\Fixtures;

use Msstc4Symfony\SloBundle\Domain\Entity\SloDefinition;
use Msstc4Symfony\SloBundle\Domain\Enum\PointType;
use Msstc4Symfony\SloBundle\Domain\Enum\StrategyPreference;
use Msstc4Symfony\SloBundle\Domain\Repository\SloCatalogInterface;
use Msstc4Symfony\SloBundle\Domain\ValueObject\Classification;
use Msstc4Symfony\SloBundle\Domain\ValueObject\Objective;
use Msstc4Symfony\SloBundle\Domain\ValueObject\PointId;
use Msstc4Symfony\SloBundle\Domain\ValueObject\SloName;
use Msstc4Symfony\SloBundle\Domain\ValueObject\Target;
use Msstc4Symfony\SloBundle\Domain\ValueObject\Window;
use Override;
use RuntimeException;

/**
 * Answers only for the operation point of the declared name, so a wrong point type or key finds nothing.
 */
final readonly class StubSloCatalog implements SloCatalogInterface
{
    public const DECLARED = 'payment.validation';

    /**
     * @param list<SloDefinition> $definitions
     * @param list<non-empty-string> $conflicted
     */
    public function __construct(
        private array $definitions = [],
        private array $conflicted = [],
        private bool $fail = false,
    ) {
    }

    public static function declaring(): self
    {
        return new self([new SloDefinition(
            SloName::fromString(self::DECLARED),
            PointType::Operation,
            [PointId::of(PointType::Operation, self::DECLARED)],
            [Objective::availability(Target::fromPercent(99.9))],
            Window::fromString('30d'),
            Classification::defaults(),
            StrategyPreference::Auto,
        )]);
    }

    #[Override]
    public function all(): array
    {
        return $this->definitions;
    }

    #[Override]
    public function get(SloName $name): ?SloDefinition
    {
        return $this->definitions[0] ?? null;
    }

    #[Override]
    public function forPoint(PointId $point): array
    {
        if ($this->fail) {
            throw new RuntimeException('catalog failed');
        }

        return $this->isDeclared($point) ? $this->definitions : [];
    }

    #[Override]
    public function ignored(): array
    {
        return [];
    }

    #[Override]
    public function conflictedNamesFor(PointId $point): array
    {
        return $this->isDeclared($point) ? $this->conflicted : [];
    }

    private function isDeclared(PointId $point): bool
    {
        return $point->equals(PointId::of(PointType::Operation, self::DECLARED));
    }
}
