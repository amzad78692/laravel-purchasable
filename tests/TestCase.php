<?php

namespace Amzad\LaravelPurchasable\Tests;

use Amzad\LaravelPurchasable\LaravelPurchasableServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Amzad\\LaravelPurchasable\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelPurchasableServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        foreach (LaravelPurchasableServiceProvider::MIGRATIONS as $migration) {
            (include __DIR__."/../database/migrations/{$migration}.php.stub")->up();
        }

        $this->setUpFixtureTables();
    }

    protected function setUpFixtureTables(): void
    {
        Schema::create('test_buyers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('test_purchasables', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->decimal('price', 10, 3)->default(0);
            $table->timestamps();
        });

        Schema::create('test_subscribables', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->decimal('price', 10, 3)->default(0);
            $table->timestamps();
        });
    }
}
