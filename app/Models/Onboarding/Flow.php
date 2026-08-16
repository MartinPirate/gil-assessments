<?php

namespace App\Models\Onboarding;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Wallacemartinss\FilamentOnboarding\Facades\Onboarding;
use Wallacemartinss\FilamentOnboarding\Models\OnboardingFlow;
use Wallacemartinss\FilamentOnboarding\Models\OnboardingFlowProgress;
use Wallacemartinss\FilamentOnboarding\Models\OnboardingStep;
use Wallacemartinss\FilamentOnboarding\Models\OnboardingStepProgress;

/**
 * The plugin's flow, with one ordering removed.
 *
 * Its steps() relation orders by sort_order, and the manager eager-loads that
 * relation with an explicit orderBy('sort_order') of its own. MySQL and
 * Postgres shrug at the repeated column; SQL Server refuses outright —
 * "a column has been specified more than once in the order by list".
 *
 * The relation drops its ordering and lets the caller's stand. Loaded any
 * other way the order is by insertion, which for a seeded checklist is the
 * same order anyway; every read inside the plugin goes through the manager,
 * which orders explicitly.
 *
 * Overriding the model rather than patching the package: composer install
 * would undo a vendor edit, and config('filament-onboarding.models.flow')
 * exists precisely for this.
 *
 * @property string $id
 * @property string $key
 * @property string|null $panel_id
 * @property array<array-key, mixed> $title
 * @property array<array-key, mixed>|null $description
 * @property string|null $icon
 * @property string|null $color
 * @property bool $is_active
 * @property bool $is_dismissible
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $visibility_condition
 * @property-read Collection<int, OnboardingFlowProgress> $progress
 * @property-read int|null $progress_count
 * @property-read Collection<int, OnboardingStepProgress> $stepProgress
 * @property-read int|null $step_progress_count
 * @property-read Collection<int, OnboardingStep> $steps
 * @property-read int|null $steps_count
 *
 * @method static Builder<static>|Flow active()
 * @method static Builder<static>|Flow forPanel(?string $panelId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Flow newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Flow newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Flow query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Flow whereColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Flow whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Flow whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Flow whereIcon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Flow whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Flow whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Flow whereIsDismissible($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Flow whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Flow wherePanelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Flow whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Flow whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Flow whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Flow whereVisibilityCondition($value)
 *
 * @mixin \Eloquent
 */
class Flow extends OnboardingFlow
{
    /**
     * @return HasMany<OnboardingStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(Onboarding::stepModel(), 'flow_id');
    }
}
