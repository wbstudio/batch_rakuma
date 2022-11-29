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
        Schema::create('t_items', function (Blueprint $table) {
            $table->id()->comment('ID');
            $table->string('item_code')->comment('商品コード');
            $table->string('title')->comment('タイトル');
            $table->integer('category')->comment('カテゴリー');
            $table->string('image_path')->comment('画像（S3のパス）');
            $table->integer('price')->comment('価格');
            $table->integer('sold_flag')->comment('売り切れフラグ');
            $table->integer('site_id')->comment('サイトID');
            $table->string('brand_name')->nullable()->comment('ブランド名');
            $table->integer('brand_id')->nullable()->comment('ブランドID');
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
        Schema::dropIfExists('t_items');
    }
};
