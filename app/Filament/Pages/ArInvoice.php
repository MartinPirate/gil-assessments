<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Concerns\InteractsWithChooseFromList;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\VatCode;
use App\Models\Warehouse;
use App\Services\DocumentNumberService;
use App\Services\InvoiceWriter;
use App\Support\ChooseFromListRegistry;
use App\Support\InvoiceCalculator;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use JeffersonGoncalves\Filament\BarcodeField\Forms\Components\BarcodeInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

/**
 * Task 1 — the A/R Invoice entry screen.
 *
 * Mirrors the supplied SAP Business One document: business-partner block
 * top-left, numbering and dates top-right, the tabbed content area with the
 * line grid, and the sales-employee / remarks / totals footer.
 */
class ArInvoice extends Page implements HasForms
{
    use InteractsWithChooseFromList;
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'A/R Invoice';

    protected static string|\UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'A/R Invoice';

    protected string $view = 'filament.pages.ar-invoice';

    /** @var array<string, mixed> */
    public array $data = [];

    /** The number the next saved document will receive. */
    public int $nextDocNum = 1;

    public static function canAccess(): bool
    {
        return Auth::user()?->role()->canSell() ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->refreshNextDocNum();
        $this->form->fill($this->blankDocument());
    }

    protected function refreshNextDocNum(): void
    {
        $this->nextDocNum = app(DocumentNumberService::class)
            ->peek(DocumentNumberService::AR_INVOICE, 'IN');
    }

