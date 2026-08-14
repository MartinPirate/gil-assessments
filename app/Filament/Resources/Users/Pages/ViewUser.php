<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\GateLog;
use App\Models\Invoice;
use App\Models\LoginSession;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * A person, as an account with a history rather than four form fields.
 *
 * The edit form answers "what is this account called and what may it do".
 * This answers the questions someone opens a user to ask: is this really
 * their account, what have they been doing with it, and where from.
 */
class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

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
            ViewEntry::make('profile')
                ->hiddenLabel()
                ->view('filament.partials.user-hero')
                ->columnSpanFull(),

            Grid::make(['default' => 1, 'lg' => 2])->schema([
                Section::make('What this role may do')
                    ->description('Capabilities come from the role, not from this account.')
                    ->icon('heroicon-o-key')
                    ->schema([
                        ViewEntry::make('capabilities')
                            ->hiddenLabel()
                            ->view('filament.partials.user-capabilities'),
                    ]),

                Section::make('Sign-in history')
                    ->description('Where this account has been used, and from which address.')
                    ->icon('heroicon-o-finger-print')
                    ->schema([
                        ViewEntry::make('sessions')
                            ->hiddenLabel()
                            ->view('filament.partials.user-sessions'),
                    ]),
            ])->columnSpanFull(),

            Section::make('Recent activity')
                ->description('Everything this account changed, newest first.')
                ->icon('heroicon-o-clock')
                ->columnSpanFull()
                ->schema([
                    ViewEntry::make('activity')
                        ->hiddenLabel()
                        ->view('filament.partials.user-activity'),
                ]),
        ]);
    }

    /**
     * What this account has actually done.
     *
     * Counted rather than listed: the point of these figures is to show at a
     * glance whether an account is busy, dormant or brand new, which is the
     * first thing you want to know before changing or disabling one.
     *
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        /** @var User $user */
        $user = $this->getRecord();
        $id = $user->getKey();

        $sessions = LoginSession::query()->where('user_id', $id);

        return [
            'invoices' => Invoice::query()->where('created_by', $id)->count(),
            'invoiceValue' => (float) Invoice::query()->where('created_by', $id)->sum('document_total'),
            'decisions' => ApprovalRequest::query()->where('decided_by', $id)->count(),
            'gateMovements' => GateLog::query()->where('gated_in_by', $id)->orWhere('gated_out_by', $id)->count(),
            'changes' => AuditLog::query()->where('user_id', $id)->count(),
            'signIns' => (clone $sessions)->count(),
            'lastSeen' => (clone $sessions)->max('logged_in_at'),
            'isOnline' => (clone $sessions)->whereNull('logged_out_at')->exists(),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, LoginSession>
     */
    public function getSessions(): \Illuminate\Support\Collection
    {
        return LoginSession::query()
            ->where('user_id', $this->getRecord()->getKey())
            ->latest('logged_in_at')
            ->limit(8)
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, AuditLog>
     */
    public function getActivity(): \Illuminate\Support\Collection
    {
        return AuditLog::query()
            ->where('user_id', $this->getRecord()->getKey())
            ->latest('created_at')
            ->limit(15)
            ->get();
    }
}
