<?php

namespace Tests\Feature;

use App\Models\Record;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use NycuCsit\LaravelRestfulUtils\TestHelpers\Pagination;
use Tests\TestCase;
use Tests\Utils\HasRecords;
use Tests\Utils\HasUsers;

class RecordTest extends TestCase
{
    use HasRecords;
    use HasUsers;
    use Pagination;
    use RefreshDatabase;

    protected $applicationPerUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRecords();
        $this->setUpUsers();
    }

    public function test_index(): void
    {
        foreach ($this->getDnstaUsers() as $user) {
            // NOTE: You can get record with different status by filter in query param
            $response = $this->actingAs($user)
                ->getJson('/api/records');
            $response->assertOk();
            $this->assertResourcePaginated($response);
            $this->assertEquals(
                $this->getRecords()->count(),
                $response->json('meta.total'),
            );
        }
    }

    public function test_index_forbidden(): void
    {
        foreach ($this->getApplicantUsers() as $user) {
            $response = $this->actingAs($user)
                ->getJson('/api/records');
            $response->assertForbidden();
        }
        foreach ($this->getApproverUsers() as $user) {
            $response = $this->actingAs($user)
                ->getJson('/api/records');
            $response->assertForbidden();
        }
    }

    public function test_index_unauthorized(): void
    {
        Http::fake();
        $this->getJson('/api/records')->assertUnauthorized();
    }

    public function test_show(): void
    {
        $record = Record::first();
        foreach ($this->getDnstaUsers() as $user) {
            $response = $this->actingAs($user)
                ->getJson("/api/records/{$record->id}");
            $response->assertOk();
            $this->assertSameRecord($record, $response);
        }
    }

    public function test_show_unauthorized(): void
    {
        Http::fake();
        $record = Record::first();
        $response = $this->getJson("/api/records/{$record->id}");
        $response->assertUnauthorized();
    }

    public function test_show_forbidden(): void
    {
        $record = Record::first();
        foreach ($this->getApplicantUsers() as $user) {
            $response = $this->actingAs($user)->getJson("/api/records/{$record->id}");
            $response->assertForbidden();
        }
        foreach ($this->getApproverUsers() as $user) {
            $response = $this->actingAs($user)->getJson("/api/records/{$record->id}");
            $response->assertForbidden();
        }
    }

    private function assertSameRecord(Record $record, TestResponse $response): void
    {
        $this->assertEquals($record->id, $response->json('data.id'));
        $this->assertEquals($record->name, $response->json('data.name'));
        $this->assertEquals(
            $record->type->value,
            $response->json('data.type'),
        );
        $this->assertEquals($record->data, $response->json('data.data'));
        $this->assertEquals(
            $record->status->value,
            $response->json('data.status'),
        );
    }
}
