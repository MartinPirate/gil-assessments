<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use App\Models\LoginSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The user record: an account with a history, not four form fields.
 */
class UserDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_shows_the_person_their_role_and_their_sessions(): void
    {
        $subject = User::factory()->role(UserRole::Approver)->create([
            'name' => 'Amina Otieno',
            'email' => 'amina@gil.test',
            'approval_limit' => 50000,
        ]);

        LoginSession::create([
            'user_id' => $subject->id,
            'session_id' => 'sess-1',
            'logged_in_at' => now()->subHours(2),
            'logged_out_at' => now()->subHour(),
            'ip_address' => '10.0.0.9',
        ]);

        $this->actingAs(User::factory()->role(UserRole::Admin)->create());

        $this->get(UserResource::getUrl('view', ['record' => $subject]))
            ->assertSuccessful()
            ->assertSee('Amina Otieno')
            ->assertSee('amina@gil.test')
            ->assertSee('Approver')
            ->assertSee('10.0.0.9')
            ->assertSee('Decide approvals');
    }

    /**
     * Capabilities are read off the role, so a deactivated approver still
     * shows what the role grants — the account being off is a separate fact
     * and is badged separately.
     */
    public function test_a_deactivated_account_is_marked_as_such(): void
    {
        $subject = User::factory()->role(UserRole::Sales)->create(['is_active' => false]);

        $this->actingAs(User::factory()->role(UserRole::Admin)->create());

        $this->get(UserResource::getUrl('view', ['record' => $subject]))
            ->assertSuccessful()
            ->assertSee('Deactivated');
    }

    public function test_a_brand_new_account_still_renders(): void
    {
        $subject = User::factory()->role(UserRole::GateOfficer)->create();

        $this->actingAs(User::factory()->role(UserRole::Admin)->create());

        $this->get(UserResource::getUrl('view', ['record' => $subject]))
            ->assertSuccessful()
            ->assertSee('This account has never signed in.')
            ->assertSee('This account has not changed anything yet.');
    }

    /**
     * User administration is administrator-only, and the record page must not
     * be a way around that.
     */
    public function test_a_non_administrator_cannot_open_a_user_record(): void
    {
        $subject = User::factory()->role(UserRole::Sales)->create();

        $this->actingAs(User::factory()->role(UserRole::Sales)->create());

        $this->get(UserResource::getUrl('view', ['record' => $subject]))->assertForbidden();
    }
}
