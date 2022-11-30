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
        $startAll = microtime(true);
        foreach ($soldStatus as $status) {
            foreach ($categories as $category) {
                foreach ($pages as $page) {
                    $startPage = microtime(true);
                    if ($status == config('const.SOLD_STATUS.SELLING')) {
                        $url = $url_base . $category . $url_sort . $url_page . $page;
                    } else {
                        $url = $url_base . $category . $url_sort . $url_search . $url_page . $page;

                    }
                    dump($url);
                    Log::debug('【start】' . $url);
                    $crawler = Goutte::request('GET', $url);
                    $crawler->filter('.item')->each(function ($node)  use (&$itemDataList){
                        //個別ページへのリンク
                        $link = $node->filter(".item-box .item-box__image-wrapper a")->attr('href');
                        $itemCode = $this->makeItemCOde($link);
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
                    if (isset($itemDataList)) {
                        dump(count($itemDataList) . '件');
                        foreach ($itemDataList as $key => $itemData) {
                            //ブランド処理
                            if (isset($itemData['brand_name'])) {
                                $existenceBrand = $mbTBrand->getDataByItemCode($itemData);
                                if (! isset($existenceBrand) && !is_countable($existenceBrand)) {
                                    //INSERT
                                    $itemData['brand_id'] = $mbTBrand->insertBrandData($itemData);
                                } else {
                                    //UPDATE(カテゴリーのみ)
                                    $itemData['brand_id'] = $existenceBrand->id;
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
                        unset($existenceBrand, $existenceItem);
                    }
                    $endDB = microtime(true);
                    Log::debug('【DB処理時間】' . $endDB - $endPage . '秒');
                    if ($endDB- $startPage < 5) {
                        sleep(3);
                    }
                    unset($itemDataList, $startPage, $endPage, $endDB);
                }
            }
        }
        $endAll = microtime(true);        
        Log::debug("全体の時間：" . $endAll - $startAll);
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
