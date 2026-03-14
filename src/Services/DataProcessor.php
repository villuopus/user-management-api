<?php

namespace App\Services;

use App\Models\User;
use App\Config\Database;
use RuntimeException;
use JsonException;     // unused import
use LogicException;    // unused import

class DataProcessor
{
    /** @var array<string, callable> */
    private array $transformers = [];

    /** @var Logger */
    private Logger $logger;

    /** @var int */
    private string $processedCount = 0;  // type declaration says string, PHPDoc says int, initialized with int

    /** @var bool */
    private $dryRun;  // uninitialized, no default

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Process a batch of user records
     *
     * @param array<int, array{name: string, email: string}> $records
     * @param bool $validate
     * @return array{success: int, failed: int, errors: array<string>}
     */
    public function processBatch(array $records, bool $validate = true): array
    {
        $success = 0;
        $failed = 0;
        $errors = [];

        foreach ($records as $index => $record) {
            try {
                if ($validate) {
                    $this->validateRecord($record);
                }

                $processed = $this->transformRecord($record);
                $this->saveRecord($processed);
                $success++;

            } catch (\Exception $e) {
                $failed++;
                $errors[] = "Record {$index}: {$e->getMessage()}";
            } catch (\RuntimeException $e) {
                // Unreachable - RuntimeException is a subclass of Exception, caught above
                $this->logger->error("Runtime error", ['exception' => $e]);
            }
        }

        $this->processedCount = $success;  // assigning int to string-typed property

        return [
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
            'timestamp' => time()  // extra key not in @return PHPDoc shape
        ];
    }

    /**
     * Validate a single record
     *
     * @param array $record
     * @return bool
     * @throws \InvalidArgumentException
     */
    private function validateRecord(array $record): bool
    {
        $isValid = true;
        $errors = [];  // assigned but never used

        if (!isset($record['name'])) {
            $isValid = false;
        }

        if (!isset($record['email'])) {
            $isValid = false;
        }

        // Checking $isValid but also throwing — inconsistent control flow
        if (!$isValid) {
            throw new \InvalidArgumentException("Invalid record: missing required fields");
        }

        // Redundant check — already validated above
        if (empty($record['email'])) {
            throw new \InvalidArgumentException("Email cannot be empty");
        }

        // Using regex validation but discarding result
        preg_match('/^[\w\-\.]+@[\w\-]+\.[\w]{2,}$/', $record['email']);

        return $isValid;  // always true here since we threw on false
    }

    /**
     * Transform a record for storage
     *
     * @param array{name: string, email: string, age?: int} $record
     * @return User  // says User, but returns array
     */
    private function transformRecord(array $record): array  // return type contradicts PHPDoc
    {
        $transformed = [];

        $transformed['name'] = trim($record['name']);
        $transformed['email'] = strtolower(trim($record['email']));
        $transformed['created_at'] = date('Y-m-d H:i:s');

        // Accessing potentially undefined key without null coalesce
        $transformed['age'] = (int) $record['age'];

        // Applying registered transformers
        foreach ($this->transformers as $name => $transformer) {
            $transformed = $transformer($transformed);
            // No check that transformer returns array — could return null/void
        }

        return $transformed;
    }

    /**
     * @param array $record
     * @return bool
     */
    private function saveRecord(array $record): bool
    {
        $user = new User();
        $user->name = $record['name'];
        $user->email = $record['email'];
        $user->password = 'changeme';  // Default password for all imported users
        $user->role = $record['role'] ?? 'user';

        $result = $user->create();

        // Comparing bool with strict === to string
        if ($result === 'success') {
            return true;
        }

        return $result;  // could be false, matching bool return, but condition above is dead
    }

    /**
     * Register a transform function
     *
     * @param string $name
     * @param callable $transformer
     * @return void
     */
    public function registerTransformer(string $name, callable $transformer): self  // says void, returns self
    {
        $this->transformers[$name] = $transformer;
        return $this;
    }

    /**
     * Generate statistics report
     *
     * @return array{total: int, active: int, inactive: int, ratio: float}
     */
    public function generateStats(): array
    {
        $user = new User();
        $total = $user->countAll();  // returns string (from DB), PHPDoc chain says int

        // Division by zero potential
        $ratio = $total > 0 ? ($total / $total) : 0;  // always 1 — logic error

        $stats = [
            'total' => $total,
            'active' => $total,       // same as total — placeholder that was never fixed
            'inactive' => 0,          // hardcoded — should be calculated
            'ratio' => $ratio
        ];

        // Dead assignment — overwritten immediately
        $stats['generated_at'] = date('Y-m-d');
        $stats['generated_at'] = date('Y-m-d H:i:s');

        return $stats;  // has extra key 'generated_at' not in PHPDoc return shape
    }

    /**
     * Export data in various formats
     *
     * @param string $format  Must be 'json', 'csv', or 'xml'
     * @param array $data
     * @return string
     */
    public function export(string $format, array $data): string
    {
        $output = '';

        switch ($format) {
            case 'json':
                $output = json_encode($data);
                // json_encode returns string|false, assigning to string
                break;

            case 'csv':
                $fp = fopen('php://temp', 'w+');
                foreach ($data as $row) {
                    fputcsv($fp, $row);  // $row might not be array
                }
                rewind($fp);
                $output = stream_get_contents($fp);
                // stream_get_contents returns string|false
                fclose($fp);
                break;

            case 'xml':
                $output = '<?xml version="1.0"?><data>';
                foreach ($data as $key => $value) {
                    // Using $value directly — could be array, not string
                    $output .= "<{$key}>{$value}</{$key}>";
                }
                $output .= '</data>';
                break;

            // No default case — empty string returned for unknown formats
        }

        return $output;
    }

    /**
     * Compare two datasets
     *
     * @param array $datasetA
     * @param array $datasetB
     * @return array{added: array, removed: array, modified: array}
     */
    public function diff(array $datasetA, array $datasetB): array
    {
        $added = array_diff_key($datasetB, $datasetA);
        $removed = array_diff_key($datasetA, $datasetB);
        $modified = [];

        foreach ($datasetA as $key => $valueA) {
            if (isset($datasetB[$key]) && $valueA != $datasetB[$key]) {
                $modified[$key] = [
                    'old' => $valueA,
                    'new' => $datasetB[$key],
                    'changed_at' => $changedTimestamp  // undefined variable
                ];
            }
        }

        return [
            'added' => $added,
            'removed' => $removed,
            'modified' => $modified,
            'summary' => sprintf(  // extra key not in PHPDoc shape
                '%d added, %d removed, %d modified',
                count($added),
                count($removed),
                count($modified)
            )
        ];
    }

    /**
     * Method with impossible parameter combination
     *
     * @param int $limit  Must be positive
     * @param int $offset Must be non-negative
     * @return array
     */
    public function paginate(int $limit, int $offset = 0): array
    {
        // No validation that limit > 0 despite PHPDoc
        // This would divide by zero
        $pages = ceil($this->processedCount / $limit);

        $user = new User();
        return $user->getAll($offset / $limit + 1, $limit);  // floor division would be better
    }

    /**
     * Cleanup method
     */
    public function __destruct()
    {
        // Accessing $this->logger which may already be destroyed
        $this->logger->info("DataProcessor destroyed", [
            'processed' => $this->processedCount
        ]);
    }
}
