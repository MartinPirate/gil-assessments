{{-- Task 1b: only rendered when Total Amount exceeds the approval threshold. --}}
<div class="sap-approval-label" role="status" aria-live="polite">
    <x-filament::icon
        icon="heroicon-o-exclamation-triangle"
        class="sap-approval-label__icon"
    />
    <span>{{ $this->getApprovalMessage() }}</span>
</div>
