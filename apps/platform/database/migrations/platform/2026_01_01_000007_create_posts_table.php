<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('posts')) {
            Schema::create('posts', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->text('excerpt')->nullable();
                $table->longText('body');
                $table->string('status')->default('draft');
                // Nullable + nullOnDelete: deleting a category must not fail while posts reference it (AC-9).
                $table->foreignId('author_id')->constrained('authors')->restrictOnDelete();
                $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
                $table->foreignId('featured_image_id')->nullable()->constrained('media')->nullOnDelete();
                $table->timestamp('published_at')->nullable();
                $table->string('seo_title')->nullable();
                $table->string('seo_description')->nullable();
                $table->timestamps();

                $table->index(['status', 'published_at']);
                $table->index(['category_id', 'status']);
                $table->fullText(['title', 'excerpt', 'body']);
            });
        }

        if (! Schema::hasTable('post_tag')) {
            Schema::create('post_tag', function (Blueprint $table) {
                $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
                $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
                $table->primary(['post_id', 'tag_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('post_tag');
        Schema::dropIfExists('posts');
    }
};