    /**
     * @return array<string, mixed>
     */
    protected function blankDocument(): array
    {
        $today = now()->toDateString();

        return [
            'series' => 'IN',
            'status' => Invoice::STATUS_OPEN,
            'posting_date' => $today,
            'value_date' => $today,
            'document_date' => $today,
            'currency' => 'KES',
            'summary_type' => 'No Summary',
            'payment_order_run' => false,
            'discount_percent' => 0,
            'freight' => 0,
            'total_down_payment' => 0,
            'rounding_enabled' => false,
            'total_before_discount' => 0,
            'total_after_discount' => 0,
            'tax_total' => 0,
            'rounding' => 0,
            'document_total' => 0,
            'applied_amount' => 0,
            'balance_due' => 0,
            'owner_name' => Auth::user()?->name,
            // The sample screen always shows one empty row ready for entry.
            'lines' => [$this->blankLine()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function blankLine(): array
    {
        $vat = VatCode::default();

        return [
            'item_service_type' => 'Item',
            'item_id' => null,
            'item_no' => null,
            'item_description' => null,
            'uom' => null,
            'warehouse' => Warehouse::default()?->code ?? 'FG WHS',
            'qty_in_warehouse' => null,
            'quantity' => null,
            'price_before_discount' => null,
            'discount_percent' => 0,
            'price_after_discount' => null,
            'vat_code_id' => $vat?->getKey(),
            'vat_rate' => (float) ($vat?->rate ?? 0),
            'vat_amount' => 0,
            'gross_price_after_discount' => null,
            'total' => null,
            'gross_total' => null,
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                $this->headerSection(),
                $this->contentTabs(),
                $this->footerSection(),
            ]);
    }

    /* -----------------------------------------------------------------
     | Header
     | ----------------------------------------------------------------- */

    protected function headerSection(): Section
    {
        return Section::make()
            ->extraAttributes(['class' => 'sap-header'])
            ->schema([
                Grid::make(['default' => 1, 'lg' => 2])->schema([

                    // Left: business partner
                    Grid::make(1)->schema([
                        Select::make('customer_id')
                            ->label('Customer')
                            ->placeholder('Type to search a customer code…')
                            ->searchable()
                            ->required()
                            ->getSearchResultsUsing(fn (string $search) => ChooseFromListRegistry::search('customers_by_code', $search))
                            ->getOptionLabelUsing(fn ($value) => ChooseFromListRegistry::optionLabel('customers_by_code', $value))
                            ->suffixAction($this->chooseFromListAction('customers_by_code'))
                            ->live()
                            ->afterStateUpdated(fn (?string $state, Set $set) => $this->applyCustomer($state, $set)),

                        Select::make('customer_name_lookup')
                            ->label('Name')
                            ->placeholder('Type to search a customer name…')
                            ->searchable()
                            ->dehydrated(false)
                            ->getSearchResultsUsing(fn (string $search) => ChooseFromListRegistry::search('customers_by_name', $search))
                            ->getOptionLabelUsing(fn ($value) => ChooseFromListRegistry::optionLabel('customers_by_name', $value))
                            ->suffixAction($this->chooseFromListAction('customers_by_name'))
                            ->live()
                            ->afterStateUpdated(fn (?string $state, Set $set) => $this->applyCustomer($state, $set)),

                        TextInput::make('contact_person')->label('Contact Person')->disabled()->dehydrated(false),

                        // Distinct from the BP's "Name": this is the name
                        // printed on this document, which for a walk-in
                        // customer differs from the master record.
                        TextInput::make('customer_display_name')
                            ->label('Customer Name')
                            ->maxLength(150)
                            ->live(onBlur: true)
                            ->extraAttributes(['class' => 'sap-field--emphasis']),

                        TextInput::make('currency')->label('BP Currency')->disabled()->dehydrated(false),

                        TextInput::make('kra_pin')
                            ->label('KRA PIN')
                            ->disabled()
                            ->dehydrated(false)
                            ->extraAttributes(['class' => 'sap-field--emphasis']),
                    ]),

                    // Right: document numbering and dates
                    Grid::make(1)->schema([
                        Grid::make(['default' => 2])->schema([
                            Select::make('series')
                                ->label('No.')
                                ->options(['IN' => 'IN', 'CR' => 'CR'])
                                ->default('IN')
                                ->selectablePlaceholder(false)
                                ->live()
                                ->afterStateUpdated(fn () => $this->refreshNextDocNum()),

                            TextInput::make('doc_num_display')
                                ->hiddenLabel()
                                ->disabled()
                                ->dehydrated(false)
                                ->formatStateUsing(fn () => $this->nextDocNum),
                        ]),

                        TextInput::make('status')->label('Status')->disabled()->dehydrated(false),

                        DatePicker::make('posting_date')
                            ->label('Posting Date')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->required()
                            ->live(onBlur: true)
                            // The other two dates follow the posting date until
                            // the user deliberately changes them.
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set) {
                                if (blank($get('value_date'))) {
                                    $set('value_date', $state);
                                }
                                if (blank($get('document_date'))) {
                                    $set('document_date', $state);
                                }
                            }),

                        DatePicker::make('value_date')
                            ->label('Value Date')
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        DatePicker::make('document_date')
                            ->label('Document Date')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ]),
                ]),

                $this->approvalNotice(),
            ]);
    }

    /**
     * Task 1b: a label that only appears once Total Amount exceeds 10,000.
     */
    protected function approvalNotice(): \Filament\Schemas\Components\View
    {
        return \Filament\Schemas\Components\View::make('filament.pages.partials.approval-notice')
            ->visible(fn (Get $get): bool => (float) ($get('document_total') ?? 0) > Invoice::APPROVAL_THRESHOLD);
    }

    /* -----------------------------------------------------------------
     | Content tabs
     | ----------------------------------------------------------------- */

