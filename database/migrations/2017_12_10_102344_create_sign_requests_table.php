<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSignRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sign_requests', function (Blueprint $table) {
            $table->increments('id');
            $table->string('udid')->nullable();
            $table->string('icon')->nullable();
            $table->string('bid')->nullable();
            $table->string('name')->nullable();
            $table->string('aid')->nullable();
            $table->string('cert')->nullable();
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
        Schema::dropIfExists('sign_requests');
    }
}