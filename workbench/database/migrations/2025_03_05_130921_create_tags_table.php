<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->snowflake()->primary();
            $table->string('name');
            $table->snowflakeMorphs('taggable');
            $table->timestamps();
        });

        Schema::create('nullable_tags', function (Blueprint $table) {
            $table->snowflake()->primary();
            $table->string('name');
            $table->nullableSnowflakeMorphs('taggable');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
        Schema::dropIfExists('nullable_tags');
    }
};
