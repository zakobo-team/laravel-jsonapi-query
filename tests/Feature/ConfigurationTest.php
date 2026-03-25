<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Zakobo\JsonApiQuery\Tests\TestCase;

class ConfigurationTest extends TestCase
{
    #[Test]
    public function it_provides_a_publishable_configuration_stub(): void
    {
        $path = dirname(__DIR__, 2).'/config/jsonapi-query.php';

        $this->assertFileExists($path);

        $config = require $path;

        $this->assertIsArray($config);
        $this->assertArrayHasKey('pagination', $config);
        $this->assertSame([
            'default_size' => 30,
            'max_size' => 100,
        ], $config['pagination']);
    }
}
