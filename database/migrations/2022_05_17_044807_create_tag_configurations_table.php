<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTagConfigurationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tag_configurations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('parameter', 150);
            $table->string('tagname', 150);
            $table->string('datatype', 50)->default('float32');
            $table->string('document_type', 10)->default('coal');
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
        Schema::dropIfExists('tag_configurations');
    }
}
