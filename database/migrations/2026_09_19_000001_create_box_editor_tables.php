<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The owner builds each box by hand in the admin, Canva-style: artwork comes
 * in as separate transparent layers stacked around the customer's photo,
 * and captions are placed as text slots on top. This adds the layer table
 * and a shared library of fonts to pick those captions from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('image');
            $table->integer('x')->default(0);
            $table->integer('y')->default(0);
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->integer('rotation')->default(0);
            $table->unsignedTinyInteger('opacity')->default(100);
            // Whether the layer sits under or over the customer's photo; a
            // fade like the one on the Spotify box has to cover the photo.
            $table->string('placement', 10)->default('above');
            $table->boolean('locked')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('fonts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('family');
            // Null means the family is served by Google Fonts from the layout.
            $table->string('file')->nullable();
            $table->unsignedSmallInteger('weight')->default(400);
            $table->timestamps();
        });

        $now = now();
        DB::table('fonts')->insert(array_map(fn (array $f) => [
            'name' => $f[0], 'family' => $f[1], 'file' => $f[2], 'weight' => $f[3],
            'created_at' => $now, 'updated_at' => $now,
        ], self::BUILT_IN));
    }

    public function down(): void
    {
        Schema::dropIfExists('design_layers');
        Schema::dropIfExists('fonts');
    }

    /** The faces that already ship with the site: [name, family, file, weight]. */
    private const BUILT_IN = [
        ['SF Regular', 'SF Regular', 'fonts/sanfrancisco-regular.woff2', 400],
        ['SF Medium', 'SF Medium', 'fonts/sanfrancisco-medium.woff2', 400],
        ['SF Semibold', 'SF Semibold', 'fonts/sanfrancisco-semibold.woff2', 400],
        ['SF Black', 'SF Black', 'fonts/sanfrancisco-black.woff2', 400],
        ['Mark Pro', 'Mark Pro', 'fonts/markpro-regular.woff2', 400],
        ['Mark Pro Light', 'Mark Pro Light', 'fonts/markpro-light.woff2', 400],
        ['Mark Pro Book', 'Mark Pro Book', 'fonts/markpro-book.woff2', 400],
        ['Mark Pro Medium', 'Mark Pro Medium', 'fonts/markpro-medium.woff2', 400],
        ['Mark Pro Bold', 'Mark Pro Bold', 'fonts/markpro-bold.woff2', 400],
        ['Mark Pro Heavy', 'Mark Pro Heavy', 'fonts/markpro-heavy.woff2', 400],
        ['Mark Pro Black', 'Mark Pro Black', 'fonts/markpro-black.woff2', 400],
        ['Gilroy Medium', 'Gilroy Medium', 'fonts/gilroy-medium.woff2', 400],
        ['Bebas Neue', 'Bebas Neue', 'fonts/bebasneue-bold.woff2', 400],
        ['Bebas Neue Regular', 'Bebas Neue Regular', 'fonts/bebasneue-regular.woff2', 400],
        ['Nivea', 'Nivea', 'fonts/nivea-regular.woff2', 400],
        ['Nivea Bold', 'Nivea Bold', 'fonts/nivea-bold.woff2', 400],
        ['Palatino', 'Palatino Roman', 'fonts/palatino-roman.woff2', 400],
        ['Ultima Pro', 'Ultima Pro', 'fonts/ultimapro-bold.woff2', 400],
        ['Ultima Pro Italic', 'Ultima Pro Italic', 'fonts/ultimapro-black-italic.woff2', 400],
        ['Artful Beauty', 'Artful Beauty', 'fonts/artful-beauty.woff2', 400],
        ['Anabelle Script', 'Anabelle Script', 'fonts/anabelle-script.woff2', 400],
        ['Dharma Gothic', 'Dharma Gothic', 'fonts/dharmagothic-e-bold.woff2', 400],
        ['Argo Heavy', 'Argo Heavy', 'fonts/argo-heavy.woff2', 400],
        ['American Captain', 'American Captain', 'fonts/american-captain.woff2', 400],
        ['Sweetly Broken', 'Sweetly Broken', 'fonts/sweetly-broken.woff2', 400],
        ['Inter', 'Inter', null, 400],
        ['Inter SemiBold', 'Inter', null, 600],
        ['Inter Bold', 'Inter', null, 700],
        ['Montserrat Light', 'Montserrat', null, 300],
        ['Montserrat Medium', 'Montserrat', null, 500],
        ['Poppins SemiBold', 'Poppins', null, 600],
        ['Source Sans 3', 'Source Sans 3', null, 400],
        ['Source Sans 3 SemiBold', 'Source Sans 3', null, 600],
        ['Oswald Medium', 'Oswald', null, 500],
        ['Oswald Bold', 'Oswald', null, 700],
        ['Anton', 'Anton', null, 400],
        ['Archivo Black', 'Archivo Black', null, 400],
        ['Bungee', 'Bungee', null, 400],
        ['Bevan', 'Bevan', null, 400],
        ['Titan One', 'Titan One', null, 400],
        ['Luckiest Guy', 'Luckiest Guy', null, 400],
        ['Bangers', 'Bangers', null, 400],
        ['Fredoka', 'Fredoka', null, 600],
        ['Orbitron', 'Orbitron', null, 800],
        ['Creepster', 'Creepster', null, 400],
        ['Cinzel', 'Cinzel', null, 400],
        ['Cinzel Bold', 'Cinzel', null, 700],
        ['Playfair Display', 'Playfair Display', null, 600],
        ['Great Vibes', 'Great Vibes', null, 400],
        ['Sacramento', 'Sacramento', null, 400],
        ['Pacifico', 'Pacifico', null, 400],
        ['Caveat', 'Caveat', null, 600],
    ];
};
