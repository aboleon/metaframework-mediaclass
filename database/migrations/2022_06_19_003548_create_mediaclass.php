<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mediaclass', function (Blueprint $table) {
            $table->id();
            $table->string('model_type',255)->index();
            $table->unsignedBigInteger('model_id')->index()->nullable();
            $table->string('group')->index()->default('media');
            $table->string('subgroup')->nullable();
            $table->json('description')->nullable();
            $table->enum('position', ['left','right','up','down'])->default('up')->index();
            $table->string('original_filename');
            $table->string('filename',6);
            $table->string('mime');
            $table->string('temp')->nullable()->index();
            // Additional stored params
            $table->json('storable')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mediaclass');
    }
};
