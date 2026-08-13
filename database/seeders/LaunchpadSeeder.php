<?php

namespace Database\Seeders;

use App\Filament\Pages\ArInvoice;
use App\Filament\Pages\VehicleGateIn;
use App\Filament\Pages\VehicleGateOut;
use App\Filament\Resources\Approvals\ApprovalRequestResource;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\GateLogs\GateLogResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\MpesaTransactions\MpesaTransactionResource;
use App\Filament\Resources\Trips\TripResource;
use App\Filament\Resources\Users\UserResource;
use Filament\Launchpad\Models\Card;
use Filament\Launchpad\Models\Page;
use Filament\Launchpad\Models\Section;
use Filament\Launchpad\Models\Space;
use Illuminate\Database\Seeder;

/**
 * The panel's landing page.
 *
 * Launchpad claims the panel root, and an unconfigured launchpad renders
 * nothing at all — no tiles, no empty state, no way in. Every user would sign
 * in to a blank page. This gives the root a real set of shortcuts.
 *
 * Cards are not gated here: the plugin defers to each target's own
 * canAccess(), which is the same gate the navigation uses. A driver therefore
 * sees only the tiles they could already reach, without the roles being
 * restated in a second place that could drift.
 */
class LaunchpadSeeder extends Seeder
{
    public function run(): void
    {
        $space = Space::query()->firstOrCreate(
            ['label' => 'GIL Business Suite'],
            ['icon' => 'heroicon-o-squares-2x2', 'is_default' => true, 'sort' => 0],
        );

        $page = Page::query()->firstOrCreate(
            ['space_id' => $space->getKey(), 'label' => 'Home'],
            ['icon' => 'heroicon-o-home', 'sort' => 0],
        );

        $groups = [
            'Sales' => [
                ['A/R Invoice', 'Raise a new document', 'heroicon-o-document-plus', 'page', ArInvoice::class],
                ['Invoice Register', 'Every posted document', 'heroicon-o-rectangle-stack', 'resource', InvoiceResource::class],
                ['Approvals', 'Documents over the threshold', 'heroicon-o-check-badge', 'resource', ApprovalRequestResource::class],
                ['Payments', 'M-Pesa receipts and matching', 'heroicon-o-banknotes', 'resource', MpesaTransactionResource::class],
            ],
            'Operations' => [
                ['Gate In', 'Admit a vehicle', 'heroicon-o-arrow-right-on-rectangle', 'page', VehicleGateIn::class],
                ['Gate Out', 'Release a vehicle', 'heroicon-o-arrow-left-on-rectangle', 'page', VehicleGateOut::class],
                ['Gate Log', 'Movements and time on site', 'heroicon-o-clipboard-document-list', 'resource', GateLogResource::class],
                ['Trips', 'Plan and track deliveries', 'heroicon-o-truck', 'resource', TripResource::class],
            ],
            'Administration' => [
                ['Users', 'Accounts, roles and drivers', 'heroicon-o-users', 'resource', UserResource::class],
                ['Audit Log', 'Every change, and who made it', 'heroicon-o-shield-check', 'resource', AuditLogResource::class],
            ],
        ];

        $sectionSort = 0;

        foreach ($groups as $title => $cards) {
            $section = Section::query()->firstOrCreate(
                ['page_id' => $page->getKey(), 'title' => $title, 'user_id' => null],
                ['sort' => $sectionSort++],
            );

            foreach ($cards as $sort => [$label, $subtitle, $icon, $targetType, $target]) {
                // A resource that was removed should not take the seeder down
                // with it, and a card pointing at nothing is worse than none.
                if (! class_exists($target)) {
                    continue;
                }

                $card = Card::query()->firstOrCreate(
                    ['title' => $label, 'target_type' => $targetType, 'target_value' => $target],
                    [
                        'subtitle' => $subtitle,
                        'icon' => $icon,
                        'type' => 'shortcut',
                        'sort' => $sort,
                    ],
                );

                $section->cards()->syncWithoutDetaching([
                    $card->getKey() => ['sort' => $sort],
                ]);
            }
        }
    }
}
