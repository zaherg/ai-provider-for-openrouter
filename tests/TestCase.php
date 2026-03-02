<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use ReflectionClass;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        // Reset AbstractProvider static caches to ensure test isolation.
        $abstractProviderClass = 'WordPress\AiClient\Providers\AbstractProvider';
        if (class_exists($abstractProviderClass)) {
            $reflection = new ReflectionClass($abstractProviderClass);
            foreach (['metadataCache', 'availabilityCache', 'modelMetadataDirectoryCache'] as $property) {
                if ($reflection->hasProperty($property)) {
                    $prop = $reflection->getProperty($property);
                    $prop->setAccessible(true);
                    $prop->setValue(null, []);
                }
            }
        }
    }
}
