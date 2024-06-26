<?php

declare(strict_types=1);

/**
 * @psalm-immutable
 */
class GetUsers
{
    /**
     * @psalm-var non-empty-list<string>
     */
    private array $userIds;

    /**
     * @psalm-return non-empty-list<string>
     */
    public function getUserIds(): array
    {
        return $this->userIds;
    }

    /**
     * @psalm-param array<string> $userIds
     */
    public function setUserIds(array $userIds): self
    {
        $this->userIds = $userIds;

        return $this;
    }
}

final class GetUsersHandler
{
    protected Database $database;

    /**
     * @param DatabaseInterface $database Some PDO wrapper
     */
    public function __construct(DatabaseInterface $database)
    {
        $this->database = $database;
    }

    /**
     * @psalm-return Generator<array{string, string}>
     */
    final public function __invoke(GetUsers $query): Generator
    {
        yield from $this->transactionally(
            static function (): iterable {
                yield from $this
                    ->database
                    ->query('select id, firstname from users where id in (:ids)', [
                        'ids' => implode(', ', $query->getUserIds()),
                    ])
                    ->result()
                ;
            }
        );
    }

    /**
     * @psalm-param callable(): Generator $operation
     */
    private function transactionally(callable $operation): Generator
    {
        $this->database->beginTransaction('SERIALIZABLE');

        try {
            $result = $operation();
        } catch (Throwable $exception) {
            $this->database->rollback();
        }

        $this->database->commit();

        return $result;
    }
}
