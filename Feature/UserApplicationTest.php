<?php

namespace Tests\Feature;

use App\Models\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use NycuCsit\LaravelRestfulUtils\TestHelpers\Pagination;
use Tests\TestCase;
use Tests\Utils\HasUsers;

class UserApplicationTest extends TestCase
{
    use HasUsers;
    use Pagination;
    use RefreshDatabase;

    protected $applicationPerUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpUsers();
        $this->applicationPerUser = 2;
        foreach ($this->users as $user) {
            Application::factory()
                ->for($user, 'user')
                ->count($this->applicationPerUser)
                ->create();
        }
    }

    public function test_index(): void
    {
        foreach ($this->getUsers() as $user) {
            $response = $this->actingAs($user)
                ->getJson("/api/users/{$user->id}/applications");
            $response->assertOk();
            $this->assertEquals($this->applicationPerUser, $response->json('meta.total'));
            foreach ($this->getDnstaUsers() as $dnsta) {
                $response = $this->actingAs($dnsta)
                    ->getJson("/api/users/{$user->id}/applications");
                $response->assertOk();
                $this->assertEquals($this->applicationPerUser, $response->json('meta.total'));
            }
        }
    }

    public function test_index_unauthorized(): void
    {
        Http::fake();
        foreach ($this->getApplicantUsers() as $user) {
            $this->getJson("/api/users/{$user->id}/applications")->assertUnauthorized();
        }
    }

    public function test_index_forbidden(): void
    {
        foreach ($this->getApplicantUsers() as $applicant) {
            foreach ($this->getUsers() as $user) {
                if ($applicant == $user) {
                    continue;
                }
                $this->actingAs($applicant)
                    ->getJson("/api/users/{$user->id}/applications")
                    ->assertForbidden();
            }
        }
    }
}
