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
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('ac_discount_amount')->nullable()->after('additional_expense_value_3');
            $table->string('pc_discount_amount')->nullable()->after('additional_expense_value_4');
            $table->string('additional_expense_key_5')->nullable()->after('pc_discount_amount');
            $table->decimal('additional_expense_value_5', 22, 4)->default(0)->after('additional_expense_key_5');
            $table->string('gc_discount_amount')->nullable()->after('additional_expense_value_5');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            //
        });
    }
};
