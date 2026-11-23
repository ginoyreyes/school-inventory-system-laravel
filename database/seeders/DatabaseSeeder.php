<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\BorrowRecord;
use App\Models\Category;
use App\Models\Item;
use App\Models\Place;
use App\Models\Setting;
use App\Models\StockTransaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Skip seeding if initial data already exists so `composer setup` can be re-run safely
        if (User::where('username', 'lab.admin')->exists()) {
            return;
        }

        // 1. Bootstrap Administrator
        $admin = User::create([
            'name'                => 'Laboratory Administrator',
            'username'            => 'lab.admin',
            'email'               => 'lab.admin@cvsu.edu.ph',
            'password'            => 'Admin1234',
            'role'                => 'Administrator',
            'is_active'           => true,
            'password_changed_at' => now(),
        ]);

        // 2. System Settings
        Setting::create([
            'low_stock_threshold' => 10,
            'borrow_period_days'  => 7,
            'units'               => ['pcs', 'box', 'set', 'ream', 'pack', 'unit', 'bottle', 'meter', 'kg'],
            'conditions'          => ['New', 'Good', 'Fair', 'Needs Repair', 'Retired'],
            'updated_by'          => $admin->id,
        ]);

        // 3. Categories (6)
        $categoryDefs = [
            ['name' => 'Keyboards', 'description' => 'Wired and wireless keyboards for computer laboratory workstations.'],
            ['name' => 'Mice', 'description' => 'Optical and wireless pointing devices for laboratory units.'],
            ['name' => 'Monitors', 'description' => 'Display units, monitor stands and video cables for lab workstations.'],
            ['name' => 'System Units', 'description' => 'Desktop system units, internal storage and memory upgrades.'],
            ['name' => 'Projectors', 'description' => 'Projectors, screens and presentation accessories for classrooms.'],
            ['name' => 'Other School Resources', 'description' => 'Consumables and shared school resources used across the laboratory.'],
        ];

        $categories = [];
        foreach ($categoryDefs as $cat) {
            $categories[$cat['name']] = Category::create([
                'name'        => $cat['name'],
                'description' => $cat['description'],
                'created_by'  => $admin->id,
            ]);
        }

        // 4. Storage Places (8)
        $placeDefs = [
            ['name' => 'Computer Laboratory 1', 'area' => 'Laboratories', 'description' => 'Main teaching laboratory for BSIT programming classes.'],
            ['name' => 'Computer Laboratory 2', 'area' => 'Laboratories', 'description' => 'Multimedia and networking laboratory workstations.'],
            ['name' => 'AV Room', 'area' => 'Facilities', 'description' => 'Projectors, screens and audio-visual equipment storage.'],
            ['name' => 'Laboratory Stockroom', 'area' => 'Storage', 'description' => 'Primary stockroom for spare parts and consumables.'],
            ['name' => 'Supply Room', 'area' => 'Storage', 'description' => 'General school supplies and paper stock.'],
            ['name' => 'Faculty Room', 'area' => 'Offices', 'description' => 'Equipment issued to and stored for faculty use.'],
            ['name' => 'Library', 'area' => 'Facilities', 'description' => 'Library computer stations and peripherals.'],
            ['name' => 'Administrative Office', 'area' => 'Offices', 'description' => 'Office equipment and administrative supplies.'],
        ];

        foreach ($placeDefs as $place) {
            Place::create([
                'name'        => $place['name'],
                'area'        => $place['area'],
                'description' => $place['description'],
                'created_by'  => $admin->id,
            ]);
        }

        // 5. Borrow Definitions
        $borrowDefs = [
            [
                'ref' => 'BRW-0001', 'item' => 'Projector (3200 lumens)', 'borrowerName' => 'Prof. Elena Santos', 'borrowerIdNumber' => 'FAC-2041', 'qty' => 1,
                'days' => 2, 'expectedInDays' => 5, 'returnedDaysAgo' => null, 'remarks' => 'Room 204 classroom presentation.',
            ],
            [
                'ref' => 'BRW-0002', 'item' => 'Projector Remote Control', 'borrowerName' => 'BSIT 3-A', 'borrowerIdNumber' => 'SEC-2024-0315', 'qty' => 1,
                'days' => 1, 'expectedInDays' => 3, 'returnedDaysAgo' => null, 'remarks' => 'Capstone defense presentation.',
            ],
            [
                'ref' => 'BRW-0003', 'item' => 'LED Monitor 19 inch', 'borrowerName' => 'Research Laboratory', 'borrowerIdNumber' => 'LAB-RESEARCH-02', 'qty' => 2,
                'days' => 12, 'expectedInDays' => -5, 'returnedDaysAgo' => null, 'remarks' => 'Second monitor for the research workstations. Follow up needed.',
            ],
            [
                'ref' => 'BRW-0004', 'item' => 'Extension Cord (4-socket)', 'borrowerName' => 'Prof. Ramon Cruz', 'borrowerIdNumber' => 'FAC-1188', 'qty' => 1,
                'days' => 20, 'expectedInDays' => -12, 'returnedDaysAgo' => 10, 'remarks' => 'Returned in good condition.',
            ],
            [
                'ref' => 'BRW-0005', 'item' => 'USB Wired Keyboard', 'borrowerName' => 'Multimedia Laboratory', 'borrowerIdNumber' => 'LAB-MM-01', 'qty' => 3,
                'days' => 25, 'expectedInDays' => -18, 'returnedDaysAgo' => 20, 'remarks' => 'Returned after the seminar.',
            ],
            [
                'ref' => 'BRW-0006', 'item' => 'Projector Screen (Tripod)', 'borrowerName' => 'College Extension Office', 'borrowerIdNumber' => 'OFF-EXT-11', 'qty' => 1,
                'days' => 28, 'expectedInDays' => -21, 'returnedDaysAgo' => 21, 'remarks' => 'Returned complete with carrying case.',
            ],
        ];

        // 6. All 20 Inventory Items + Historical Events
        $itemDefs = [
            [
                'name' => 'USB Wired Keyboard', 'category' => 'Keyboards', 'opening' => 24, 'minimumStock' => 10, 'unit' => 'pcs',
                'location' => 'Computer Laboratory 1', 'condition' => 'Good',
                'description' => 'Standard USB wired keyboard issued with every laboratory workstation.',
                'events' => [
                    ['type' => 'Stock In', 'qty' => 20, 'days' => 21, 'supplier' => 'CDO Computer Traders', 'remarks' => 'Delivery for the second semester.'],
                    ['type' => 'Stock Out', 'qty' => 9, 'days' => 12, 'reason' => 'Defective unit replacement', 'remarks' => 'Nine units replaced in Laboratory 1.'],
                ],
            ],
            [
                'name' => 'Mechanical Keyboard (RGB)', 'category' => 'Keyboards', 'opening' => 6, 'minimumStock' => 4, 'unit' => 'pcs',
                'location' => 'Computer Laboratory 2', 'condition' => 'Good',
                'description' => 'Mechanical keyboard reserved for the programming and multimedia laboratory.',
                'events' => [['type' => 'Stock In', 'qty' => 6, 'days' => 30, 'supplier' => 'Metro Manila IT Supplies', 'remarks' => 'Procured for the programming laboratory.']],
            ],
            [
                'name' => 'Wireless Keyboard', 'category' => 'Keyboards', 'opening' => 8, 'minimumStock' => 6, 'unit' => 'pcs',
                'location' => 'AV Room', 'condition' => 'Good',
                'description' => '2.4 GHz wireless keyboard used with the presentation cart.',
                'events' => [['type' => 'Stock Out', 'qty' => 3, 'days' => 9, 'reason' => 'Issued to faculty', 'remarks' => 'Issued for the AV presentation cart.']],
            ],
            [
                'name' => 'Optical Mouse', 'category' => 'Mice', 'opening' => 30, 'minimumStock' => 12, 'unit' => 'pcs',
                'location' => 'Computer Laboratory 1', 'condition' => 'Good',
                'description' => 'USB optical mouse, 1000 DPI, black.',
                'events' => [
                    ['type' => 'Stock In', 'qty' => 18, 'days' => 18, 'supplier' => 'CDO Computer Traders', 'remarks' => 'Replacement stock for the laboratories.'],
                    ['type' => 'Stock Out', 'qty' => 14, 'days' => 7, 'reason' => 'Laboratory replacement', 'remarks' => 'Replaced double-clicking units in Laboratory 1.'],
                ],
            ],
            [
                'name' => 'Wireless Mouse', 'category' => 'Mice', 'opening' => 10, 'minimumStock' => 8, 'unit' => 'pcs',
                'location' => 'Computer Laboratory 2', 'condition' => 'Good',
                'description' => 'Silent 2.4 GHz wireless mouse with USB receiver.',
                'events' => [['type' => 'Stock Out', 'qty' => 4, 'days' => 5, 'reason' => 'Issued to faculty', 'remarks' => 'Issued to the research laboratory.']],
            ],
            [
                'name' => 'LED Monitor 19 inch', 'category' => 'Monitors', 'opening' => 20, 'minimumStock' => 6, 'unit' => 'pcs',
                'location' => 'Computer Laboratory 1', 'condition' => 'Good',
                'description' => '19-inch LED monitor with HDMI and VGA inputs.',
                'events' => [
                    ['type' => 'Stock In', 'qty' => 10, 'days' => 24, 'supplier' => 'Metro Manila IT Supplies', 'remarks' => 'Additional monitors for the new workstation row.'],
                    ['type' => 'Stock Out', 'qty' => 12, 'days' => 11, 'reason' => 'New workstation setup', 'remarks' => 'Deployed to the research room workstations.'],
                ],
            ],
            [
                'name' => 'LED Monitor 22 inch', 'category' => 'Monitors', 'opening' => 8, 'minimumStock' => 4, 'unit' => 'pcs',
                'location' => 'Computer Laboratory 2', 'condition' => 'Good',
                'description' => '22-inch full-HD monitor for the multimedia laboratory.',
                'events' => [['type' => 'Stock Out', 'qty' => 5, 'days' => 6, 'reason' => 'Laboratory deployment', 'remarks' => 'Installed in the multimedia laboratory.']],
            ],
            [
                'name' => 'HDMI Cable 1.5m', 'category' => 'Monitors', 'opening' => 14, 'minimumStock' => 8, 'unit' => 'pcs',
                'location' => 'AV Room', 'condition' => 'Good',
                'description' => '1.5 metre HDMI cable for monitors and projectors.',
                'events' => [
                    ['type' => 'Stock In', 'qty' => 12, 'days' => 16, 'supplier' => 'AV Solutions Philippines', 'remarks' => 'Replacement cables.'],
                    ['type' => 'Stock Out', 'qty' => 16, 'days' => 4, 'reason' => 'Classroom deployment', 'remarks' => 'Distributed to classroom AV sets.'],
                ],
            ],
            [
                'name' => 'System Unit (Core i3, 8GB)', 'category' => 'System Units', 'opening' => 12, 'minimumStock' => 5, 'unit' => 'unit',
                'location' => 'Computer Laboratory 1', 'condition' => 'Good',
                'description' => 'Desktop system unit, Core i3-10100, 8GB RAM, 1TB HDD.',
                'events' => [
                    ['type' => 'Stock In', 'qty' => 6, 'days' => 26, 'supplier' => 'Metro Manila IT Supplies', 'remarks' => 'Additional laboratory units.'],
                    ['type' => 'Stock Out', 'qty' => 9, 'days' => 13, 'reason' => 'Workstation installation', 'remarks' => 'Installed in Computer Laboratory 1.'],
                ],
            ],
            [
                'name' => 'System Unit (Core i5, 16GB)', 'category' => 'System Units', 'opening' => 5, 'minimumStock' => 3, 'unit' => 'unit',
                'location' => 'Computer Laboratory 2', 'condition' => 'Good',
                'description' => 'Desktop system unit for software development subjects, Core i5, 16GB RAM, 512GB SSD.',
                'events' => [['type' => 'Stock Out', 'qty' => 2, 'days' => 8, 'reason' => 'Software laboratory', 'remarks' => 'Deployed to the software engineering laboratory.']],
            ],
            [
                'name' => 'SSD 480GB SATA', 'category' => 'System Units', 'opening' => 10, 'minimumStock' => 4, 'unit' => 'pcs',
                'location' => 'Laboratory Stockroom', 'condition' => 'New',
                'description' => '480GB SATA SSD used to upgrade aging laboratory units.',
                'events' => [['type' => 'Stock Out', 'qty' => 6, 'days' => 3, 'reason' => 'Unit upgrade', 'remarks' => 'Installed in six laboratory units.']],
            ],
            [
                'name' => 'Projector (3200 lumens)', 'category' => 'Projectors', 'opening' => 4, 'minimumStock' => 2, 'unit' => 'unit',
                'location' => 'AV Room', 'condition' => 'Good',
                'description' => 'HDMI/VGA projector, 3200 lumens, ceiling or cart mount.',
                'events' => [['type' => 'Stock In', 'qty' => 3, 'days' => 20, 'supplier' => 'AV Solutions Philippines', 'remarks' => 'Procured for the lecture rooms.']],
            ],
            [
                'name' => 'Projector Screen (Tripod)', 'category' => 'Projectors', 'opening' => 5, 'minimumStock' => 2, 'unit' => 'pcs',
                'location' => 'AV Room', 'condition' => 'Good',
                'description' => 'Portable 70-inch tripod projection screen.',
                'events' => [['type' => 'Stock Out', 'qty' => 1, 'days' => 14, 'reason' => 'Seminar use', 'remarks' => 'Used during the research colloquium.']],
            ],
            [
                'name' => 'Projector Remote Control', 'category' => 'Projectors', 'opening' => 6, 'minimumStock' => 6, 'unit' => 'pcs',
                'location' => 'AV Room', 'condition' => 'Good',
                'description' => 'Replacement remote control compatible with the laboratory projectors.',
                'events' => [['type' => 'Stock Out', 'qty' => 2, 'days' => 10, 'reason' => 'Replacement', 'remarks' => 'Replaced missing remotes in Rooms 201 and 204.']],
            ],
            [
                'name' => 'Bond Paper (A4)', 'category' => 'Other School Resources', 'opening' => 60, 'minimumStock' => 25, 'unit' => 'ream',
                'location' => 'Laboratory Stockroom', 'condition' => 'New',
                'description' => 'A4 multi-purpose bond paper, 70gsm, 500 sheets per ream.',
                'events' => [
                    ['type' => 'Stock In', 'qty' => 50, 'days' => 15, 'supplier' => 'National Book Store', 'remarks' => 'Quarterly supply order.'],
                    ['type' => 'Stock Out', 'qty' => 30, 'days' => 2, 'reason' => 'Office request', 'remarks' => 'Issued to the college offices.'],
                ],
            ],
            [
                'name' => 'Whiteboard Marker (box of 12)', 'category' => 'Other School Resources', 'opening' => 18, 'minimumStock' => 10, 'unit' => 'box',
                'location' => 'Laboratory Stockroom', 'condition' => 'New',
                'description' => 'Bullet-tip whiteboard markers, black and blue, box of 12.',
                'events' => [['type' => 'Stock Out', 'qty' => 11, 'days' => 5, 'reason' => 'Faculty distribution', 'remarks' => 'Distributed to laboratory instructors.']],
            ],
            [
                'name' => 'Printer Ink Cartridge (Black)', 'category' => 'Other School Resources', 'opening' => 9, 'minimumStock' => 6, 'unit' => 'pcs',
                'location' => 'Laboratory Stockroom', 'condition' => 'New',
                'description' => 'Black ink cartridge for the laboratory printer.',
                'events' => [['type' => 'Stock Out', 'qty' => 5, 'days' => 4, 'reason' => 'Printer refill', 'remarks' => 'Used by the laboratory printer.']],
            ],
            [
                'name' => 'Ethernet Cable Cat6 (3m)', 'category' => 'Other School Resources', 'opening' => 25, 'minimumStock' => 12, 'unit' => 'pcs',
                'location' => 'Laboratory Stockroom', 'condition' => 'Good',
                'description' => '3-metre Cat6 patch cable for laboratory network drops.',
                'events' => [['type' => 'Stock Out', 'qty' => 15, 'days' => 6, 'reason' => 'Network cabling', 'remarks' => 'Replaced damaged patch cables in Laboratory 2.']],
            ],
            [
                'name' => 'Extension Cord (4-socket)', 'category' => 'Other School Resources', 'opening' => 8, 'minimumStock' => 4, 'unit' => 'pcs',
                'location' => 'Laboratory Stockroom', 'condition' => 'Good',
                'description' => '4-socket extension cord with surge protection.',
                'events' => [['type' => 'Stock Out', 'qty' => 3, 'days' => 7, 'reason' => 'Laboratory setup', 'remarks' => 'Used for the new workstation row.']],
            ],
            [
                'name' => 'Computer Cleaning Kit', 'category' => 'Other School Resources', 'opening' => 7, 'minimumStock' => 5, 'unit' => 'set',
                'location' => 'Laboratory Stockroom', 'condition' => 'New',
                'description' => 'Anti-static brush, microfiber cloth and cleaning solution set.',
                'events' => [['type' => 'Stock Out', 'qty' => 2, 'days' => 9, 'reason' => 'Maintenance', 'remarks' => 'Quarterly preventive maintenance.']],
            ],
        ];

        $items = [];
        $allTransactions = [];
        $logs = [];

        foreach ($itemDefs as $index => $def) {
            $itemCode = 'INV-' . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);
            $createdAt = now()->subDays(45)->setTime(8, 0);

            $item = Item::create([
                'item_code'     => $itemCode,
                'name'          => $def['name'],
                'category_id'   => $categories[$def['category']]->id,
                'description'   => $def['description'],
                'quantity'      => $def['opening'],
                'unit'          => $def['unit'],
                'minimum_stock' => $def['minimumStock'],
                'location'      => $def['location'],
                'condition'     => $def['condition'],
                'created_by'    => $admin->id,
                'updated_by'    => $admin->id,
                'created_at'    => $createdAt,
                'updated_at'    => $createdAt,
            ]);
            $items[$def['name']] = $item;

            $allTransactions[] = [
                'type'          => 'Stock In',
                'item_id'       => $item->id,
                'quantity'      => $def['opening'],
                'balance_after' => $def['opening'],
                'date'          => $createdAt->copy()->addMinutes(5),
                'supplier'      => 'Opening balance',
                'reason'        => '',
                'remarks'       => 'Opening stock recorded when the item was added to the inventory.',
            ];

            $events = [];
            foreach ($def['events'] as $ev) {
                $events[] = [
                    'type'     => $ev['type'],
                    'qty'      => $ev['qty'],
                    'date'     => now()->subDays($ev['days'])->setTime(9 + ($index % 7), 10 + ($index % 40)),
                    'supplier' => $ev['supplier'] ?? '',
                    'reason'   => $ev['reason'] ?? '',
                    'remarks'  => $ev['remarks'] ?? '',
                ];
            }

            foreach ($borrowDefs as $b) {
                if ($b['item'] === $def['name']) {
                    $events[] = [
                        'type'     => 'Borrowed',
                        'qty'      => $b['qty'],
                        'date'     => now()->subDays($b['days'])->setTime(10, 0),
                        'supplier' => '',
                        'reason'   => "Borrowed by {$b['borrowerName']}",
                        'remarks'  => "Borrow reference {$b['ref']}.",
                    ];
                    if ($b['returnedDaysAgo'] !== null) {
                        $events[] = [
                            'type'     => 'Returned',
                            'qty'      => $b['qty'],
                            'date'     => now()->subDays($b['returnedDaysAgo'])->setTime(14, 30),
                            'supplier' => '',
                            'reason'   => "Returned by {$b['borrowerName']}",
                            'remarks'  => "Borrow reference {$b['ref']}.",
                        ];
                    }
                }
            }

            usort($events, fn ($a, $b) => $a['date']->timestamp <=> $b['date']->timestamp);

            $balance = $def['opening'];
            foreach ($events as $ev) {
                $delta = in_array($ev['type'], ['Stock In', 'Returned'], true) ? $ev['qty'] : -$ev['qty'];
                $balance = max(0, $balance + $delta);

                $allTransactions[] = [
                    'type'          => $ev['type'],
                    'item_id'       => $item->id,
                    'quantity'      => $ev['qty'],
                    'balance_after' => $balance,
                    'date'          => $ev['date'],
                    'supplier'      => $ev['supplier'],
                    'reason'        => $ev['reason'],
                    'remarks'       => $ev['remarks'],
                ];
            }

            $item->update(['quantity' => $balance]);

            $logs[] = [
                'action'       => 'Added inventory item',
                'entity_type'  => 'Item',
                'entity_id'    => (string) $item->id,
                'entity_label' => "{$itemCode} · {$def['name']}",
                'details'      => "Opening quantity {$def['opening']} {$def['unit']} in {$def['category']}",
                'created_at'   => $createdAt->copy()->addMinutes(5),
            ];
        }

        usort($allTransactions, fn ($a, $b) => $a['date']->timestamp <=> $b['date']->timestamp);
        foreach ($allTransactions as $seq => $txn) {
            StockTransaction::create([
                'reference'     => 'TXN-' . str_pad((string) ($seq + 1), 5, '0', STR_PAD_LEFT),
                'type'          => $txn['type'],
                'item_id'       => $txn['item_id'],
                'quantity'      => $txn['quantity'],
                'balance_after' => $txn['balance_after'],
                'date'          => $txn['date'],
                'supplier'      => $txn['supplier'],
                'reason'        => $txn['reason'],
                'remarks'       => $txn['remarks'],
                'recorded_by'   => $admin->id,
                'created_at'    => $txn['date'],
                'updated_at'    => $txn['date'],
            ]);
        }

        // 7. Borrow Records (6)
        foreach ($borrowDefs as $b) {
            $item = $items[$b['item']];
            $dateBorrowed = now()->subDays($b['days'])->setTime(10, 0);
            $expectedReturn = now()->addDays($b['expectedInDays'])->setTime(17, 0);
            $actualReturn = $b['returnedDaysAgo'] !== null ? now()->subDays($b['returnedDaysAgo'])->setTime(14, 30) : null;

            $record = BorrowRecord::create([
                'reference'            => $b['ref'],
                'borrower_name'        => $b['borrowerName'],
                'borrower_id_number'   => $b['borrowerIdNumber'],
                'item_id'              => $item->id,
                'quantity'             => $b['qty'],
                'date_borrowed'        => $dateBorrowed,
                'expected_return_date' => $expectedReturn,
                'actual_return_date'   => $actualReturn,
                'status'               => $actualReturn ? 'Returned' : 'Borrowed',
                'remarks'              => $b['remarks'],
                'recorded_by'          => $admin->id,
                'returned_by'          => $actualReturn ? $admin->id : null,
                'created_at'           => $dateBorrowed,
                'updated_at'           => $actualReturn ?? $dateBorrowed,
            ]);

            $logs[] = [
                'action'       => 'Recorded borrowed item',
                'entity_type'  => 'Borrow',
                'entity_id'    => (string) $record->id,
                'entity_label' => "{$record->reference} · {$item->name}",
                'details'      => "{$b['borrowerName']} borrowed {$b['qty']} {$item->unit}",
                'created_at'   => $dateBorrowed->copy()->addMinutes(5),
            ];
        }

        // 8. Extra Activity Logs
        $extraLogs = [
            ['action' => 'Recorded stock in', 'entity_type' => 'Item', 'entity_label' => 'INV-0001 · USB Wired Keyboard', 'details' => 'Stock In of 20 pcs. New balance: 44.', 'created_at' => now()->subDays(21)->setTime(11, 20)],
            ['action' => 'Recorded stock out', 'entity_type' => 'Item', 'entity_label' => 'INV-0015 · Bond Paper (A4)', 'details' => 'Stock Out of 30 reams. New balance: 80.', 'created_at' => now()->subDays(2)->setTime(13, 40)],
            ['action' => 'Updated inventory item', 'entity_type' => 'Item', 'entity_label' => 'INV-0008 · HDMI Cable 1.5m', 'details' => 'Item details updated. Minimum stock level: 8 pcs.', 'created_at' => now()->subDays(4)->setTime(15, 10)],
            ['action' => 'Updated system settings', 'entity_type' => 'Settings', 'entity_label' => 'Inventory settings', 'details' => 'Low-stock default threshold: 10; default borrowing period: 7 day(s)', 'created_at' => now()->subDays(30)->setTime(10, 15)],
            ['action' => 'Recorded returned item', 'entity_type' => 'Borrow', 'entity_label' => 'BRW-0004 · Extension Cord (4-socket)', 'details' => 'Prof. Ramon Cruz returned 1 pcs', 'created_at' => now()->subDays(10)->setTime(14, 35)],
            ['action' => 'Generated inventory report', 'entity_type' => 'Report', 'entity_label' => 'Complete inventory report', 'details' => 'Printed for the quarterly inventory review', 'created_at' => now()->subDays(6)->setTime(9, 45)],
        ];

        foreach ([...$logs, ...$extraLogs] as $entry) {
            ActivityLog::create([
                'actor_id'       => $admin->id,
                'actor_name'     => $admin->name,
                'actor_username' => $admin->username,
                'actor_role'     => $admin->role,
                'action'         => $entry['action'],
                'entity_type'    => $entry['entity_type'] ?? '',
                'entity_id'      => $entry['entity_id'] ?? '',
                'entity_label'   => $entry['entity_label'] ?? '',
                'details'        => $entry['details'] ?? '',
                'ip'             => '127.0.0.1',
                'user_agent'     => 'seed-script',
                'created_at'     => $entry['created_at'],
                'updated_at'     => $entry['created_at'],
            ]);
        }
    }
}