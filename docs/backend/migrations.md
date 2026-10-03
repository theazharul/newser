# Migration Strategy

**Source:** §33 (plan.txt)
**Gap:** GAP-007

---

## Core Rules

1. **Never modify old migrations after release**
2. **Always create a new migration**
3. **Platform migrations are append-only and idempotent**
4. **Platform migrations run before client migrations**

---

## Migration Directory Structure

```
database/migrations/
├── platform/                  # Platform-controlled (append-only)
│   ├── 2026_01_01_000001_create_users_table.php
│   ├── 2026_01_01_000002_create_posts_table.php
│   ├── 2026_01_01_000003_create_categories_table.php
│   ├── 2026_01_01_000004_create_media_table.php
│   ├── 2026_01_01_000005_create_projects_table.php
│   ├── 2026_01_01_000006_create_licenses_table.php
│   └── ...
│
└── client/                  # Client-controlled (custom)
    ├── 2026_02_01_000001_create_custom_table.php
    └── ...
```

---

## Migration Ordering

Laravel runs migrations in alphabetical order by filename. To ensure platform migrations run first:

1. Platform migrations use `platform/` subdirectory
2. Client migrations use `client/` subdirectory
3. Custom migration runner: platform first, then client

```php
// app/Console/Commands/MigratePlatform.php
public function handle()
{
    $this->call('migrate', ['--path' => 'database/migrations/platform']);
    $this->call('migrate', ['--path' => 'database/migrations/client']);
}
```

---

## Platform Migration Convention

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Use Schema::hasTable() and Schema::hasColumn() for idempotency
        if (!Schema::hasTable('posts')) {
            Schema::create('posts', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->text('excerpt')->nullable();
                $table->longText('body');
                $table->string('status')->default('draft');
                $table->foreignId('author_id')->constrained('authors');
                $table->foreignId('category_id')->constrained('categories');
                $table->foreignId('featured_image_id')->nullable()->constrained('media');
                $table->timestamp('published_at')->nullable();
                $table->string('seo_title')->nullable();
                $table->string('seo_description')->nullable();
                $table->timestamps();

                // Indexes
                $table->index(['status', 'published_at']);
                $table->index(['category_id', 'status']);
                $table->fullText(['title', 'excerpt', 'body']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
```

---

## Client Migration Convention

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Client migrations can modify client-owned tables only
        // Never modify platform tables directly
        // Use Schema::table() with hasColumn() guards

        if (Schema::hasTable('posts') && !Schema::hasColumn('posts', 'custom_field')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->string('custom_field')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('posts') && Schema::hasColumn('posts', 'custom_field')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->dropColumn('custom_field');
            });
        }
    }
};
```

---

## Migration Safety Rules

| Rule | Rationale |
|------|-----------|
| Never modify released migrations | Breaks existing installations |
| Always use `hasTable()` / `hasColumn()` | Idempotent migrations |
| Never drop platform tables in client migrations | Data loss |
| Never modify platform columns in client migrations | Conflicts with platform updates |
| Use `Schema::table()` for alterations | Safer than `Schema::create()` |
| Test migrations on a copy of production data | Catch issues before deployment |

---

## Migration Testing

```php
// tests/unit/Migrations/PostMigrationTest.php
public function test_posts_table_has_required_columns(): void
{
    $this->artisan('migrate', ['--path' => 'database/migrations/platform']);

    $this->assertTrue(Schema::hasTable('posts'));
    $this->assertTrue(Schema::hasColumn('posts', 'title'));
    $this->assertTrue(Schema::hasColumn('posts', 'slug'));
    $this->assertTrue(Schema::hasColumn('posts', 'status'));
    $this->assertTrue(Schema::hasColumn('posts', 'published_at'));
}

public function test_posts_table_has_indexes(): void
{
    $this->artisan('migrate', ['--path' => 'database/migrations/platform']);

    $indexes = Schema::getIndexes('posts');
    $indexNames = array_column($indexes, 'name');

    $this->assertContains('posts_status_published_at_index', $indexNames);
    $this->assertContains('posts_category_id_status_index', $indexNames);
}
```

---

## Related Documents

- [Backend Overview](./overview.md)
- [Entities](./entities.md)
- [API](./api.md)
- [Gap Analysis](../gap-analysis.md) — GAP-007
