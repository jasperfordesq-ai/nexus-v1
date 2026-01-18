<?php

declare(strict_types=1);

namespace Nexus\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base Test Case
 *
 * All test classes should extend this class to get access to
 * common testing utilities and setup.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Tear down the test environment.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Assert that an array has specific keys.
     */
    protected function assertArrayHasKeys(array $keys, array $array, string $message = ''): void
    {
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $array, $message ?: "Array does not have key: {$key}");
        }
    }

    /**
     * Assert that a string contains all given substrings.
     */
    protected function assertStringContainsStrings(array $needles, string $haystack, string $message = ''): void
    {
        foreach ($needles as $needle) {
            $this->assertStringContainsString($needle, $haystack, $message ?: "String does not contain: {$needle}");
        }
    }

    /**
     * Create a mock with method expectations.
     */
    protected function createMockWithMethods(string $class, array $methods = []): object
    {
        $mock = $this->createMock($class);

        foreach ($methods as $method => $return) {
            $mock->method($method)->willReturn($return);
        }

        return $mock;
    }

    /**
     * Get a private or protected property value.
     */
    protected function getPrivateProperty(object $object, string $property): mixed
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);

        return $property->getValue($object);
    }

    /**
     * Set a private or protected property value.
     */
    protected function setPrivateProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    /**
     * Call a private or protected method.
     */
    protected function callPrivateMethod(object $object, string $method, array $args = []): mixed
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }
}
