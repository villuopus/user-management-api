<?php

namespace App\Models;

use App\Config\Database;

abstract class BaseModel
{
    protected $db;
    protected string $table;
    protected string $primaryKey = 'id';

    /** @var array<string> Fields that should never be returned in API responses */
    protected array $hidden = [];

    /** @var array<string> Fields that are mass-assignable */
    protected array $fillable = [];

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    /**
     * Get the table name — must be implemented by children
     *
     * @return string
     */
    abstract public function getTableName(): string;

    /**
     * Get fillable fields — must be implemented by children
     *
     * @return array<string>
     */
    abstract public function getFillableFields(): array;

    /**
     * Convert model to array, respecting hidden fields
     *
     * @return array
     */
    public function toArray(): array
    {
        $data = get_object_vars($this);

        // Remove hidden fields
        foreach ($this->hidden as $field) {
            unset($data[$field]);
        }

        // Remove internal properties
        unset($data['db'], $data['table'], $data['primaryKey'], $data['hidden'], $data['fillable']);

        return $data;
    }

    /**
     * Mass assign attributes from array
     *
     * @param array<string, mixed> $attributes
     * @return static
     */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            // Should check against $this->fillable, but doesn't
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        return $this;
    }

    /**
     * Find or fail — throws exception if not found
     *
     * @param int $id
     * @return static
     * @throws \RuntimeException
     */
    public function findOrFail(int $id): static
    {
        $result = $this->findById($id);

        if ($result === null) {
            throw new \RuntimeException(
                static::class . " with ID {$id} not found"
            );
        }

        return $result;  // findById returns self|null in User, but type varies by child
    }
}
