<?php

namespace App\Filament\Resources\SalesEmployees\Pages;

use App\Filament\Resources\SalesEmployees\SalesEmployeeResource;
use App\Models\Invoice;
use App\Models\SalesEmployee;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * A salesperson, measured by what they have actually sold.
 *
 * Answers the questions somebody opens a sales employee to ask: how much have
 * they written, which customers do they bring in, and what is still owed on
 * the documents they raised.
 */
class ViewSalesEmployee extends ViewRecord
{
    protected static string $resource = SalesEmployeeResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->name;
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            ViewEntry::make('hero')
                ->hiddenLabel()
                ->view('filament.partials.employee-hero')
                ->columnSpanFull(),

            Grid::make(['default' => 1, 'lg' => 2])->schema([
                Section::make('Customers')
                    ->description('Who this salesperson writes for, by value.')
                    ->icon('heroicon-o-building-storefront')
                    ->schema([
                        ViewEntry::make('customers')
                            ->hiddenLabel()
                            ->view('filament.partials.employee-customers'),
                    ]),

                Section::make('Documents')
                    ->description('Everything they have raised, most recent first.')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        ViewEntry::make('orders')
                            ->hiddenLabel()
                            ->view('filament.partials.employee-orders'),
                    ]),
            ])->columnSpanFull(),
        ]);
    }

    /**
     * Drafts are excluded throughout: a draft is not a sale, and counting one
     * would flatter the figures.
     *
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        /** @var SalesEmployee $employee */
        $employee = $this->getRecord();

        $posted = Invoice::where('sales_employee_id', $employee->getKey())
            ->where('doc_type', Invoice::TYPE_INVOICE);

        return [
            'documents' => (clone $posted)->count(),
            'drafts' => Invoice::where('sales_employee_id', $employee->getKey())
                ->where('doc_type', Invoice::TYPE_DRAFT)
                ->count(),
            'sold' => (clone $posted)->sum('document_total'),
            'outstanding' => (clone $posted)->sum('balance_due'),
            'awaitingApproval' => (clone $posted)->where('status', Invoice::STATUS_PENDING_APPROVAL)->count(),
            'customers' => (clone $posted)->distinct()->count('customer_id'),
            'lastRaised' => (clone $posted)->latest('posting_date')->first()?->posting_date,
        ];
    }
}
