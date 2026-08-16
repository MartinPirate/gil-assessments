<?php

namespace Database\Seeders;

use App\Filament\Pages\ArInvoice;
use App\Filament\Pages\MyTrips;
use App\Filament\Pages\VehicleGateIn;
use App\Filament\Pages\VehicleGateOut;
use App\Filament\Resources\Approvals\ApprovalRequestResource;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\GateLogs\GateLogResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Items\ItemResource;
use App\Filament\Resources\MpesaTransactions\MpesaTransactionResource;
use App\Filament\Resources\Routes\RouteResource;
use App\Filament\Resources\Users\UserResource;
use Filament\Facades\Filament;
use Illuminate\Database\Seeder;
use Wallacemartinss\FilamentOnboarding\Enums\CompletionMode;
use Wallacemartinss\FilamentOnboarding\Enums\StepType;
use Wallacemartinss\FilamentOnboarding\Models\OnboardingFlow;
use Wallacemartinss\FilamentOnboarding\Models\OnboardingStep;

/**
 * A first-day checklist per role.
 *
 * The onboarding plugin is registered but ships with no content, and an empty
 * flow list renders nothing — so the feature was installed and invisible. Each
 * journey below is written for one role and gated on the capability that role
 * already has, so a driver is walked through their trips rather than through
 * raising an invoice they cannot open.
 *
 * Steps complete by visit: the checklist ticks itself as the person actually
 * goes to the screen, which is the point. Nothing here has to be marked done
 * by hand.
 */
class OnboardingSeeder extends Seeder
{
    public function run(): void
    {
        // Resolving a page's URL needs a panel in hand; a seeder has none.
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        foreach ($this->flows() as $flow) {
            $record = OnboardingFlow::updateOrCreate(
                ['key' => $flow['key']],
                [
                    'panel_id' => 'admin',
                    'title' => $flow['title'],
                    'description' => $flow['description'],
                    'icon' => $flow['icon'],
                    'visibility_condition' => $flow['condition'],
                    'is_active' => true,
                    'is_dismissible' => true,
                    'sort_order' => $flow['sort'],
                ],
            );

            $keys = [];

            foreach ($flow['steps'] as $index => [$title, $description, $icon, $target]) {
                $path = $this->pathTo($target);
                $keys[] = $flow['key'].'.'.($index + 1);

                OnboardingStep::updateOrCreate(
                    ['flow_id' => $record->getKey(), 'key' => $flow['key'].'.'.($index + 1)],
                    [
                        'type' => StepType::Task,
                        'title' => $title,
                        'description' => $description,
                        'icon' => $icon,
                        'cta_label' => 'Open',
                        'cta_url' => $path,
                        'completion_mode' => CompletionMode::Visit,
                        'visit_url' => $path,
                        'is_required' => true,
                        'is_active' => true,
                        'sort_order' => $index,
                    ],
                );
            }

            /*
             * A step dropped from the journey has to be dropped from the
             * database too. Re-seeding otherwise leaves the old row behind —
             * which is how the gate officer's journey kept a "Check the trips"
             * card pointing at a screen that now answers 403.
             */
            OnboardingStep::query()
                ->where('flow_id', $record->getKey())
                ->whereNotIn('key', $keys)
                ->delete();
        }
    }

    /**
     * A panel-relative path. visit_url is matched against the path the browser
     * is on, so an absolute URL carrying the host would never match.
     *
     * @param  class-string  $target
     */
    protected function pathTo(string $target): string
    {
        return (string) parse_url($target::getUrl(), PHP_URL_PATH);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function flows(): array
    {
        return [
            [
                'key' => 'sales-first-day',
                'title' => 'Getting started in Sales',
                'description' => 'Raise your first document and learn where it goes afterwards.',
                'icon' => 'heroicon-o-document-plus',
                'condition' => 'can-sell',
                'sort' => 1,
                'steps' => [
                    ['Raise an A/R Invoice', 'The document screen: pick a customer, add lines, save.', 'heroicon-o-document-plus', ArInvoice::class],
                    ['Find it in the register', 'Every posted document, searchable by number, customer or status.', 'heroicon-o-rectangle-stack', InvoiceResource::class],
                    ['See how it gets paid', 'M-Pesa receipts arrive here and settle against invoices.', 'heroicon-o-banknotes', MpesaTransactionResource::class],
                ],
            ],
            [
                'key' => 'manager-first-day',
                'title' => 'Getting started as a Manager',
                'description' => 'Anything over the threshold waits for you.',
                'icon' => 'heroicon-o-check-badge',
                'condition' => 'can-approve',
                'sort' => 2,
                'steps' => [
                    ['Open the approval queue', 'Documents above the threshold, oldest first.', 'heroicon-o-check-badge', ApprovalRequestResource::class],
                    ['Look at a document in full', 'The register shows what you are approving, line by line.', 'heroicon-o-rectangle-stack', InvoiceResource::class],
                ],
            ],
            [
                'key' => 'gate-first-day',
                'title' => 'Getting started at the Gate',
                'description' => 'Admitting and releasing vehicles, and reading the log.',
                'icon' => 'heroicon-o-arrow-right-on-rectangle',
                'condition' => 'can-operate-gate',
                'sort' => 3,
                'steps' => [
                    ['Admit a vehicle', 'Pick the vehicle and driver — time and your name are captured for you.', 'heroicon-o-arrow-right-on-rectangle', VehicleGateIn::class],
                    ['Release one', 'Only vehicles currently on site are offered.', 'heroicon-o-arrow-left-on-rectangle', VehicleGateOut::class],
                    // No trips step: planning moved off the gate officer's
                    // desk, and a journey that walks somebody to a 403 is
                    // worse than a shorter journey.
                    ['Read the gate log', 'Every movement, with time on site.', 'heroicon-o-clipboard-document-list', GateLogResource::class],
                ],
            ],
            [
                'key' => 'driver-first-day',
                'title' => 'Getting started as a Driver',
                'description' => 'Your trips, and nothing else.',
                'icon' => 'heroicon-o-truck',
                'condition' => 'is-driver',
                'sort' => 4,
                'steps' => [
                    ['Open My Trips', 'Start and finish your runs from here, on a phone at the gate.', 'heroicon-o-truck', MyTrips::class],
                    ['Read your route', 'The legs you are sent down — stops, distance and hours.', 'heroicon-o-map', RouteResource::class],
                    ['Check your gate movements', 'When you came on site and when you left.', 'heroicon-o-clipboard-document-list', GateLogResource::class],
                ],
            ],
            [
                'key' => 'admin-first-day',
                'title' => 'Getting started as an Administrator',
                'description' => 'The master data everything else is built on.',
                'icon' => 'heroicon-o-cog-6-tooth',
                'condition' => 'can-administer',
                'sort' => 5,
                'steps' => [
                    ['Review the customers', 'Contacts, delivery location and the default contact person.', 'heroicon-o-building-storefront', CustomerResource::class],
                    ['Review the items', 'What is sold, at what price, out of which warehouse.', 'heroicon-o-cube', ItemResource::class],
                    ['Set up the people', 'Accounts and roles — a driver record is created with its login.', 'heroicon-o-users', UserResource::class],
                    ['Know where the audit log is', 'Every change, who made it and from where.', 'heroicon-o-shield-check', AuditLogResource::class],
                ],
            ],
        ];
    }
}
