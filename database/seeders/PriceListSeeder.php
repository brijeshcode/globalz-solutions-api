<?php

namespace Database\Seeders;

use App\Models\Items\Item;
use App\Models\Items\PriceList;
use App\Models\Items\PriceListItem;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PriceListSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = Item::get();

        $priceLists = [
            [
                'code' => 'DEFAULT PL',
                'description' => 'price list default',
                'is_default' => 1,
                'note' => 'price list description',
                'items' => [
                    [
                        'item_code'=> $items[0]->code ,
                        'price_list_id' => 1,
                        'item_id' => $items[0]->id,
                        'item_description' => $items[0]->description ,
                        'sell_price' => 100,
                    ],
                    [
                        'item_code'=> $items[1]->code ,
                        'price_list_id' => 1,
                        'item_id' => $items[1]->id,
                        'item_description' => $items[1]->description ,
                        'sell_price' => 700,
                    ],
                    [
                        'item_code'=> $items[2]->code ,
                        'price_list_id' => 1,
                        'item_id' => $items[2]->id,
                        'item_description' => $items[2]->description ,
                        'sell_price' => 100,
                    ],
                    [
                        'item_code'=> $items[4]->code ,
                        'price_list_id' => 1,
                        'item_id' => $items[4]->id,
                        'item_description' => $items[4]->description ,
                        'sell_price' => 500,
                    ],
                    [
                        'item_code'=> $items[5]->code ,
                        'price_list_id' => 1,
                        'item_id' => $items[5]->id,
                        'item_description' => $items[5]->description ,
                        'sell_price' => 1500,
                    ],
                    [
                        'item_code'=> $items[6]->code ,
                        'price_list_id' => 1,
                        'item_id' => $items[6]->id,
                        'item_description' => $items[6]->description ,
                        'sell_price' => 500,
                    ]
                ],
            ],

            [
                'code' => 'REGULER PL',
                'description' => 'price list normal',
                'is_default' => 0,
                'note' => 'price list description',
                'items' => [
                    [
                        'item_code'=> $items[2]->code ,
                        'price_list_id' => 2,
                        'item_id' => $items[2]->id,
                        'item_description' => $items[2]->description ,
                        'sell_price' => 1500,
                    ],
                    [
                        'item_code'=> $items[5]->code ,
                        'price_list_id' => 2,
                        'item_id' => $items[5]->id,
                        'item_description' => $items[5]->description ,
                        'sell_price' => 1400,
                    ],
                    [
                        'item_code'=> $items[8]->code ,
                        'price_list_id' => 1,
                        'item_id' => $items[8]->id,
                        'item_description' => $items[8]->description ,
                        'sell_price' => 1700,
                    ],
                ],
            ],
            
        ];

        foreach ($priceLists as $priceList) {
            PriceList::create([
                'code' => $priceList['code'],
                'is_default' => $priceList['is_default'],
                'description' => $priceList['description'],
                'note' => $priceList['note'],
            ]);

            foreach($priceList['items'] as $item)
            PriceListItem::create( $item);
        }
    
    }
}
