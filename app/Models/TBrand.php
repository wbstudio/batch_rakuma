<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DB;

class TBrand extends Model
{
    use HasFactory;
    public function getDataByItemCode($itemData)
    {
        $brandName = $itemData['brand_name'];
        $siteId = $itemData['site_id'];

        $columnList = [
            'tb.id',	
            'tb.name',	
            'tb.site_id',	
            'tb.status',	
            'tb.delete_flg',	
            'tb.created_at',	
            'tb.updated_at',
        ];

        $query = DB::table('t_brands as tb');

        $query->select($columnList);

        $query->where('tb.name', $brandName)
            ->where('tb.site_id', $siteId)
            ->where('tb.delete_flg', config('const.DELETE_FLG_OFF'));

        $retData = $query->first();
        return $retData;
    }

    public function insertBrandData($itemData)
    {
        $query = DB::table('t_brands');
        $value = [
            'name' => $itemData['brand_name'],
            'site_id' => $itemData['site_id'],
            'status' => 0,
            'delete_flg' => config('const.DELETE_FLG_OFF'),	
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $query->insert($value);
        return $query->id;
    }
}
