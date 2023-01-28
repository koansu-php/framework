<?php
/**
 *  * Created by mtils on 06.11.2021 at 07:05.
 **/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder as Schema;

return new class() extends Migration
{
    public function up(Schema $schema) : void
    {
        $schema->create('nodes', function(Blueprint $table) {

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

    public function down(Schema $schema) : void
    {
        $schema->dropIfExists('nodes');
    }

};