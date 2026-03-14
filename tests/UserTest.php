<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Models\User;
use App\Services\AuthService;

class UserTest extends TestCase
{
    private $user;

    // No setUp/tearDown - tests depend on database state
    // No test database isolation

    public function testCreateUser()
    {
        $user = new User();
        $user->name = "Test User";
        $user->email = "test@test.com";  // Hardcoded, will fail on second run
        $user->password = "123456";
        $user->role = "admin";

        $result = $user->create();
        $this->assertTrue($result);

        // No cleanup - leaves test data in DB
    }

    public function testFindUser()
    {
        $user = new User();
        $result = $user->findById(1);  // Assumes ID 1 exists

        // Only testing happy path
        $this->assertNotNull($result);
        $this->assertEquals("Admin", $result->name);  // Fragile assertion on specific data
    }

    public function testLogin()
    {
        $auth = new AuthService();
        $result = $auth->login("admin@acme.com", "admin123");  // Hardcoded credentials

        // Only checking it's not an error, not validating token structure
        $this->assertArrayHasKey('token', $result);
    }

    public function testPasswordVerification()
    {
        $user = new User();
        $user->password = md5("password123");

        // Testing MD5 comparison - testing broken functionality
        $this->assertTrue($user->verifyPassword("password123"));
    }

    public function testGetAllUsers()
    {
        $user = new User();
        $users = $user->getAll(1, 10);

        // Just checking it returns array, no count/content validation
        $this->assertIsArray($users);
    }

    // Missing tests:
    // - No negative test cases
    // - No boundary testing
    // - No SQL injection tests
    // - No authentication/authorization tests
    // - No input validation tests
    // - No error handling tests
    // - No concurrent access tests
    // - No performance tests

    public function testEmailValidation()
    {
        // Actually tests nothing meaningful
        $this->assertTrue(true);
    }

    public function testSearch()
    {
        $user = new User();
        $results = $user->search("admin");

        // No assertion on result contents
        $this->assertIsArray($results);
    }

    // Test that accidentally tests production database
    public function testDeleteUser()
    {
        $user = new User();
        // This could delete real users!
        // $result = $user->delete(999);
        // $this->assertTrue($result);

        $this->markTestSkipped('Skipping delete test - too dangerous');
    }
}