    protected function contentTabs(): Tabs
    {
        return Tabs::make('Content')
            ->extraAttributes(['class' => 'sap-contents'])
            ->columnSpanFull()
            ->tabs([
                Tab::make('Contents')->schema([
                    // The strip the sample shows above the grid: document-level
                    // Item/Service Type on the left, Summary Type on the right.
                    Grid::make(['default' => 1, 'md' => 2])
                        ->extraAttributes(['class' => 'sap-grid-strip'])
                        ->schema([
                            Select::make('item_service_type')
                                ->label('Item/Service Type')
                                ->options(['Item' => 'Item', 'Service' => 'Service'])
                                ->default('Item')
                                ->selectablePlaceholder(false),

                            Select::make('summary_type')
                                ->label('Summary Type')
                                ->options(['No Summary' => 'No Summary', 'By Items' => 'By Items', 'By Document' => 'By Document'])
                                ->default('No Summary')
                                ->selectablePlaceholder(false)
                                ->extraAttributes(['class' => 'sap-grid-strip__right']),
                        ]),

                    $this->linesRepeater(),
                ]),
                Tab::make('Logistics')->schema($this->logisticsFields()),
                Tab::make('Accounting')->schema($this->accountingFields()),
                Tab::make('Attachments')->schema($this->attachmentFields()),
                Tab::make('TIMS')->schema($this->timsFields()),
                Tab::make('ETIMS')->schema($this->etimsFields()),
            ]);
    }

    protected function linesRepeater(): Repeater
    {
        return Repeater::make('lines')
            ->hiddenLabel()
            ->addActionLabel('Add Row')
            ->defaultItems(1)
            ->reorderable(false)
            ->columnSpanFull()
            ->live(onBlur: true)
            ->afterStateUpdated(fn () => $this->recalculateAllTotals())
            ->deleteAction(fn (Action $action) => $action->after(fn () => $this->recalculateAllTotals()))
            ->table([
                // Row number, filled by a CSS counter — Filament repeaters do
                // not expose the item index to the schema.
                TableColumn::make('#')->width('44px'),
                TableColumn::make('Item No.')->width('180px'),
                TableColumn::make('Item Description')->width('260px'),
                TableColumn::make('Quantity')->width('100px')->alignEnd(),
                TableColumn::make('Whse')->width('110px'),
                TableColumn::make('Qty in Whse')->width('110px')->alignEnd(),
                TableColumn::make('UoM Code')->width('90px'),
                TableColumn::make('Unit Price')->width('130px')->alignEnd(),
                TableColumn::make('Discount %')->width('100px')->alignEnd(),
                TableColumn::make('Price after Discount')->width('140px')->alignEnd(),
                TableColumn::make('VAT Code')->width('120px'),
                TableColumn::make('Gross Price after Disc.')->width('150px')->alignEnd(),
                TableColumn::make('Total (LC)')->width('140px')->alignEnd(),
                TableColumn::make('Gross Total (LC)')->width('150px')->alignEnd(),
            ])
            ->schema([
                // Placeholder cell; the number itself comes from CSS.
                \Filament\Schemas\Components\Text::make('')
                    ->extraAttributes(['class' => 'sap-rownum']),

                Select::make('item_id')
                    ->hiddenLabel()
                    ->placeholder('Item No.')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => ChooseFromListRegistry::search('items', $search))
                    ->getOptionLabelUsing(fn ($value) => Item::find($value)?->item_no)
                    ->suffixAction($this->chooseFromListAction('items'))
                    ->live()
                    ->afterStateUpdated(fn (?string $state, Set $set, Get $get) => $this->applyItem($state, $set, $get)),

                TextInput::make('item_description')
                    ->hiddenLabel()
                    ->placeholder('Item Description')
                    ->maxLength(200)
                    ->live(onBlur: true),

                TextInput::make('quantity')
                    ->hiddenLabel()
                    ->numeric()                 // Task 1d: numeric only
                    ->minValue(0)
                    ->step('0.001')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set) => $this->recalculateLine($get, $set)),

                Select::make('warehouse')
                    ->hiddenLabel()
                    ->options(fn () => Warehouse::query()->where('is_active', true)->pluck('code', 'code'))
                    ->searchable()
                    ->suffixIcon(Heroicon::ArrowRightCircle)
                    ->suffixIconColor('warning'),

                TextInput::make('qty_in_warehouse')
                    ->hiddenLabel()
                    ->disabled()
                    ->dehydrated(),

