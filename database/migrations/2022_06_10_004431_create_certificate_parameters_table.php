<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCertificateParametersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('certificate_parameters', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('certificate_id');
            $table->string('parameter', 50);
            $table->string('tagname', 50);
            $table->dateTime('timestamp');
            $table->string('value', 50);
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
        Schema::dropIfExists('certificate_parameters');
    }
}
