<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The way in.
 */
class EntryPointTest extends TestCase
{
    public function test_the_root_sends_visitors_to_the_panel(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }

    public function test_a_signed_out_visitor_reaching_the_panel_is_asked_to_log_in(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }
}
