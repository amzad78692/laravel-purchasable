<?php

use Amzad\LaravelPurchasable\Tests\FilamentTestCase;
use Amzad\LaravelPurchasable\Tests\TestCase;

uses(FilamentTestCase::class)->in(__DIR__.'/Filament');
uses(TestCase::class)->in(__DIR__.'/Feature', __DIR__.'/Unit');
