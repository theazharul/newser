<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('media')) {
            Schema::create('media', function (Blueprint $table) {
                $table->id();
                $table->string('file_name');
                $table->string('file_path');
                $table->string('mime_type');
                $table->unsignedBigInteger('size');
                $table->unsignedInteger('width')->nullable();
                $table->unsignedInteger('height')->nullable();
                $table->string('alt_text')->nullable();
                $table->text('caption')->nullable();
                $table->string('credit')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('media_variants')) {
            Schema::create('media_variants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
                $table->string('variant_name');
                $table->string('file_path');
                $table->unsignedInteger('width');
                $table->unsignedInteger('height');
                $table->unsignedBigInteger('size');
                $table->timestamps();

                $table->unique(['media_id', 'variant_name']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('media_variants');
        Schema::dropIfExists('media');
    }
};