                TextInput::make('uom')->hiddenLabel()->disabled()->dehydrated(),

                TextInput::make('price_before_discount')
                    ->hiddenLabel()
                    ->numeric()
                    ->minValue(0)
                    ->step('0.0001')
                    ->prefix('KES')
                    ->extraInputAttributes(['class' => 'sap-money'])
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set) => $this->recalculateLine($get, $set)),

                TextInput::make('discount_percent')
                    ->hiddenLabel()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->step('0.000001')
                    ->extraInputAttributes(['class' => 'sap-money'])
                    ->live(onBlur: true)
                    // Task 1e: discount over 50 is an error.
                    ->rules(['numeric', 'max:50'])
                    ->validationMessages(['max' => 'Discount cannot exceed 50%.'])
                    ->afterStateUpdated(fn (Get $get, Set $set) => $this->recalculateLine($get, $set)),

                TextInput::make('price_after_discount')->hiddenLabel()->readOnly()->dehydrated()
                    ->prefix('KES')->extraInputAttributes(['class' => 'sap-money']),

                Select::make('vat_code_id')
                    ->hiddenLabel()
                    ->options(fn () => VatCode::query()->where('is_active', true)->pluck('code', 'id'))
                    ->default(fn () => VatCode::default()?->getKey())
                    ->selectablePlaceholder(false)
                    ->live()
                    ->afterStateUpdated(fn (?string $state, Get $get, Set $set) => $this->applyVatCode($state, $get, $set)),

                TextInput::make('gross_price_after_discount')->hiddenLabel()->readOnly()->dehydrated()
                    ->prefix('KES')->extraInputAttributes(['class' => 'sap-money']),
                TextInput::make('total')->hiddenLabel()->readOnly()->dehydrated(false)
                    ->prefix('KES')->extraInputAttributes(['class' => 'sap-money']),
                TextInput::make('gross_total')->hiddenLabel()->readOnly()->dehydrated(false)
                    ->prefix('KES')->extraInputAttributes(['class' => 'sap-money']),
            ]);
    }

    /**
     * @return array<int, mixed>
     */
    protected function logisticsFields(): array
    {
        return [
            Grid::make(['default' => 1, 'md' => 2])->schema([
                Textarea::make('ship_to')->label('Ship To')->rows(3)->dehydrated(false),
                Textarea::make('bill_to')->label('Bill To')->rows(3)->dehydrated(false),
                Select::make('shipping_type')
                    ->label('Shipping Type')
                    ->options(['Company Vehicle' => 'Company Vehicle', 'Courier' => 'Courier', 'Customer Collect' => 'Customer Collect'])
                    ->dehydrated(false),
                TextInput::make('vehicle_reference')->label('Vehicle Reference')->dehydrated(false),
            ]),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected function accountingFields(): array
    {
        return [
            Grid::make(['default' => 1, 'md' => 2])->schema([
                Select::make('payment_terms')
                    ->label('Payment Terms')
                    ->options(['Cash' => 'Cash', 'Net 30' => 'Net 30', 'Net 60' => 'Net 60'])
                    ->dehydrated(false),
                TextInput::make('journal_remarks')->label('Journal Remarks')->dehydrated(false),
                TextInput::make('control_account')->label('Control Account')->dehydrated(false),
            ]),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected function attachmentFields(): array
    {
        return [
            Grid::make(1)->schema([
                \Filament\Forms\Components\FileUpload::make('attachments')
                    ->label('Supporting documents')
                    ->multiple()
                    ->directory('invoice-attachments')
                    ->maxFiles(10)
                    ->maxSize(5120)
                    // Deliberately restrictive: an invoice attachment has no
                    // reason to be an executable or an archive.
                    ->acceptedFileTypes(['application/pdf', 'image/png', 'image/jpeg'])
                    ->helperText('PDF, PNG or JPEG, up to 5 MB each.')
                    ->dehydrated(false),
            ]),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected function timsFields(): array
    {
        return [
            Grid::make(['default' => 1, 'md' => 2])->schema([
                TextInput::make('tims_invoice_number')
                    ->label('TIMS Invoice Number')
                    ->disabled()->dehydrated(false)
                    ->helperText('Assigned by the KRA control unit once the document is transmitted.'),
                TextInput::make('tims_device_serial')->label('Control Unit Serial')->disabled()->dehydrated(false),
                TextInput::make('tims_signature')->label('Control Unit Signature')->disabled()->dehydrated(false),
                TextInput::make('tims_transmitted_at')->label('Transmitted At')->disabled()->dehydrated(false),
            ]),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected function etimsFields(): array
    {
        return [
            Grid::make(['default' => 1, 'md' => 2])->schema([
                TextInput::make('etims_status')
                    ->label('eTIMS Status')
                    ->disabled()->dehydrated(false)
                    ->default('Not transmitted'),
                TextInput::make('etims_receipt_number')->label('eTIMS Receipt Number')->disabled()->dehydrated(false),
                TextInput::make('etims_qr_url')->label('eTIMS QR URL')->disabled()->dehydrated(false),
                TextInput::make('etims_error')->label('Last Transmission Error')->disabled()->dehydrated(false),

                /*
                 * The one TIMS field a person fills in. Everything above is
                 * assigned by the KRA control unit on transmission; this is
                 * scanned off the paper receipt the ETR prints, which is what
                 * ties that receipt back to this document.
                 *
                 * Typed entry still works when the camera will not read a
                 * creased receipt, so the scanner is an affordance rather than
                 * the only way in.
                 */
                BarcodeInput::make('etr_barcode')
                    ->label('ETR Receipt Barcode')
                    ->icon('heroicon-m-qr-code')
                    ->maxLength(64)
                    ->helperText('Scan the barcode on the ETR receipt, or type it in.')
                    ->columnSpanFull(),
            ]),
        ];
    }

    /* -----------------------------------------------------------------
     | Footer
     | ----------------------------------------------------------------- */

    protected function footerSection(): Section
    {
        return Section::make()
            ->extraAttributes(['class' => 'sap-footer'])
            ->schema([
                Grid::make(['default' => 1, 'lg' => 2])->schema([

                    Grid::make(1)->schema([
                        Select::make('sales_employee_id')
                            ->label('Sales Employee')
                            ->placeholder('Type to search…')
                            ->searchable()
                            ->required()
                            ->getSearchResultsUsing(fn (string $search) => ChooseFromListRegistry::search('sales_employees', $search))
                            ->getOptionLabelUsing(fn ($value) => ChooseFromListRegistry::optionLabel('sales_employees', $value))
                            ->suffixAction($this->chooseFromListAction('sales_employees')),

                        TextInput::make('owner_name')
                            ->label('Owner')
                            ->disabled()
                            ->dehydrated(false)
                            ->suffixIcon(Heroicon::ArrowRightCircle)
                            ->suffixIconColor('warning'),

                        Checkbox::make('payment_order_run')->label('Payment Order Run'),

                        Textarea::make('remarks')
                            ->label('Remarks')
                            ->rows(4)
                            ->required()                 // Task 1e: mandatory
                            ->maxLength(1000)
                            // Committed on blur so opening a Choose From List
                            // modal afterwards cannot discard the text.
                            ->live(onBlur: true)
                            ->validationMessages(['required' => 'Remarks are required.']),

                        // Populated by the KRA control unit once transmitted;
                        // shown here because the sample document has the box.
                        TextInput::make('qr_code')
                            ->label('QRCode')
                            ->disabled()
                            ->dehydrated()
                            ->placeholder('Assigned on eTIMS transmission')
                            ->extraAttributes(['class' => 'sap-qrcode']),
                    ]),

                    Grid::make(1)->extraAttributes(['class' => 'sap-totals'])->schema([
                        TextInput::make('total_before_discount')->label('Total Before Discount')->readOnly()->prefix('KES'),

                        TextInput::make('discount_percent')
                            ->label('Discount')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step('0.000001')
                            ->suffix('%')
                            ->live(onBlur: true)
                            ->rules(['numeric', 'max:50'])
                            ->validationMessages(['max' => 'Discount cannot exceed 50%.'])
                            ->afterStateUpdated(fn () => $this->recalculateAllTotals()),

                        TextInput::make('total_after_discount')->label('Total After Discount')->readOnly()->prefix('KES'),

                        TextInput::make('total_down_payment')
                            ->label('Total Down Payment')
                            ->numeric()->minValue(0)->step('0.0001')->prefix('KES')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn () => $this->recalculateAllTotals()),

                        TextInput::make('freight')
                            ->label('Freight')
                            ->numeric()->minValue(0)->step('0.0001')->prefix('KES')
                            ->suffixIcon(Heroicon::ArrowRightCircle)
                            ->suffixIconColor('warning')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn () => $this->recalculateAllTotals()),

                        Checkbox::make('rounding_enabled')
                            ->label('Rounding')
                            ->live()
                            ->afterStateUpdated(fn () => $this->recalculateAllTotals()),

                        TextInput::make('rounding')->label('Rounding Amount')->readOnly()->prefix('KES'),
                        TextInput::make('tax_total')->label('Tax')->readOnly()->prefix('KES'),

                        TextInput::make('document_total')
                            ->label('Total')
                            ->readOnly()
                            ->prefix('KES')
                            ->extraInputAttributes(['class' => 'sap-grand-total']),

                        TextInput::make('applied_amount')->label('Applied Amount')->readOnly()->prefix('KES'),
                        TextInput::make('balance_due')->label('Balance Due')->readOnly()->prefix('KES'),
                    ]),
                ]),
            ]);
    }

    /* -----------------------------------------------------------------
     | Choose From List
     | ----------------------------------------------------------------- */

    #[On('choose-from-list-selected')]
    public function chooseFromListSelected(string $statePath, int|string $recordId, string $source): void
    {
        if (! $this->isWritableStatePath($statePath)) {
            return;
        }

        data_set($this, $statePath, (string) $recordId);

        match ($source) {
            'customers_by_code', 'customers_by_name' => $this->applyCustomer(
                (string) $recordId,
                $this->siblingSetter($statePath),
            ),
            'items' => $this->applyItem(
                (string) $recordId,
                $this->siblingSetter($statePath),
                $this->siblingGetter($statePath),
            ),
            // Sales employee needs no companion fields populated.
            default => null,
        };

        $this->recalculateAllTotals();
    }

    /* -----------------------------------------------------------------
     | Field hydration + maths
     | ----------------------------------------------------------------- */

    /**
     * @param  Set|callable  $set
     */
    protected function applyCustomer(?string $customerId, $set): void
    {
        $customer = $customerId ? Customer::find($customerId) : null;

        $set('customer_id', $customer?->getKey());
        $set('customer_name_lookup', $customer?->getKey());
        $set('contact_person', $customer?->contact_person);
        $set('currency', $customer?->currency ?? 'KES');
        $set('kra_pin', $customer?->kra_pin);
    }

    /**
     * Auto-populate a line from the chosen item (Task 1d).
     *
     * @param  Set|callable  $set
     * @param  Get|callable  $get
     */
    protected function applyItem(?string $itemId, $set, $get): void
    {
        $item = $itemId ? Item::find($itemId) : null;

        if (! $item) {
            return;
        }

        $set('item_no', $item->item_no);
        $set('item_description', $item->description);
        $set('uom', $item->uom);
        $set('warehouse', $item->warehouse);
        $set('qty_in_warehouse', number_format((float) $item->qty_in_warehouse, 3, '.', ''));
        $set('price_before_discount', (float) $item->unit_price);

        if (blank($get('quantity'))) {
            $set('quantity', 1);
        }

        $this->recalculateLine($get, $set);
    }

    /**
     * @param  Get|callable  $get
     * @param  Set|callable  $set
     */
    protected function applyVatCode(?string $vatCodeId, $get, $set): void
    {
        $vat = $vatCodeId ? VatCode::find($vatCodeId) : VatCode::default();

        $set('vat_rate', (float) ($vat?->rate ?? 0));

        $this->recalculateLine($get, $set);
    }

    /**
     * Recompute one line's derived columns, then the document totals.
     *
     * @param  Get|callable  $get
     * @param  Set|callable  $set
     */
    protected function recalculateLine($get, $set): void
    {
        $line = InvoiceCalculator::recalculateLine([
            'quantity' => (float) ($get('quantity') ?? 0),
            'price_before_discount' => (float) ($get('price_before_discount') ?? 0),
            'discount_percent' => (float) ($get('discount_percent') ?? 0),
            'vat_rate' => (float) ($get('vat_rate') ?? 0),
        ]);

        $set('price_after_discount', $this->money($line['price_after_discount']));
        $set('gross_price_after_discount', $this->money($line['gross_price_after_discount']));
        $set('total', $this->money($line['line_total']));
        $set('gross_total', $this->money($line['gross_total']));

        $this->recalculateAllTotals();
    }

    /**
     * Totals are always derived from `$this->data` rather than a component
     * relative Get, so this is correct no matter which field triggered it.
     */
    public function recalculateAllTotals(): void
    {
        $lines = (array) data_get($this, 'data.lines', []);

        foreach ($lines as $key => $line) {
            if (! is_array($line)) {
                continue;
            }

            // The VAT rate is display state; re-read it from the chosen code so
            // an edited payload cannot lower the tax.
            $line['vat_rate'] = (float) (VatCode::find($line['vat_code_id'] ?? null)?->rate
                ?? VatCode::default()?->rate
                ?? 0);

            $recalculated = InvoiceCalculator::recalculateLine($line);

            data_set($this, "data.lines.{$key}.vat_rate", $recalculated['vat_rate']);
            data_set($this, "data.lines.{$key}.price_after_discount", $this->money($recalculated['price_after_discount']));
            data_set($this, "data.lines.{$key}.gross_price_after_discount", $this->money($recalculated['gross_price_after_discount']));
            data_set($this, "data.lines.{$key}.total", $this->money($recalculated['line_total']));
            data_set($this, "data.lines.{$key}.gross_total", $this->money($recalculated['gross_total']));

            $lines[$key] = $recalculated;
        }

        $totals = InvoiceCalculator::documentTotals(
            $lines,
            (float) data_get($this, 'data.discount_percent', 0),
            (float) data_get($this, 'data.freight', 0),
            (float) data_get($this, 'data.total_down_payment', 0),
            (float) data_get($this, 'data.applied_amount', 0),
            (bool) data_get($this, 'data.rounding_enabled', false),
        );

        foreach ($totals as $key => $value) {
            data_set($this, "data.{$key}", $this->money($value));
        }
    }

    /**
     * Money is held in the form as a fixed 3 d.p. string so the displayed
     * figure and the stored figure are never a rounding apart.
     */
    protected function money(float $value): string
    {
        return number_format($value, InvoiceCalculator::SCALE, '.', '');
    }

    public function getApprovalMessage(): string
    {
        $amount = (float) data_get($this, 'data.document_total', 0);

        return 'Invoice will go for approval – Amount: '.number_format($amount, 2);
    }

    public function requiresApproval(): bool
    {
        return (float) data_get($this, 'data.document_total', 0) > Invoice::APPROVAL_THRESHOLD;
    }

    /* -----------------------------------------------------------------
     | Saving
     | ----------------------------------------------------------------- */

    public function addAndNewAction(): Action
    {
        return Action::make('addAndNew')
            ->label('Add & New')
            ->color('primary')
            ->submit('save');
    }

    public function addDraftAndNewAction(): Action
    {
        return Action::make('addDraftAndNew')
            ->label('Add Draft & New')
            ->color('gray')
            ->action('saveDraft');
    }

    /**
     * "Copy From" pulls lines from an earlier document for this customer.
     * Deliberately limited to the selected customer's own documents — copying
     * across customers would leak one customer's pricing into another's quote.
     */
    public function copyFromAction(): Action
    {
        return Action::make('copyFrom')
            ->label('Copy From')
            ->color('gray')
            // A page-level action has no schema container, so the form state
            // is read directly rather than through a Get utility.
            ->disabled(fn () => blank(data_get($this, 'data.customer_id')))
            ->schema([
                Select::make('source_invoice_id')
                    ->label('Source document')
                    ->required()
                    ->options(fn () => Invoice::query()
                        ->where('customer_id', data_get($this, 'data.customer_id'))
                        ->latest('doc_num')
                        ->limit(25)
                        ->get()
                        ->mapWithKeys(fn (Invoice $i) => [
                            $i->id => $i->document_number.' — '.number_format((float) $i->document_total, 2),
                        ])
                        ->all())
                    ->helperText('Only documents for the selected customer are listed.'),
            ])
            ->action(function (array $data) {
                $source = Invoice::with('lines')->find($data['source_invoice_id']);

                if (! $source) {
                    return;
                }

                $this->copyLinesFrom($source);

                Notification::make()
                    ->title("Lines copied from {$source->document_number}")
                    ->success()
                    ->send();
            });
    }

    /**
     * The SAP client turns a document into a downstream one. There is no
     * downstream document type in this system yet, so this states that plainly
     * rather than pretending to work.
     */
    public function copyToAction(): Action
    {
        return Action::make('copyTo')
            ->label('Copy To')
            ->color('gray')
            ->disabled()
            ->tooltip('Available once a downstream document type (credit note / delivery) exists.');
    }

    /**
     * Replace the current lines with a copy of another document's.
     */
    protected function copyLinesFrom(Invoice $source): void
    {
        $lines = $source->lines->map(fn ($line) => [
            'item_id' => $line->item_id,
            'item_no' => $line->item_no,
            'item_description' => $line->item_description,
            'uom' => $line->uom,
            'warehouse' => $line->warehouse,
            'qty_in_warehouse' => $line->qty_in_warehouse,
            'quantity' => (float) $line->quantity,
            'price_before_discount' => (float) $line->price_before_discount,
            'discount_percent' => (float) $line->discount_percent,
            'vat_code_id' => $line->vat_code_id,
            'vat_rate' => (float) $line->vat_rate,
        ])->values()->all();

        data_set($this, 'data.lines', $lines ?: [$this->blankLine()]);

        $this->recalculateAllTotals();
    }

    public function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label('Cancel')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Discard this document?')
            ->modalDescription('The values currently on screen will be cleared. Nothing has been saved yet.')
            ->modalSubmitActionLabel('Discard')
            ->action('resetForm');
    }

    public function resetForm(): void
    {
        $this->form->fill($this->blankDocument());

        Notification::make()->title('Form cleared')->success()->send();
    }

    public function save(): void
    {
        $this->persist(asDraft: false);
    }

    public function saveDraft(): void
    {
        $this->persist(asDraft: true);
    }

    protected function persist(bool $asDraft): void
    {
        abort_unless(static::canAccess(), 403);

        $invoice = app(InvoiceWriter::class)->store(
            $this->form->getState(),
            Auth::id(),
            $asDraft,
        );

        Notification::make()
            ->title(($asDraft ? 'Draft ' : 'Invoice ').$invoice->document_number.' added')
            ->body($invoice->requires_approval
                ? 'Total exceeds '.number_format(Invoice::APPROVAL_THRESHOLD).' — sent for approval.'
                : 'Saved successfully.')
            ->success()
            ->send();

        $this->refreshNextDocNum();
        $this->form->fill($this->blankDocument());
    }
}
