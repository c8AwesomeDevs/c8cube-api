<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCertificatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->string('certificate_type', 10);
            $table->string('certificate_number', 100)->nullable();
            $table->date('certificate_date')->nullable();
            $table->string('vessel_name', 50)->nullable();
            $table->string('filename', 50);
            $table->string('status', 10)->default('queued');
            $table->boolean('validated')->default(0);
            $table->integer('validated_by')->nullable();
            $table->dateTime('validated_at')->nullable();
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
        Schema::dropIfExists('certificates');
    }
}
