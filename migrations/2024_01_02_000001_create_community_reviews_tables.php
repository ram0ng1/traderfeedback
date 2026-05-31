<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/**
 * Cria as tabelas do subsistema Community Reviews (reviews de produtos),
 * migrado do plugin MyBB homônimo. IDs do MyBB são preservados pela migração
 * de dados (mybb:reviews), então as PKs não são auto-incrementadas a partir do
 * zero — usamos `unsignedInteger('id')->primary()` para aceitar os ids de origem.
 *
 * Famílias:
 *   tfb_review_categories  → categorias (Brushes, Soaps…)
 *   tfb_review_fields      → campos de nota por categoria (Density, Lather…)
 *   tfb_products           → produtos
 *   tfb_product_reviews    → reviews (preço, url, comentário, nota média)
 *   tfb_review_field_ratings → nota (1–5) por campo dentro de um review
 *   tfb_review_photos      → fotos do review
 *   tfb_review_comments    → comentários nos reviews
 */
return [
    'up' => function (Builder $schema) {
        if (! $schema->hasTable('tfb_review_categories')) {
            $schema->create('tfb_review_categories', function (Blueprint $table) {
                $table->unsignedInteger('id')->primary();
                $table->string('name', 255);
                $table->integer('position')->default(0);
            });
        }

        if (! $schema->hasTable('tfb_review_fields')) {
            $schema->create('tfb_review_fields', function (Blueprint $table) {
                $table->unsignedInteger('id')->primary();
                $table->unsignedInteger('category_id');
                $table->string('name', 255);
                $table->integer('position')->default(0);

                $table->foreign('category_id')->references('id')->on('tfb_review_categories')->cascadeOnDelete();
                $table->index('category_id');
            });
        }

        if (! $schema->hasTable('tfb_products')) {
            $schema->create('tfb_products', function (Blueprint $table) {
                $table->unsignedInteger('id')->primary();
                $table->unsignedInteger('category_id');
                $table->string('name', 255);
                $table->unsignedInteger('user_id')->nullable();
                $table->unsignedInteger('views')->default(0);
                $table->decimal('cached_rating', 3, 2)->default(0);
                $table->unsignedInteger('review_count')->default(0);
                $table->timestamps();

                $table->foreign('category_id')->references('id')->on('tfb_review_categories')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                $table->index('category_id');
                $table->index('cached_rating');
                $table->index('created_at');
            });
        }

        if (! $schema->hasTable('tfb_product_reviews')) {
            $schema->create('tfb_product_reviews', function (Blueprint $table) {
                $table->unsignedInteger('id')->primary();
                $table->unsignedInteger('product_id');
                $table->unsignedInteger('user_id')->nullable();
                $table->unsignedInteger('merchant_user_id')->nullable();
                $table->string('price', 30)->nullable();
                $table->string('url', 255)->nullable();
                $table->longText('comment');
                $table->decimal('rating', 3, 2)->default(0);
                $table->timestamps();

                $table->foreign('product_id')->references('id')->on('tfb_products')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                $table->foreign('merchant_user_id')->references('id')->on('users')->nullOnDelete();
                $table->index('product_id');
                $table->index('user_id');
                $table->index('created_at');
            });
        }

        if (! $schema->hasTable('tfb_review_field_ratings')) {
            $schema->create('tfb_review_field_ratings', function (Blueprint $table) {
                $table->unsignedInteger('id')->primary();
                $table->unsignedInteger('review_id');
                $table->unsignedInteger('field_id');
                $table->unsignedTinyInteger('rating')->default(0);
                $table->text('comment')->nullable();

                $table->foreign('review_id')->references('id')->on('tfb_product_reviews')->cascadeOnDelete();
                $table->index('review_id');
                $table->index('field_id');
            });
        }

        if (! $schema->hasTable('tfb_review_photos')) {
            $schema->create('tfb_review_photos', function (Blueprint $table) {
                $table->unsignedInteger('id')->primary();
                $table->unsignedInteger('review_id');
                $table->text('url');
                $table->text('thumbnail_url')->nullable();
                $table->integer('position')->default(0);

                $table->foreign('review_id')->references('id')->on('tfb_product_reviews')->cascadeOnDelete();
                $table->index('review_id');
            });
        }

        if (! $schema->hasTable('tfb_review_comments')) {
            $schema->create('tfb_review_comments', function (Blueprint $table) {
                $table->unsignedInteger('id')->primary();
                $table->unsignedInteger('product_id');
                $table->unsignedInteger('review_id')->nullable();
                $table->unsignedInteger('user_id')->nullable();
                $table->text('comment');
                $table->timestamps();

                $table->foreign('product_id')->references('id')->on('tfb_products')->cascadeOnDelete();
                $table->foreign('review_id')->references('id')->on('tfb_product_reviews')->nullOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                $table->index('product_id');
                $table->index('review_id');
                $table->index('created_at');
            });
        }
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('tfb_review_comments');
        $schema->dropIfExists('tfb_review_photos');
        $schema->dropIfExists('tfb_review_field_ratings');
        $schema->dropIfExists('tfb_product_reviews');
        $schema->dropIfExists('tfb_products');
        $schema->dropIfExists('tfb_review_fields');
        $schema->dropIfExists('tfb_review_categories');
    },
];
