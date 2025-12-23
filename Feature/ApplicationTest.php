<?php

namespace Tests\Feature;

use App\Enums\ApplicationAction;
use App\Enums\ApplicationStatus;
use App\Enums\RecordStatus;
use App\Enums\RecordType;
use App\Enums\Role;
use App\Mail\NotifyAfterReject;
use App\Mail\NotifyApplicantAfterAccept;
use App\Mail\NotifyApplicantAfterApprove;
use App\Mail\NotifyApproverAfterSubmit;
use App\Mail\NotifyDnsTaAfterApprove;
use App\Mail\NotifyDnsTaAfterApproverSubmit;
use App\Models\Application;
use App\Models\Record;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use NycuCsit\LaravelRestfulUtils\TestHelpers\Pagination;
use Tests\TestCase;
use Tests\Utils\HasUsers;

class ApplicationTest extends TestCase
{
    use HasUsers;
    use Pagination;
    use RefreshDatabase;

    protected $applicationPerUser;

    protected Collection $applications;

    protected Application $validApproverApp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpUsers();
        $this->applicationPerUser = 2;
        foreach ($this->users as $user) {
            Application::factory()
                ->for($user, 'user')
                ->count($this->applicationPerUser)
                ->sequence(
                    ['approver_id' => $this->users[Role::APPROVER->value]->id],
                    ['approver_id' => $this->users[Role::APPROVER->value]->id],
                )->create();
        }
        $this->applications = Application::all();

