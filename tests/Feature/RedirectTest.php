<?php

namespace Tests\Feature;

use Tests\TestCase;

class RedirectTest extends TestCase
{
    public function test_root_redirects_to_employees(): void
    {
        $this->get('/')->assertRedirect('/employees');
    }
}
