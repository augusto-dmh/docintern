<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('processing.ocr_provider', 'simulated');
        config()->set('processing.classification_provider', 'simulated');
    }
}
