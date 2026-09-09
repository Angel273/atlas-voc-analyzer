<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Permissions
        $permissions = [
            'data.view' => 'View survey data and records',
            'data.import' => 'Upload and import Excel data files',
            'data.delete' => 'Delete survey datasets and imports',

            'dashboard.view' => 'View analytical dashboards',
            'dashboard.edit' => 'Customize, resize, and edit dashboard widgets',
            'dashboard.manage' => 'Create and delete dashboards',

            'ai.chat' => 'Interact with the AI Assistant',
            'ai.history' => 'View and resume previous AI conversations',

            'forecast.view' => 'View statistical forecasts',
            'forecast.run' => 'Execute new forecast calculations and interpretations',

            'identity.view' => 'View real identities (de-anonymize agent BMS and supervisors)',

            'audit.view' => 'View system audit trails and AI execution traces',
            'audit.export' => 'Export tamper-evident audit records',

            'categories.manage' => 'Manage verbatim categories catalog',

            'users.manage' => 'Create and manage system users',
            'roles.manage' => 'Manage roles and assignments',
            'permissions.manage' => 'Manage system permissions',

            'system.manage' => 'Access system administration and diagnostics',
        ];

        $createdPermissions = [];
        foreach ($permissions as $slug => $desc) {
            $createdPermissions[$slug] = Permission::updateOrCreate(
                ['slug' => $slug],
                ['name' => ucwords(str_replace(['.', '_'], ' ', $slug)), 'description' => $desc]
            );
        }

        // 2. Roles
        $adminRole = Role::updateOrCreate(
            ['slug' => 'administrator'],
            ['name' => 'Administrator', 'description' => 'Full administrative access to all VOC tools and audit logs']
        );
        $analystRole = Role::updateOrCreate(
            ['slug' => 'analyst'],
            ['name' => 'Analyst', 'description' => 'Can import data, build dashboards, run forecasts, and consult AI']
        );
        $viewerRole = Role::updateOrCreate(
            ['slug' => 'viewer'],
            ['name' => 'Viewer', 'description' => 'Read-only access to dashboards, reports, and AI assistant']
        );

        // Assign permissions to Administrator (all)
        $adminRole->permissions()->sync(array_column($createdPermissions, 'id'));

        // Assign permissions to Analyst
        $analystPermissions = [
            'data.view', 'data.import',
            'dashboard.view', 'dashboard.edit', 'dashboard.manage',
            'ai.chat', 'ai.history',
            'forecast.view', 'forecast.run',
            'identity.view',
            'audit.view',
            'categories.manage',
        ];
        $analystRole->permissions()->sync(
            collect($analystPermissions)->map(fn ($p) => $createdPermissions[$p]->id)->toArray()
        );

        // Assign permissions to Viewer (note: no identity.view by default, testing pseudonyms!)
        $viewerPermissions = [
            'data.view',
            'dashboard.view',
            'ai.chat', 'ai.history',
            'forecast.view',
        ];
        $viewerRole->permissions()->sync(
            collect($viewerPermissions)->map(fn ($p) => $createdPermissions[$p]->id)->toArray()
        );

        // 3. Default Users
        $admin = User::updateOrCreate(
            ['email' => 'admin@atlas.local'],
            ['name' => 'System Administrator', 'password' => Hash::make('password123')]
        );
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        $analyst = User::updateOrCreate(
            ['email' => 'analyst@atlas.local'],
            ['name' => 'Lead Analyst', 'password' => Hash::make('password123')]
        );
        $analyst->roles()->syncWithoutDetaching([$analystRole->id]);

        $viewer = User::updateOrCreate(
            ['email' => 'viewer@atlas.local'],
            ['name' => 'Business Stakeholder', 'password' => Hash::make('password123')]
        );
        $viewer->roles()->syncWithoutDetaching([$viewerRole->id]);

        // 4. Default Categories
        $categories = [
            [
                'name' => 'Customer Service',
                'description' => 'Feedback concerning representative helpfulness, empathy, attentiveness, and responsiveness.',
                'examples' => 'Agent was courteous; Rep took time to listen; Felt valued as a customer.',
            ],
            [
                'name' => 'Product Experience',
                'description' => 'Comments regarding product features, ease of use, design, and overall usability.',
                'examples' => 'The app was confusing; Feature X is great; Navigation is difficult.',
            ],
            [
                'name' => 'Billing & Payments',
                'description' => 'Issues or questions regarding invoices, charges, fees, payment methods, or refunds.',
                'examples' => 'Unexpected surcharge; Payment failed to process; Unclear breakdown on invoice.',
            ],
            [
                'name' => 'Technical Support',
                'description' => 'Troubleshooting, downtime, system errors, bugs, or slow response times.',
                'examples' => 'System crashed repeatedly; Error 500 when submitting; Cannot connect.',
            ],
            [
                'name' => 'Policy & Process',
                'description' => 'Feedback about company policies, return rules, turnaround times, or verification requirements.',
                'examples' => 'Return policy is too rigid; Verification steps took too long.',
            ],
            [
                'name' => 'Uncategorized / Other',
                'description' => 'General comments or feedback not fitting specific categories.',
                'examples' => 'Okay; None; N/A.',
            ],
        ];

        foreach ($categories as $cat) {
            Category::updateOrCreate(['name' => $cat['name']], $cat);
        }

        // 5. Default Master Dashboard
        $dashboard = Dashboard::updateOrCreate(
            ['name' => 'VOC Master Overview', 'is_default' => true],
            ['description' => 'Executive overview of key VOC metrics, trends, and team distributions.']
        );

        $widgets = [
            [
                'title' => 'Net Promoter Score',
                'type' => 'metric',
                'x' => 0, 'y' => 0, 'w' => 3, 'h' => 3,
                'configuration' => ['metric' => 'nps', 'comparison' => true],
                'sort_order' => 1,
            ],
            [
                'title' => 'Customer Satisfaction',
                'type' => 'metric',
                'x' => 3, 'y' => 0, 'w' => 3, 'h' => 3,
                'configuration' => ['metric' => 'csat', 'comparison' => true],
                'sort_order' => 2,
            ],
            [
                'title' => 'Professionalism',
                'type' => 'metric',
                'x' => 6, 'y' => 0, 'w' => 3, 'h' => 3,
                'configuration' => ['metric' => 'professionalism', 'comparison' => true],
                'sort_order' => 3,
            ],
            [
                'title' => 'Survey Volume',
                'type' => 'metric',
                'x' => 9, 'y' => 0, 'w' => 3, 'h' => 3,
                'configuration' => ['metric' => 'survey_volume'],
                'sort_order' => 4,
            ],
            [
                'title' => 'NPS & CSAT Temporal Trend',
                'type' => 'trend',
                'x' => 0, 'y' => 3, 'w' => 8, 'h' => 5,
                'configuration' => ['metrics' => ['nps', 'csat'], 'dimension' => 'survey_date'],
                'sort_order' => 5,
            ],
            [
                'title' => 'NPS Distribution (P / N / D)',
                'type' => 'distribution',
                'x' => 8, 'y' => 3, 'w' => 4, 'h' => 5,
                'configuration' => ['metric' => 'nps'],
                'sort_order' => 6,
            ],
            [
                'title' => 'Performance by Supervisor',
                'type' => 'comparison',
                'x' => 0, 'y' => 8, 'w' => 6, 'h' => 5,
                'configuration' => ['metric' => 'nps', 'dimension' => 'supervisor'],
                'sort_order' => 7,
            ],
            [
                'title' => 'Verbatim Category Breakdown',
                'type' => 'category_breakdown',
                'x' => 6, 'y' => 8, 'w' => 6, 'h' => 5,
                'configuration' => ['dimension' => 'category'],
                'sort_order' => 8,
            ],
            [
                'title' => 'Supervisor & Team Leader Summary Table',
                'type' => 'table',
                'x' => 0, 'y' => 13, 'w' => 12, 'h' => 6,
                'configuration' => ['group_by' => 'supervisor'],
                'sort_order' => 9,
            ],
        ];

        foreach ($widgets as $w) {
            DashboardWidget::updateOrCreate(
                ['dashboard_id' => $dashboard->id, 'title' => $w['title']],
                $w
            );
        }

        // 6. Default DSL Tools
        $this->call(DslToolSeeder::class);
    }
}
