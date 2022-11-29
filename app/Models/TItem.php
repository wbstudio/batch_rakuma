<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DB;

class TItem extends Model
{
    use HasFactory;

    public function getDataByItemCode($itemData)
    {
        $itemCode = $itemData['item_code'];
        $siteId = $itemData['site_id'];

        $columnList = [
            'ti.id',	
            'ti.item_code',	
            'ti.title',	
            'ti.category',	
            'ti.image_path',	
            'ti.price',	
            'ti.sold_flag',	
            'ti.site_id',	
            'ti.brand_name',	
            'ti.brand_id',	
            'ti.status',	
            'ti.created_at',	
            'ti.updated_at',	
        ];

        $query = DB::table('t_items as ti');

        $query->select($columnList);

        $query->where('ti.item_code', $itemCode)
            ->where('ti.site_id', $siteId)
            ->where('ti.delete_flg', config('const.DELETE_FLG_OFF'));

        $retData = $query->first();
        return $retData;
    }

    public function insertItemData($itemData, $category)
    {
        $query = DB::table('t_items');
        $value = [
            'item_code' => $itemData['item_code'],	
            'title' => $itemData['title'],	
            'category' => $category,
            'image_path' => $itemData['image_path'],	
            'price' => $itemData['price'],	
            'sold_flag' => $itemData['sold_flag'],	
            'site_id' => $itemData['site_id'],	
            'brand_name' => $itemData['brand_name'],
            'brand_id' => $itemData['brand_id'],
            'status' => 0,
            'delete_flg' => config('const.DELETE_FLG_OFF'),
            'created_at' => now(),	
            'updated_at' => now(),	
        ];

        $query->insert($value);
    }

    public function updateCategory($itemData, $category)
    {
        $value = [
            'category' => $category,
            'updated_at' => now(),
        ];

        $query = DB::table('t_items');
        $query->where('item_code', $itemData['item_code'])
            ->where('site_id', $itemData['site_id']);
        $query->update($value);
    }

    public function updateSoldFlg($itemData)
    {
        $value = [
            'sold_flag' => $itemData['sold_flag'],
            'updated_at' => now(),
        ];

        $query = DB::table('t_items');
        $query->where('item_code', $itemData['item_code'])
            ->where('site_id', $itemData['site_id']);
        $query->update($value);
    }

}
