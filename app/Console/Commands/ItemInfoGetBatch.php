<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Goutte;
use Storage;

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
                    if ($status == config('const.SOLD_STATUS.SELLING')) {
                        $url = $url_base . $category . $url_sort . $url_page . $page;
                    } else {
                        $url = $url_base . $category . $url_sort . $url_search . $url_page . $page;

                    }
                    $url = 'https://fril.jp/s?query=Air+Jordan+11+Brad+%281996%29+size+28.5cn&_gl=1*44pz35*_ga*ODQxNDM4MzYyLjE2Njg0MTA3OTc.*_ga_7KV9PBS698*MTY2OTI1MDg4MS4xMC4xLjE2NjkyNTA4ODEuNjAuMC4w';
                    dump($url);
                    $crawler = Goutte::request('GET', $url);
                    $crawler->filter('.item')->each(function ($node)  use (&$itemDataList){
                        //個別ページへのリンク
                        $link = $node->filter(".item-box .item-box__image-wrapper a")->attr('href');
                        $itemCode = $this->makeItemCOde($link);
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
                                var_dump('a');
                                $path = Storage::disk('s3')->putFile('/', storage_path('app/file_name.jpg'), 'public');
                                // アップロードした画像のフルパスを取得
                                $imagePutPath = Storage::disk('s3')->url($path);
                            }
                        }
                        //個別ページへのリンク
                        $link = $node->filter(".item-box .item-box__image-wrapper a")->attr('href');
                        //個別ページへのリンク
                        $link = $node->filter(".item-box .item-box__image-wrapper a")->attr('href');
                        //個別ページへのリンク
                        $link = $node->filter(".item-box .item-box__image-wrapper a")->attr('href');
                        $itemDataList[] = $image;
                        $itemDataList[] = $imagePutPath;
                    }); 
                }
                //Modelへ
                sleep(5);
            }
        }
        
        Log::debug($itemDataList);
        Log::debug("---【END】---------------------------");
    }

    public function makeItemCOde($link)
    {
        $ret = null;
        if (isset($link)) {
            $ret = str_replace(config('const.SITE_DOMAIN.RAKUMA'), '', $link);
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
