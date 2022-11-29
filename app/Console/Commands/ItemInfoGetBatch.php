<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Goutte;
use Storage;
use \App\Models\TItem;
use \App\Models\TBrand;

class ItemInfoGetBatch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:itemInfoGetBatch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command itemInfoGetBatch';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $mbTItem = new TItem();
        $mbTBrand = new TBrand();
        $categories = config('const.CATEGORY');
        $soldStatus = config('const.SOLD_STATUS_NUM');
        $pages = config('const.PAGES');
        $url_base = 'https://fril.jp/s?category_id=';
        $url_sort = '&sort=created_at&order=desc';
        $url_search = '&transaction=soldout';
        $url_page = '&page=';
        $itemDataList =[];
        Log::debug("---【START】---------------------------");
        foreach ($soldStatus as $status) {
            foreach ($categories as $category) {
                foreach ($pages as $page) {
                    $startPage = microtime(true);
                    if ($status == config('const.SOLD_STATUS.SELLING')) {
                        $url = $url_base . $category . $url_sort . $url_page . $page;
                    } else {
                        $url = $url_base . $category . $url_sort . $url_search . $url_page . $page;

                    }
                    Log::debug('【start】' . $url);
                    // $url = 'https://fril.jp/s?query=L%2828%29%E3%80%917+For+All+Mankind+%E3%83%AC%E3%83%87%E3%82%A3%E3%83%BC';
                    // $url = 'https://fril.jp/s?query=Hikaru+172005%E6%A7%98%E5%B0%82%E7%94%A8%E3%80%80STUSSY+%E3%83%91%E3%83%BC%E3%82%AB%E3%83%BC&_gl=1*ew49k7*_ga*ODQxNDM4MzYyLjE2Njg0MTA3OTc.*_ga_7KV9PBS698*MTY2OTYxNjg4OC4xNS4xLjE2Njk2MTY5MTQuMzQuMC4w';
                    $crawler = Goutte::request('GET', $url);
                    $crawler->filter('.item')->each(function ($node)  use (&$itemDataList){
                        //個別ページへのリンク
                        $link = $node->filter(".item-box .item-box__image-wrapper a")->attr('href');
                        $itemCode = $this->makeItemCOde($link);
                        dump('-----code::' . $itemCode . '------------------');
                        //タイトル
                        $title = $node->filter(".item-box .item-box__image-wrapper a")->attr('title');
                        //価格
                        $priceText = $node->filter(".item-box .item-box__text-wrapper p.item-box__item-price")->text();
                        $price = $this->makePrice($priceText);
                        $itemDataList[$itemCode] = [
                            'item_code' => $itemCode,
                            'title' => $title,
                            'price' => $price,
                            'site_id' => config('const.RAKUMA.SITE_ID'),
                            'sold_flag' => config('const.SOLD_STATUS.SELLING'),
                            'brand_name' => null,
                            'brand_id' => null,
                            'image_path' => null,
                        ];
                        //売り切れフラグ
                        if ($node->filter(".item-box .item-box__image-wrapper .item-box__soldout_ribbon")) {
                            $itemDataList[$itemCode]['sold_flag'] = config('const.SOLD_STATUS.SOLDOUT');
                        }
                        //ブランド
                        if (count($node->filter(".item-box .item-box__text-wrapper .brand-name"))) {
                            $itemDataList[$itemCode]['brand_name'] = $node->filter(".item-box .item-box__text-wrapper .brand-name")->text();
                            dump('brand::' . $itemDataList[$itemCode]['brand_name']);
                        }
                        //画像
                        $image = $node->filter(".item-box .item-box__image-wrapper a noscript img")->attr('src');
                        if (isset($image)) {
                            $itemInfo = $this->arrangeImageInfo($image);
                            $imagePath = '/' . $itemInfo['imageInfo'][0] . '/' . $itemInfo['imageInfo'][1];
                            // バケットの`myprefix`フォルダへアップロード
                            $imgData = file_get_contents($image);
                            header('Content-type: image/' . $itemInfo["imageExtension"]);
                            Storage::put('file_name.'. $itemInfo["imageExtension"], $imgData, 'public');
                            if ( $imgData )
                            {
                                $path = Storage::disk('s3')->putFile($imagePath, storage_path('app/file_name.jpg'), 'public');
                                // アップロードした画像のフルパスを取得
                                $imagePutPath = Storage::disk('s3')->url($path);
                            }
                        }
                        $itemDataList[$itemCode]['image_path'] = $imagePutPath;
                    });
                    $endPage = microtime(true);
                    Log::debug('【ページ情報取得時間】' . $endPage - $startPage . '秒');
                    dump('DB');
                    if (isset($itemDataList)) {
                        foreach ($itemDataList as $key => $itemData) {
                            //ブランド処理
                            if (isset($itemData['brand_name'])) {
                                $existenceBrand = $mbTBrand->getDataByItemCode($itemData);
                                if (! isset($existenceBrand) && !is_countable($existenceBrand)) {
                                    //INSERT
                                    $itemData['brand_id'] = $mbTBrand->insertBrandData($itemData);
                                } else {
                                    //UPDATE(カテゴリーのみ)
                                    $itemData['brand_id'] = $existenceBrand['id'];
                                }
                            }

                            //アイテム処理
                            $existenceItem = $mbTItem->getDataByItemCode($itemData);
                            if (! isset($existenceItem) && !is_countable($existenceItem)) {
                                //INSERT
                                $mbTItem->insertItemData($itemData, $category);
                            } else if (
                                ($existenceItem->category == 10005 || $existenceItem->category == 10001)
                                && ! ($category == 10005 || $category == 10001)
                            ) {
                                //UPDATE(カテゴリーのみ)
                                $mbTItem->updateCategory($itemData, $category);
                            }

                            //売り切れフラグ更新
                            if ($itemData['sold_flag'] == config('const.SOLD_STATUS.SOLDOUT')) {
                                $mbTItem->updateSoldFlg($itemData);
                            }
                            unset($itemDataList[$key]);
                        }
                    }
                    $endDB = microtime(true);
                    Log::debug('【DB処理時間】' . $endDB - $endPage . '秒');
                    unset($itemDataList);
                    //Modelへ
                    sleep(3);
                }
            }
        }
        
        Log::debug("---【END】---------------------------");
    }

    public function makeItemCOde($link)
    {
        $ret = null;
        if (isset($link)) {
            $ret = str_replace(config('const.RAKUMA.SITE_DOMAIN'), '', $link);
        }
        return $ret;
    }

    public function makePrice($priceTest)
    {
        $ret = null;
        if (isset($priceTest)) {
            $priceTest = str_replace('¥', '', $priceTest);
            $priceTest = str_replace(',', '', $priceTest);
            $ret = $priceTest;
        }
        return $ret;
    }

    public function arrangeImageInfo($link)
    {
        $ret = null;
        if (isset($link)) {
            $link = str_replace(config('const.RAKUMA.SITE_IMAGE_BASE'), '', $link);
            $imageInfo = explode('?', $link)[0];
            $ret['imageInfo'] = explode('/', $imageInfo);
            $ret['imageExtension'] = explode('.', $ret['imageInfo'][2])[1];
        }
        return $ret;
    }
}
