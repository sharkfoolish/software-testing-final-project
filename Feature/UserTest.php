<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_user(): void
    {
        $applicant = User::factory()->create();

        $payload = [
            'office_ext' => '33333',
            'office_room' => 'EC334',
        ];

        $response = $this->actingAs($applicant)->putJson("/api/users/{$applicant->id}", $payload);
        $response->assertSuccessful();
        $response->assertJson(['data' => $payload]);
    }

    public function test_get_approvers(): void
    {
        User::factory()->count(2)->role(Role::APPROVER)->create();

        $response = $this->getJson('/api/approvers');
        $response->assertUnauthorized();

        // as applicant
        $applicant = User::factory()->approver()->create();
        $response = $this->actingAs($applicant)->getJson('/api/approvers');
        $response->assertSuccessful();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id', 'cs_id', 'name', 'email', 'role', 'office_room', 'office_ext',
                ],
            ],
        ]);

        // as dnsta
        $dnsta = User::factory()->dnsta()->create();
        $response = $this->actingAs($dnsta)->getJson('/api/approvers');
        $response->assertSuccessful();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id', 'ldap_id', 'cs_id', 'name', 'email', 'role', 'office_room',
                    'office_ext', 'last_logged_in_at', 'created_at', 'updated_at',
                ],
            ],
        ]);
    }
}
