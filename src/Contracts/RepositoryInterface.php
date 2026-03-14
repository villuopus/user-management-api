<?php

namespace App\Contracts;

interface RepositoryInterface
{
    /**
     * Find a record by its primary key
     *
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array;

    /**
     * Get all records with pagination
     *
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getAll(int $page, int $limit): array;

    /**
     * Create a new record
     *
     * @param array $attributes
     * @return int  The new record ID
     */
    public function create(array $attributes): int;

    /**
     * Update a record by ID
     *
     * @param int $id
     * @param array $attributes
     * @return bool
     */
    public function update(int $id, array $attributes): bool;

    /**
     * Delete a record by ID
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Count all records
     *
     * @return int
     */
    public function countAll(): int;
}
