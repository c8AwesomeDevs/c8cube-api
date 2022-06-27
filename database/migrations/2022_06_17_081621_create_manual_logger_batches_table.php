<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateManualLoggerBatchesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('manual_logger_batches', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('uploaded_by');
            $table->string('name');
            $table->string('description', 255)->nullable();
            $table->text('data')->nullable();
            $table->boolean('validated')->default(false);
            $table->boolean('written')->default(false);
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
        Schema::dropIfExists('manual_logger_batches');
    }
}
