<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The blog: /bloq, /ru/blog, /en/blog.
 *
 * Built the way the gift pages are, because the same two things decide the
 * shape — an article belongs to one language, and its twins in the other two
 * hang off the Azerbaijani row through `alt_of`, which is what hreflang is
 * made of. What a gift page has and an article does not is a list of every
 * design; what an article has and a gift page does not is a date, a cover
 * and a reading time.
 *
 * `published_at` is never null for a row that shows. A null date would sort
 * to the top on MySQL and to the bottom on SQLite — the shop runs on one and
 * is tested on the other — so an article with no date is simply not
 * published, and the admin fills today's date in for a new one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('locale', 2)->default('az')->index();
            // A self-reference: the Azerbaijani article a translation belongs to.
            $table->foreignId('alt_of')->nullable()->constrained('posts')->nullOnDelete();
            $table->string('topic', 60)->nullable();
            $table->string('emoji', 16)->nullable();
            $table->string('title');
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 300)->nullable();
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('cover')->nullable();
            $table->json('faq')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // The designs an article points at. Empty means it points at none:
        // an article about choosing a photograph has no business listing the
        // whole catalogue, which is what a gift page does.
        Schema::create('post_product', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['post_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_product');
        Schema::dropIfExists('posts');
    }
};
