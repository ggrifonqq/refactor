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
    //удалил сеттер и перенес $userIds  в конструктор,дабы не нарушалась имутабельность 
    public function __construct(array $userIds)
    {
        if (empty($userIds)) {
            throw new InvalidArgumentException('User IDs cannot be empty.');
        }

        $this->userIds = $userIds;
    }

    /**
     * @psalm-return non-empty-list<string>
     */
    public function getUserIds(): array
    {
        return $this->userIds;
    }
}
//Добавил инерфейсы для гибкости и по SOLID'у 😃
interface DatabaseTransactionInterface
{
    public function beginTransaction(string $isolationLevel): void;
    public function commit(): void;
    public function rollback(): void;
}

interface DatabaseQueryInterface
{
    public function prepare(string $query): PDOStatement;
}

interface DatabaseInterface extends DatabaseTransactionInterface, DatabaseQueryInterface
{
}

final class GetUsersHandler
{
    protected DatabaseInterface $database;
    
    public function __construct(DatabaseInterface $database)
    {
        $this->database = $database;
    }

    /**
     * @psalm-return Generator<array{string, string}>
     */
    final public function __invoke(GetUsers $query): Generator
    {
        //рефактор кода для удобства восприятия , вызываем функцию 
        yield from $this->transactionally(
            function () use ($query): Generator {
                yield from $this->fetchUsers($query);
            }
        );
    }

    private function fetchUsers(GetUsers $query): Generator
    {
        $ids = implode(', ', array_fill(0, count($query->getUserIds()), '?'));
        $statement = $this->database->prepare('SELECT id, firstname FROM users WHERE id IN (' . $ids . ')');
        $statement->execute($query->getUserIds());
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            yield $row;
        }
    }

    /**
     * @psalm-param callable(): Generator $operation
     */
    private function transactionally(callable $operation): Generator
    {
        $this->database->beginTransaction('SERIALIZABLE');
        //Перенес коммит , в трай , для правильной отработки транзакции 
        try {
            $result = $operation();
            $this->database->commit();
        } catch (Throwable $exception) {
            $this->database->rollback();
            throw $exception; // повторное выбрасывание исключения после отката
        }

        return $result;
    }
}
