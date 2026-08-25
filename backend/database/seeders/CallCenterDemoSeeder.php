<?php

namespace Database\Seeders;

use App\Models\CallCenter;
use App\Models\CallCenterAgent;
use App\Models\CallCenterAgentGroup;
use App\Models\CallDisposition;
use App\Models\CallLog;
use App\Models\Campaign;
use App\Models\Customer;
use App\Modules\CallCenter\Enums\AgentStatus;
use App\Modules\CallCenter\Enums\CallCenterStatus;
use App\Modules\CallCenter\Enums\CallStatus;
use App\Modules\CallCenter\Enums\CallType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CallCenterDemoSeeder extends Seeder
{
    public function run(): void
    {
        $campaignIds = Campaign::query()->limit(3)->pluck('id')->all();

        $center = CallCenter::query()->updateOrCreate(
            ['name' => 'Primary Sales Floor'],
            [
                'description' => 'Main outbound sales and support call center.',
                'status' => CallCenterStatus::Active,
                'timezone' => 'America/New_York',
                'phone_number' => '+1-555-0100',
                'extension' => '100',
                'country_code' => 'US',
                'default_language' => 'en',
                'allowed_campaign_ids' => $campaignIds,
                'assigned_user_ids' => [],
                'assigned_role_ids' => [],
                'permissions' => ['view_logs', 'manage_dispositions'],
            ],
        );

        $dispositions = collect([
            ['name' => 'Sale', 'code' => 'sale', 'color' => 'success', 'counts_as_sale' => true, 'sort_order' => 1],
            ['name' => 'No Answer', 'code' => 'no_answer', 'color' => 'gray', 'sort_order' => 2],
            ['name' => 'Busy', 'code' => 'busy', 'color' => 'warning', 'sort_order' => 3],
            ['name' => 'Callback', 'code' => 'callback', 'color' => 'info', 'sort_order' => 4],
            ['name' => 'Wrong Number', 'code' => 'wrong_number', 'color' => 'danger', 'sort_order' => 5],
            ['name' => 'Interested', 'code' => 'interested', 'color' => 'primary', 'sort_order' => 6],
            ['name' => 'Not Interested', 'code' => 'not_interested', 'color' => 'gray', 'sort_order' => 7],
            ['name' => 'Voicemail', 'code' => 'voicemail', 'color' => 'warning', 'sort_order' => 8],
        ])->map(fn (array $data) => CallDisposition::query()->updateOrCreate(
            ['code' => $data['code']],
            array_merge($data, ['is_active' => true]),
        ));

        $agents = collect([
            ['name' => 'Alex Rivera', 'username' => 'arivera', 'email' => 'alex.agent@checkoutplatform.test', 'status' => AgentStatus::OnCall],
            ['name' => 'Jordan Lee', 'username' => 'jlee', 'email' => 'jordan.agent@checkoutplatform.test', 'status' => AgentStatus::Active],
            ['name' => 'Sam Patel', 'username' => 'spatel', 'email' => 'sam.agent@checkoutplatform.test', 'status' => AgentStatus::Offline],
        ])->map(fn (array $data, int $index) => CallCenterAgent::query()->updateOrCreate(
            ['email' => $data['email']],
            [
                'call_center_id' => $center->id,
                'name' => $data['name'],
                'username' => $data['username'],
                'phone' => '+1-555-010'.($index + 1),
                'extension' => (string) (200 + $index),
                'status' => $data['status'],
                'assigned_campaign_ids' => $campaignIds,
                'role_label' => 'Sales Agent',
                'permissions' => ['make_calls', 'view_customers'],
                'is_logged_in' => $data['status'] !== AgentStatus::Offline,
                'last_login_at' => now()->subHours($index + 1),
            ],
        ));

        $group = CallCenterAgentGroup::query()->updateOrCreate(
            ['name' => 'Outbound Team A', 'call_center_id' => $center->id],
            [
                'description' => 'Primary outbound dialing group.',
                'assigned_campaign_ids' => $campaignIds,
                'permissions' => ['outbound_calls'],
            ],
        );
        $group->agents()->sync($agents->pluck('id'));

        $customer = Customer::query()->first();
        $saleDisposition = $dispositions->firstWhere('code', 'sale');
        $noAnswerDisposition = $dispositions->firstWhere('code', 'no_answer');

        foreach ([
            [
                'agent' => $agents[0],
                'call_type' => CallType::Outbound,
                'call_status' => CallStatus::Answered,
                'duration_seconds' => 245,
                'disposition' => $saleDisposition,
                'notes' => 'Customer purchased subscription upgrade.',
            ],
            [
                'agent' => $agents[1],
                'call_type' => CallType::Inbound,
                'call_status' => CallStatus::Missed,
                'duration_seconds' => 0,
                'disposition' => $noAnswerDisposition,
                'notes' => 'No answer after 4 rings.',
            ],
            [
                'agent' => $agents[2],
                'call_type' => CallType::Outbound,
                'call_status' => CallStatus::Completed,
                'duration_seconds' => 132,
                'disposition' => $dispositions->firstWhere('code', 'callback'),
                'notes' => 'Requested callback tomorrow morning.',
            ],
        ] as $index => $payload) {
            CallLog::query()->updateOrCreate(
                ['call_id' => 'CALL-DEMO-'.($index + 1)],
                [
                    'customer_id' => $customer?->id,
                    'customer_name' => $customer?->full_name ?? 'Demo Customer',
                    'phone_number' => $customer?->phone ?? '+1-555-0199',
                    'agent_id' => $payload['agent']->id,
                    'call_center_id' => $center->id,
                    'platform_campaign_id' => $campaignIds[0] ?? null,
                    'disposition_id' => $payload['disposition']?->id,
                    'call_type' => $payload['call_type'],
                    'call_status' => $payload['call_status'],
                    'duration_seconds' => $payload['duration_seconds'],
                    'recording_url' => 'https://example.com/recordings/demo-'.($index + 1).'.mp3',
                    'notes' => $payload['notes'],
                    'started_at' => now()->subHours($index + 2),
                    'ended_at' => now()->subHours($index + 2)->addSeconds($payload['duration_seconds']),
                ],
            );
        }
    }
}
