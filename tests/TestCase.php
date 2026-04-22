<?php

declare(strict_types=1);

namespace Tests;

use Dotenv\Repository\RepositoryBuilder;
use Mockery as m;
use PhpOption\Option;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(RepositoryBuilder::class)) {
            eval('namespace Dotenv\\Repository;
            class RepositoryBuilder {
                public static function createWithDefaultAdapters() {
                    return new static();
                }
                public function addAdapter($adapter) {
                    return $this;
                }
                public function immutable() {
                    return $this;
                }
                public function make() {
                    return new class {
                        public function get($key) { return null; }
                    };
                }
            }');
        }

        if (! class_exists(Option::class)) {
            eval('namespace PhpOption; class Option { 
                public static function fromValue($value) { 
                    return new class($value) { 
                        private $value; 
                        public function __construct($value) { 
                            $this->value = $value; 
                        } 
                        public function map($callback) { 
                            return $this; 
                        }
                        public function getOrCall($callback) {
                            return $this->value ?? $callback();
                        }
                    }; 
                } 
            }');
        }
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
