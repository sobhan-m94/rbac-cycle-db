<?php

declare(strict_types=1);

namespace Yiisoft\Rbac\Cycle;

use Cycle\Database\DatabaseInterface;
use Cycle\Database\Injection\Fragment;
use Cycle\Database\Table;
use Yiisoft\Rbac\Assignment;
use Yiisoft\Rbac\AssignmentsStorageInterface;

/**
 * Storage for RBAC assignments in the form of database table. Operations are performed using Cycle ORM.
 *
 * @psalm-type RawAssignment = array{
 *     itemName: string,
 *     userId: string,
 *     createdAt: int|string,
 * }
 */
final class AssignmentsStorage implements AssignmentsStorageInterface
{
    /**
     * @param string $tableName A name of the table for storing RBAC assignments.
     * @psalm-param non-empty-string $tableName
     *
     * @param DatabaseInterface $database Cycle database instance.
     */
    public function __construct(
        private string $tableName,
        private DatabaseInterface $database,
    ) {
    }

    public function getAll(): array
    {
        /** @psalm-var RawAssignment[] $rows */
        $rows = $this->database
            ->select()
            ->from($this->tableName)
            ->fetchAll();

        $assignments = [];
        foreach ($rows as $row) {
            $assignments[$row['userId']][$row['itemName']] = new Assignment(
                $row['userId'],
                $row['itemName'],
                (int) $row['createdAt'],
            );
        }

        return $assignments;
    }

    public function getByUserId(string $userId): array
    {
        /** @psalm-var RawAssignment[] $rows */
        $rows = $this->database
            ->select()
            ->from($this->tableName)
            ->where(['userId' => $userId])
            ->fetchAll();

        return array_combine(
            array_column($rows, 'itemName'),
            array_map(
                static fn(array $row) => new Assignment($userId, $row['itemName'], (int) $row['createdAt']),
                $rows
            )
        );
    }

    public function get(string $itemName, string $userId): ?Assignment
    {
        /** @psalm-var RawAssignment|false $row */
        $row = $this->database
            ->select()
            ->from($this->tableName)
            ->where(['itemName' => $itemName, 'userId' => $userId])
            ->run()
            ->fetch();

        return $row === false ? null : new Assignment($row['userId'], $row['itemName'], (int) $row['createdAt']);
    }

    public function add(string $itemName, string $userId): void
    {
        $this->database
            ->insert($this->tableName)
            ->values(
                [
                    'itemName' => $itemName,
                    'userId' => $userId,
                    'createdAt' => time(),
                ],
            )
            ->run();
    }

    public function hasItem(string $name): bool
    {
        /**
         * @var array<0, 1>|false $result
         * @infection-ignore-all
         * - ArrayItemRemoval, select.
         * - IncrementInteger, limit.
         */
        $result = $this
            ->database
            ->select([new Fragment('1')])
            ->from($this->tableName)
            ->where(['itemName' => $name])
            ->limit(1)
            ->run()
            ->fetch();

        return $result !== false;
    }

    public function renameItem(string $oldName, string $newName): void
    {
        if ($oldName === $newName) {
            return;
        }
        $this->database
            ->update($this->tableName, ['itemName' => $newName], ['itemName' => $oldName])
            ->run();
    }

    public function remove(string $itemName, string $userId): void
    {
        $this->database
            ->delete($this->tableName, ['itemName' => $itemName, 'userId' => $userId])
            ->run();
    }

    public function removeByUserId(string $userId): void
    {
        $this->database
            ->delete($this->tableName, ['userId' => $userId])
            ->run();
    }

    public function removeByItemName(string $itemName): void
    {
        $this->database
            ->delete($this->tableName, ['itemName' => $itemName])
            ->run();
    }

    public function clear(): void
    {
        /** @var Table $table */
        $table = $this->database->table($this->tableName);
        $table->eraseData();
    }
}
