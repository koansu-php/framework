<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNodesTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     * @noinspection PhpPossiblePolymorphicInvocationInspection
     */
    public function up()
    {
        Schema::create('nodes', function(Blueprint $table) {

            $table->increments('id');


            $table->string('name')->nullable();
            $table->string('title')->nullable();
            $table->string('path')->nullable();
            $table->string('parent_id')->nullable();

            // We'll need to ensure that MySQL uses the InnoDB engine to
            // support the indexes, other engines aren't affected.
            $table->engine = 'InnoDB';

        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('nodes');
    }

}

