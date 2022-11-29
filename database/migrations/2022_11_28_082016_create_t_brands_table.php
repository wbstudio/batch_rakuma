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
        Schema::create('t_brands', function (Blueprint $table) {
            $table->id()->comment('ID');
            $table->string('name')->comment('ブランド名');
            $table->integer('site_id')->comment('サイトID');
            $table->integer('status')->nullable()->comment('ステータス');
            $table->integer('delete_flg')->comment('削除フラグ');
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
        Schema::dropIfExists('t_brands');
    }
};
