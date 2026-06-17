<?php

namespace Tests\Feature;

use App\Repositories\UploadRepository;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = $this->makeMongoUser(['_id' => 'dashboard-user-id']);
        $this->actingAs($user);

        $uploads = Mockery::mock(UploadRepository::class);
        $uploads->shouldReceive('dashboardUploadsForUser')
            ->once()
            ->with('dashboard-user-id')
            ->andReturn(new Collection());

        $this->instance(UploadRepository::class, $uploads);

        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);
    }
}
