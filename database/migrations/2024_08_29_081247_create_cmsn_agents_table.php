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
        Schema::create('cmsn_agents', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('transaction_id')->unsigned();
            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('cascade');
            $table->integer('transaction_sell_line_id')->unsigned();
            $table->foreign('transaction_sell_line_id')->references('id')->on('transaction_sell_lines')->onDelete('cascade');
            $table->decimal('discount_amount', 22, 4)->default(0);
            $table->integer('user_id')->unsigned();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->decimal('cmmsn_percent', 4, 2)->default(0);
            $table->string('cmmsn_type')->nullable();
            $table->timestamps();

            //Indexing
            $table->index('transaction_id');
            $table->index('transaction_sell_line_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cmsn_agents');
    }
};
