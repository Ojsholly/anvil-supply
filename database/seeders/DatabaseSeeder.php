<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Deterministic — the same rows every time. The data is deliberately
     * messy: duplicate names, an apostrophe variant, repeat customers, and
     * near-identical orders, so agent disambiguation is visible on screen.
     */
    public function run(): void
    {
        $customers = collect([
            ['name' => 'James Carter',  'email' => 'james.carter@meridian-auto.example', 'company' => 'Meridian Auto Works',  'phone' => '+1 555-0142'],
            ['name' => 'James Carter',  'email' => 'j.carter@mailbox.example',           'company' => null,                    'phone' => null],
            ['name' => "Sarah O'Brien", 'email' => 'sarah.obrien@kilnworks.example',      'company' => 'Kilnworks Ceramics',    'phone' => '+1 555-0187'],
            ['name' => 'Sarah Obrien',  'email' => 's.obrien@mailbox.example',           'company' => null,                    'phone' => null],
            ['name' => 'Dmitri Volkov', 'email' => 'd.volkov@foundry-nine.example',      'company' => 'Foundry Nine',          'phone' => '+7 555-0110'],
            ['name' => 'Amara Okafor',  'email' => 'a.okafor@delta-fab.example',         'company' => 'Delta Fabrication',     'phone' => '+234 555-0163'],
            ['name' => 'Chen Wei',      'email' => 'chen.wei@westport.example',          'company' => 'Westport Machine Shop', 'phone' => '+1 555-0139'],
            ['name' => 'Priya Nair',    'email' => 'priya.nair@nullfield.example',       'company' => 'Nullfield Studios',     'phone' => null,
              'notes' => 'Asked twice for invoices by fax. Do not fax.'],
        ])->map(fn ($c) => Customer::create($c));

        $products = collect([
            ['sku' => 'ANV-10KG',     'name' => '10kg Cast-Iron Anvil',                    'price_cents' => 18900, 'stock' => 14],
            ['sku' => 'ANV-25KG',     'name' => '25kg Cast-Iron Anvil',                    'price_cents' => 34900, 'stock' => 6],
            ['sku' => 'ANV-50KG',     'name' => '50kg Drop-Forged Anvil',                  'price_cents' => 64900, 'stock' => 2],
            ['sku' => 'HMR-BALLPEEN', 'name' => 'Ball-Peen Hammer (the good one)',         'price_cents' => 2900,  'stock' => 41],
            ['sku' => 'HMR-CROSS',    'name' => 'Cross-Peen Hammer 2lb',                   'price_cents' => 3400,  'stock' => 27],
            ['sku' => 'TNG-SET-5',    'name' => '5pc Blacksmith Tongs Set',                'price_cents' => 5600,  'stock' => 19],
            ['sku' => 'VSE-WS4IN',    'name' => '4-inch Workbench Vise',                   'price_cents' => 11200, 'stock' => 8],
            ['sku' => 'VSE-WS4IN-W',  'name' => '4-inch Workbench Vise (warehouse second — wobbly)', 'price_cents' => 4900, 'stock' => 3],
            ['sku' => 'APR-LEATHER',  'name' => 'Split-Leg Leather Apron',                 'price_cents' => 7800,  'stock' => 11],
            ['sku' => 'PUN-SET-3',    'name' => 'Center Punch Set (3pc)',                  'price_cents' => 2100,  'stock' => 33],
            ['sku' => 'QNO-OIL-1L',   'name' => 'Quench Oil 1L',                           'price_cents' => 1600,  'stock' => 52],
            ['sku' => 'BOOK-FORGE',   'name' => '"So You Bought an Anvil" — field guide',  'price_cents' => 2400,  'stock' => 20],
        ])->mapWithKeys(fn ($p) => [$p['sku'] => Product::create($p)]);

        // [number, customer idx, status, placed_at, shipped_at, [[sku, qty], ...]]
        $orders = [
            ['AS-1044', 0, 'delivered', '2026-09-14 10:22', '2026-09-15', [['ANV-10KG', 1], ['BOOK-FORGE', 1]]],
            ['AS-1045', 2, 'delivered', '2026-09-19 15:03', '2026-09-20', [['TNG-SET-5', 2]]],
            ['AS-1046', 4, 'delivered', '2026-09-27 09:41', '2026-09-28', [['ANV-25KG', 1], ['QNO-OIL-1L', 4]]],
            ['AS-1047', 0, 'delivered', '2026-10-02 11:57', '2026-10-03', [['HMR-BALLPEEN', 2], ['PUN-SET-3', 1]]],
            ['AS-1048', 5, 'delivered', '2026-10-05 14:30', '2026-10-06', [['ANV-25KG', 1], ['APR-LEATHER', 1]]],
            ['AS-1049', 6, 'shipped',   '2026-10-11 08:15', '2026-10-12', [['VSE-WS4IN', 2]]],
            ['AS-1050', 4, 'shipped',   '2026-10-13 16:44', '2026-10-14', [['ANV-50KG', 1], ['TNG-SET-5', 1]]],
            ['AS-1051', 1, 'paid',      '2026-10-17 12:05', null,         [['HMR-CROSS', 1], ['BOOK-FORGE', 2]]],
            ['AS-1052', 2, 'shipped',   '2026-10-18 10:52', '2026-10-19', [['PUN-SET-3', 1], ['QNO-OIL-1L', 2]]],
            ['AS-1053', 0, 'paid',      '2026-10-20 09:11', null,         [['ANV-10KG', 1]]],
            ['AS-1054', 7, 'pending',   '2026-10-22 19:37', null,         [['VSE-WS4IN-W', 1], ['BOOK-FORGE', 1]]],
            ['AS-1055', 3, 'delivered', '2026-10-24 13:26', '2026-10-25', [['HMR-BALLPEEN', 1]]],
            ['AS-1056', 5, 'shipped',   '2026-10-26 07:58', '2026-10-27', [['ANV-10KG', 2], ['TNG-SET-5', 1]]],
            ['AS-1057', 6, 'cancelled', '2026-10-27 11:20', null,         [['ANV-50KG', 1]]],
            ['AS-1058', 0, 'refunded',  '2026-10-28 15:49', null,         [['HMR-BALLPEEN', 1], ['QNO-OIL-1L', 1]]],
            ['AS-1059', 2, 'paid',      '2026-10-29 10:34', null,         [['ANV-25KG', 1]]],
            ['AS-1060', 1, 'pending',   '2026-10-30 17:02', null,         [['QNO-OIL-1L', 3]]],
            ['AS-1061', 7, 'shipped',   '2026-10-31 09:45', '2026-11-01', [['ANV-10KG', 1], ['APR-LEATHER', 1]]],
        ];

        foreach ($orders as [$number, $ci, $status, $placed, $shipped, $items]) {
            $order = Order::create([
                'number' => $number,
                'customer_id' => $customers[$ci]->id,
                'status' => $status,
                'placed_at' => $placed,
                'shipped_at' => $shipped,
            ]);

            $total = 0;
            foreach ($items as [$sku, $qty]) {
                $order->items()->create([
                    'product_id' => $products[$sku]->id,
                    'quantity' => $qty,
                    'unit_price_cents' => $products[$sku]->price_cents,
                ]);
                $total += $products[$sku]->price_cents * $qty;
            }
            $order->update(['total_cents' => $total]);
        }

        // One refund already on the books — AS-1058 (James Carter, Meridian).
        // Gives Part 2 something to read back.
        Order::where('number', 'AS-1058')->first()->refunds()->create([
            'amount_cents' => 4500,
            'reason' => 'Hammer head loose on arrival — partial refund agreed',
            'idempotency_key' => 'seed-as1058-partial',
            'processed_at' => '2026-10-29 09:15',
        ]);
    }
}
