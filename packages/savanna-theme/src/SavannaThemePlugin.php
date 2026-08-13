<?php

namespace Savanna\Theme;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Colors\Color;

/**
 * Applies the Savanna theme to a Filament panel.
 *
 * The stylesheet does the visual work; this class only sets the panel options
 * that CSS cannot reach — the primary colour ramp Filament generates at
 * runtime, and the sidebar width the layout is drawn against.
 *
 * The CSS is not registered from here. A Filament theme has to be compiled
 * through the application's own Tailwind build so it can see the utility
 * classes in use, so the host imports
 * `packages/savanna-theme/resources/css/savanna.css` from its panel theme
 * file. Injecting a prebuilt stylesheet instead would ship a copy of Tailwind
 * on top of the one already on the page.
 */
class SavannaThemePlugin implements Plugin
{
    /**
     * The single accent. Warm enough to sit on the theme's neutral ground
     * without vibrating against it, dark enough to pass contrast as text on
     * the soft tint used behind an active navigation item.
     */
    public const ACCENT = '#e2571f';

    protected string $accent = self::ACCENT;

    /**
     * 270px. Wide enough that a two-word navigation label and its count badge
     * sit on one line without the label truncating, which is what forces the
     * rail wider than Filament's default in the first place.
     */
    protected string $sidebarWidth = '17rem';

    /**
     * Figtree — a humanist sans with a tall x-height, which is what keeps
     * 12px navigation labels legible at this density.
     */
    protected ?string $font = 'Figtree';

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'savanna-theme';
    }

    /** Override the accent without touching the stylesheet. */
    public function accent(string $hex): static
    {
        $this->accent = $hex;

        return $this;
    }

    public function sidebarWidth(string $width): static
    {
        $this->sidebarWidth = $width;

        return $this;
    }

    /** Pass null to keep the host panel's own typeface. */
    public function font(?string $family): static
    {
        $this->font = $family;

        return $this;
    }

    public function register(Panel $panel): void
    {
        $panel
            ->colors(['primary' => Color::hex($this->accent)])
            ->sidebarWidth($this->sidebarWidth);

        if ($this->font !== null) {
            $panel->font($this->font);
        }
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