        Mail::fake();
    }

    public function test_index(): void
    {
        foreach ($this->getDnstaUsers() as $user) {
            $response = $this->actingAs($user)->getJson('/api/applications');
            $response->assertOk();
            $this->assertResourcePaginated($response);
            $this->assertGreaterThanOrEqual(count($this->applications), $response->json('meta.total'));
        }
    }

    public function test_index_unauthorized(): void
    {
        Http::fake();
        $response = $this->getJson('/api/applications');
        $response->assertUnauthorized();
    }

    public function test_index_forbidden(): void
    {
        foreach ($this->getApplicantUsers() as $user) {
            $response = $this->actingAs($user)->getJson('/api/applications');
            $response->assertForbidden();
        }
        foreach ($this->getApproverUsers() as $user) {
            $response = $this->actingAs($user)->getJson('/api/applications');
            $response->assertForbidden();
        }
    }

    public function test_store(): void
    {
        $approver = User::factory()->approver()->create();
        foreach ($this->getApplicantUsers() as $user) {
            $request = [
                'applicant_id' => $user->id,
                'action' => ApplicationAction::ADD,
                'office_room' => 'EC318',
                'office_ext' => '54707',
                'record_name' => fake()->domainName(),
                'record_type' => RecordType::A,
                'record_data' => fake()->ipv4(),
                'approver_id' => $approver->id,
            ];
            $response = $this->actingAs($user)->postJson('/api/applications', $request);
            $response->assertCreated();
            $this->assertDatabaseHas(Application::class, $request);

            Mail::assertQueuedCount(1);
            Mail::assertQueued(NotifyApproverAfterSubmit::class, fn (NotifyApproverAfterSubmit $mail) => $mail->hasTo($user->approver->email) &&
                    $mail->hasCc($user->email));
        }
    }

    public function test_store_unauthorized(): void
    {
        Http::fake();
        $app = Application::factory()->raw([
            'action' => ApplicationAction::ADD,
            'office_room' => 'EC320',
            'office_ext' => '48763',
            'approver_id' => $this->users[Role::APPROVER->value]->id,
            'remark' => 'ABC',
            'records' => Record::factory(3)->raw(),
        ]);
        $response = $this->postJson('/api/applications', $app);
        $response->assertUnauthorized();
    }

    public function test_show(): void
    {
        foreach ($this->getDnstaUsers() as $user) {
            $app = Application::first();
            $response = $this->actingAs($user)->getJson("/api/applications/{$app->id}");
            $response->assertOk();
            $this->assertEquals($app->id, $response->json('data.id'));
        }

        $user = User::firstWhere('role', Role::APPLICANT);
        // applicant can view their application
        $app = Application::firstWhere('applicant_id', $user->id);
        $response = $this->actingAs($user)->get("/api/applications/$app->id");
        $response->assertSuccessful();
        // but can't view others
        $app = Application::firstWhere('applicant_id', '<>', $user->id);
        $response = $this->actingAs($user)->get("/api/applications/$app->id");
        $response->assertForbidden();
    }

    public function test_show_unauthorized(): void
    {
        Http::fake();
        $app = Application::first();
        $response = $this->getJson("/api/applications/{$app->id}");
        $response->assertUnauthorized();
    }

    public function test_show_forbidden(): void
    {
        $app = Application::first();
        foreach ($this->getApplicantUsers() as $user) {
            $response = $this->actingAs($user)->getJson("/api/applications/{$app->id}");
            $response->assertForbidden();
        }
        foreach ($this->getApproverUsers() as $user) {
            $response = $this->actingAs($user)->getJson("/api/applications/{$app->id}");
            $response->assertForbidden();
        }
    }

    /**
     * Approve
     */
    public function test_approve(): void
    {
        foreach ($this->getApproverUsers() as $user) {
            $app = Application::factory()->create([
                'approver_id' => $user->id,
                'status' => ApplicationStatus::PENDING,
            ]);
            $response = $this->actingAs($user)->postJson("/api/applications/{$app->id}/approve");
            $response->assertOk();
            $this->assertEquals(ApplicationStatus::APPROVED, $app->refresh()->status);

            Mail::assertQueuedCount(2);
            Mail::assertQueued(NotifyDnsTaAfterApprove::class, fn (NotifyDnsTaAfterApprove $mail) => $mail->hasTo(Config::get('mail.dnsta')));
            Mail::assertQueued(NotifyApplicantAfterApprove::class, fn (NotifyApplicantAfterApprove $mail) => $mail->hasTo($app->user->email) &&
                    $mail->hasCc($app->approver->email));
        }
    }

    public function test_approve_application_with_mismatch_status(): void
    {
        foreach ($this->getApproverUsers() as $user) {
            $statuses = [
                ApplicationStatus::APPROVED,
                ApplicationStatus::ACCEPTED,
                ApplicationStatus::REVOKED,
                ApplicationStatus::REJECTED,
            ];
            foreach ($statuses as $status) {
                $app = Application::factory()->create([
                    'approver_id' => $user->id,
                    'status' => $status->value,
                ]);
                $response = $this->actingAs($user)->postJson("/api/applications/{$app->id}/approve");
                $response->assertBadRequest();
            }
        }
    }

    public function test_approve_without_permission(): void
    {
        $app = $this->applications->first();

        // applicant
        $applicant = User::factory()->role(Role::APPLICANT)->create();
        $otherApprover = User::factory()->role(Role::APPROVER)->create();

        $otherApplicants = [$applicant, $otherApprover];

        foreach ($otherApplicants as $user) {
            $response = $this->actingAs($user)->postJson("/api/applications/{$app->id}/approve");
            $response->assertForbidden();
        }
    }

    public function test_unauthorized_approve(): void
    {
        $app = $this->applications->first();

        $response = $this->postJson("/api/applications/{$app->id}/approve");
        $response->assertUnauthorized();
    }

    /**
     * Accept
     */
    public function test_accept(): void
    {
        foreach ($this->getDnstaUsers() as $user) {
            $app = Application::factory()->create([
                'approver_id' => $user->id,
                'status' => ApplicationStatus::APPROVED,
            ]);
            $response = $this->actingAs($user)->postJson("/api/applications/{$app->id}/accept");
            $response->assertOk();
            $this->assertEquals(ApplicationStatus::ACCEPTED, $app->refresh()->status);

            Mail::assertQueuedCount(1);
            Mail::assertQueued(NotifyApplicantAfterAccept::class, fn (NotifyApplicantAfterAccept $mail) => $mail->hasTo($app->user->email) &&
                    $mail->hasCc($app->approver->email) &&
                    $mail->hasCc(Config::get('mail.dnsta')));
        }
    }

    public function test_accept_application_with_mismatch_status(): void
    {
        foreach ($this->getDnstaUsers() as $user) {
            $statuses = [
                ApplicationStatus::ACCEPTED,
                ApplicationStatus::REVOKED,
                ApplicationStatus::REJECTED,
            ];
            foreach ($statuses as $status) {
                $app = Application::factory()->create([
                    'approver_id' => $user->id,
                    'status' => $status->value,
                ]);
                $response = $this->actingAs($user)->postJson("/api/applications/{$app->id}/accept");
                $response->assertBadRequest();
            }
        }

        // TODO: test when app.status = PENDING,
        // if `force` is given, should be ok
    }

    public function test_accept_without_permission(): void
    {
        $app = $this->applications->first();

        // applicant
        $applicant = User::factory()->role(Role::APPLICANT)->create();
        $otherApprover = User::factory()->role(Role::APPROVER)->create();

        // TODO: test application's approver has no permission as well
        $otherApplicants = [$applicant, $otherApprover];

        foreach ($otherApplicants as $user) {
            $response = $this->actingAs($user)->postJson("/api/applications/{$app->id}/accept");
            $response->assertForbidden();
        }
    }

    public function test_unauthorized_accept(): void
    {
        $app = $this->applications->first();

        $response = $this->postJson("/api/applications/{$app->id}/accept");
        $response->assertUnauthorized();
    }

    /**
     * Revoke
     */
    public function test_revoke(): void
    {
        foreach ($this->getApproverUsers() as $user) {
            foreach ([ApplicationStatus::PENDING, ApplicationStatus::APPROVED] as $status) {
                $app = Application::factory()->create([
                    'approver_id' => $user->id,
                    'status' => $status->value,
                ]);
                $response = $this->actingAs($app->user)->postJson("/api/applications/{$app->id}/revoke");
                $response->assertOk();
                $this->assertEquals(ApplicationStatus::REVOKED, $app->refresh()->status);
            }
        }
    }

    public function test_revoke_application_with_mismatch_status(): void
    {
        foreach ($this->getApproverUsers() as $user) {
            $statuses = [
                ApplicationStatus::ACCEPTED,
                ApplicationStatus::REVOKED,
                ApplicationStatus::REJECTED,
            ];
            foreach ($statuses as $status) {
                $app = Application::factory()->create([
                    'approver_id' => $user->id,
                    'status' => $status->value,
                ]);
                $response = $this->actingAs($app->user)->postJson("/api/applications/{$app->id}/revoke");
                $response->assertBadRequest();
            }
        }
    }

    public function test_revoke_without_permission(): void
    {
        $app = $this->applications->first();

        // applicant
        $applicant = User::factory()->role(Role::APPLICANT)->create();
        $otherApprover = User::factory()->role(Role::APPROVER)->create();

        $otherApplicants = [$applicant, $otherApprover];

        foreach ($otherApplicants as $user) {
            $response = $this->actingAs($user)->postJson("/api/applications/{$app->id}/approve");
            $response->assertForbidden();
        }
    }

    public function test_unauthorized_revoke(): void
    {
        $app = $this->applications->first();

        $response = $this->postJson("/api/applications/{$app->id}/revoke");
        $response->assertUnauthorized();
    }

    /**
     * Reject
     */
    protected function assertReject(User $user, Application $app, bool $isDnsTa = false)
    {
        $response = $this->actingAs($user)->postJson("/api/applications/{$app->id}/reject", [
            'remark' => 'Not good application',
        ]);
        $response->assertOk();
        $this->assertEquals(ApplicationStatus::REJECTED, $app->refresh()->status);

        Mail::assertQueued(NotifyAfterReject::class, function (NotifyAfterReject $mail) use ($app, $isDnsTa) {
            $ccDnsTa = ! $mail->hasCc(Config::get('mail.dnsta'));

            if ($isDnsTa) {
                $ccDnsTa = ! $ccDnsTa;
                $mail->assertSeeInHtml('管理員');
                $mail->assertSeeInHtml('Not good application');   // remark
                $mail->assertHasSubject('管理員已拒絕 DNS 申請通知');
            } else {
                $mail->assertSeeInHtml($app->approver->name);
                $mail->assertHasSubject('教授已拒絕 DNS 申請通知');
            }

            return $mail->hasTo($app->user->email) &&
                $mail->hasCc($app->approver->email) &&
                $ccDnsTa;
        });
    }

    public function test_approver_reject(): void
    {
        foreach ($this->getApproverUsers() as $user) {
            foreach ([ApplicationStatus::PENDING, ApplicationStatus::APPROVED] as $status) {
                $app = Application::factory()->create([
                    'approver_id' => $user->id,
                    'status' => $status->value,
                ]);
                $this->assertReject($user, $app);
            }
        }
    }

    public function test_dns_ta_reject(): void
    {
        foreach ($this->getDnstaUsers() as $user) {
            foreach ([ApplicationStatus::APPROVED] as $status) {
                $app = Application::factory()->create([
                    'approver_id' => $user->id,
                    'status' => $status->value,
                ]);
                $this->assertReject($user, $app, true);
            }
        }
    }

    public function test_reject_application_with_mismatch_status(): void
    {
        foreach ($this->getApproverUsers() as $user) {
            $statuses = [
                ApplicationStatus::ACCEPTED,
                ApplicationStatus::REVOKED,
                ApplicationStatus::REJECTED,
            ];
            foreach ($statuses as $status) {
                $app = Application::factory()->create([
                    'approver_id' => $user->id,
                    'status' => $status->value,
                ]);
                $response = $this->actingAs($user)->postJson("/api/applications/{$app->id}/reject");
                $response->assertBadRequest();
            }
        }
    }

    public function test_reject_without_permission(): void
    {
        // Make sure the application is filed by an applicant
        $app = Application::factory()->create([
            'applicant_id' => User::factory(),
        ]);

        // applicant
        $applicant = User::find($app->applicant_id);
        $otherApplicant = User::factory()->role(Role::APPLICANT)->create();
        $otherApprover = User::factory()->role(Role::APPROVER)->create();

        $otherApplicants = [$applicant, $otherApplicant, $otherApprover];

        foreach ($otherApplicants as $user) {
            $response = $this->actingAs($user)->postJson("/api/applications/{$app->id}/reject");
            $response->assertForbidden();
        }
    }

    public function test_unauthorized_reject(): void
    {
        $app = $this->applications->first();

        $response = $this->postJson("/api/applications/{$app->id}/reject");
        $response->assertUnauthorized();
    }

    /**
     * Complete
     */
    public function test_complete(): void
    {
        $dnsta = User::factory()->dnsta()->create();
        foreach ($this->getApproverUsers() as $user) {
            $app = Application::factory()->create([
                'approver_id' => $user->id,
                'status' => ApplicationStatus::ACCEPTED,
            ]);
            $response = $this->actingAs($dnsta)->postJson("/api/applications/{$app->id}/complete");
            $response->assertOk();
            $this->assertEquals(ApplicationStatus::COMPLETED, $app->refresh()->status);

            // TODO: more assertion on ApplicationAction and derived/affected Record
        }

    }

    public function test_complete_application_with_mismatch_status(): void
    {
        $dnsta = User::factory()->dnsta()->create();
        foreach ($this->getApproverUsers() as $user) {
            $statuses = [
                ApplicationStatus::PENDING,
                ApplicationStatus::APPROVED,
                ApplicationStatus::REVOKED,
                ApplicationStatus::REJECTED,
            ];
            foreach ($statuses as $status) {
                $app = Application::factory()->create([
                    'approver_id' => $user->id,
                    'status' => $status->value,
                ]);
                $response = $this->actingAs($dnsta)->postJson("/api/applications/{$app->id}/complete");
                $response->assertBadRequest();
            }
        }
    }

    public function test_complete_without_permission(): void
    {
        $app = $this->applications->first();

        // applicant
        $applicant = User::factory()->role(Role::APPLICANT)->create();
        $otherApprover = User::factory()->role(Role::APPROVER)->create();

        $otherApplicants = [$applicant, $otherApprover];

        foreach ($otherApplicants as $user) {
            $response = $this->actingAs($user)->postJson("/api/applications/{$app->id}/complete");
            $response->assertForbidden();
        }
    }

    public function test_unauthorized_complete(): void
    {
        $app = $this->applications->first();

        $response = $this->postJson("/api/applications/{$app->id}/complete");
        $response->assertUnauthorized();
    }

    /**
     * DNSTA Auto-completion and record creation when creating an application
     */
    public function test_dnsta_add_application_auto_completes_and_creates_record(): void
    {
        // Create DNSTA user
        $dnstaUser = $this->users[Role::DNSTA->value];
        $approver = User::factory()->approver()->create();

        // Submit an ADD application
        $applicationData = [
            'action' => ApplicationAction::ADD->value,
            'office_room' => fake()->text(5),
            'office_ext' => fake()->text(5),
            'record_name' => fake()->domainName(),
            'record_type' => RecordType::A->value,
            'record_data' => fake()->ipv4(),
            'approver_id' => $approver->id,
            'remark' => fake()->sentence(),
        ];

        $response = $this->actingAs($dnstaUser)->postJson('/api/applications', $applicationData);

        // Application should be created successfully
        $response->assertCreated();
        $applicationId = $response->json('data.id');

        // Application should be automatically completed
        $this->assertDatabaseHas(Application::class, [
            'id' => $applicationId,
            'status' => ApplicationStatus::COMPLETED->value,
            'applicant_id' => $dnstaUser->id,
            'action' => ApplicationAction::ADD->value,
            'record_name' => $applicationData['record_name'],
            'record_type' => $applicationData['record_type'],
            'record_data' => $applicationData['record_data'],
            'approver_id' => $applicationData['approver_id'],
        ]);

        // A DNS record should be created and activated
        $application = Application::find($applicationId);
        $this->assertNotNull($application->derivedRecord);

        $this->assertDatabaseHas(Record::class, [
            'id' => $application->derivedRecord->id,
            'name' => $applicationData['record_name'],
            'type' => $applicationData['record_type'],
            'data' => $applicationData['record_data'],
            'status' => RecordStatus::ACTIVE->value,
            'application_id' => $applicationId,
        ]);
    }

    public function test_auto_approve_when_submitted_by_approver(): void
    {
        $approver = User::factory()->approver()->create();

        $applicationData = [
            'action' => ApplicationAction::ADD->value,
            'office_room' => fake()->text(5),
            'office_ext' => fake()->text(5),
            'record_name' => fake()->domainName(),
            'record_type' => RecordType::A->value,
            'record_data' => fake()->ipv4(),
            'approver_id' => $approver->id,
            'remark' => fake()->sentence(),
        ];

        $response = $this->actingAs($approver)->postJson('/api/applications', $applicationData);
        $response->assertCreated();

        $this->assertDatabaseHas(Application::class, $applicationData);

        Mail::assertQueuedCount(1);
        Mail::assertQueued(
            NotifyDnsTaAfterApproverSubmit::class,
            fn (NotifyDnsTaAfterApproverSubmit $mail) => $mail->hasTo(Config::get('mail.dnsta'))
        );
    }

}
