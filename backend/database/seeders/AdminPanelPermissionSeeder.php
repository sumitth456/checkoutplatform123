<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class AdminPanelPermissionSeeder extends Seeder
{
    /**
     * @return array<string, array{group: string, description: string}>
     */
    public static function permissionDefinitions(): array
    {
        return [
            'dashboard.view' => [
                'group' => 'Dashboard',
                'description' => 'View the operations dashboard and widgets',
            ],
            'products.view' => [
                'group' => 'Products',
                'description' => 'View products and catalog details',
            ],
            'products.create' => [
                'group' => 'Products',
                'description' => 'Create products',
            ],
            'products.update' => [
                'group' => 'Products',
                'description' => 'Update products, variants, and related products',
            ],
            'products.delete' => [
                'group' => 'Products',
                'description' => 'Delete, restore, and force delete products',
            ],
            'products.export' => [
                'group' => 'Products',
                'description' => 'Export product catalog',
            ],
            'products.import.view' => [
                'group' => 'Products',
                'description' => 'View product import history and batch details',
            ],
            'products.import.manage' => [
                'group' => 'Products',
                'description' => 'Upload, validate, and run bulk product imports',
            ],
            'products.billing_cycles.manage' => [
                'group' => 'Products',
                'description' => 'Configure default rebill billing cycles on catalog products',
            ],
            'products.billing_cycles.export' => [
                'group' => 'Products',
                'description' => 'Export product billing cycle definitions',
            ],
            'products.billing_cycles.import' => [
                'group' => 'Products',
                'description' => 'Import product billing cycle definitions',
            ],
            'customers.view' => [
                'group' => 'Customers',
                'description' => 'View customers and profiles',
            ],
            'customers.create' => [
                'group' => 'Customers',
                'description' => 'Create customers',
            ],
            'customers.update' => [
                'group' => 'Customers',
                'description' => 'Update customer profiles and addresses',
            ],
            'customers.delete' => [
                'group' => 'Customers',
                'description' => 'Delete, restore, and force delete customers',
            ],
            'customers.export' => [
                'group' => 'Customers',
                'description' => 'Export customer list',
            ],
            'orders.view' => [
                'group' => 'Orders',
                'description' => 'View orders and details',
            ],
            'orders.create' => [
                'group' => 'Orders',
                'description' => 'Create manual orders',
            ],
            'orders.update' => [
                'group' => 'Orders',
                'description' => 'Update order status, fraud, and notes',
            ],
            'orders.delete' => [
                'group' => 'Orders',
                'description' => 'Delete, restore, and force delete orders',
            ],
            'orders.export' => [
                'group' => 'Orders',
                'description' => 'Export orders',
            ],
            'orders.refund' => [
                'group' => 'Orders',
                'description' => 'Issue refunds from the admin panel',
            ],
            'call_center.view' => [
                'group' => 'CRM',
                'description' => 'View call center dashboard and metrics',
            ],
            'call_centers.view' => [
                'group' => 'CRM',
                'description' => 'View call centers',
            ],
            'call_centers.create' => [
                'group' => 'CRM',
                'description' => 'Create call centers',
            ],
            'call_centers.update' => [
                'group' => 'CRM',
                'description' => 'Edit call centers',
            ],
            'call_centers.delete' => [
                'group' => 'CRM',
                'description' => 'Delete call centers',
            ],
            'call_center_agents.view' => [
                'group' => 'CRM',
                'description' => 'View agents and agent groups',
            ],
            'call_center_agents.manage' => [
                'group' => 'CRM',
                'description' => 'Manage agents and agent groups',
            ],
            'call_logs.view' => [
                'group' => 'CRM',
                'description' => 'View call logs',
            ],
            'call_logs.export' => [
                'group' => 'CRM',
                'description' => 'Export call logs',
            ],
            'call_dispositions.view' => [
                'group' => 'CRM',
                'description' => 'View call dispositions',
            ],
            'call_dispositions.manage' => [
                'group' => 'CRM',
                'description' => 'Manage call dispositions',
            ],
            'affiliate_dashboard.view' => [
                'group' => 'Marketing',
                'description' => 'View affiliate dashboard and reports',
            ],
            'affiliates.view' => [
                'group' => 'Marketing',
                'description' => 'View affiliates',
            ],
            'affiliates.create' => [
                'group' => 'Marketing',
                'description' => 'Create affiliates',
            ],
            'affiliates.update' => [
                'group' => 'Marketing',
                'description' => 'Edit affiliates',
            ],
            'affiliates.delete' => [
                'group' => 'Marketing',
                'description' => 'Delete affiliates',
            ],
            'affiliate_offers.view' => [
                'group' => 'Marketing',
                'description' => 'View affiliate offers',
            ],
            'affiliate_offers.create' => [
                'group' => 'Marketing',
                'description' => 'Create affiliate offers',
            ],
            'affiliate_offers.update' => [
                'group' => 'Marketing',
                'description' => 'Edit affiliate offers',
            ],
            'affiliate_offers.delete' => [
                'group' => 'Marketing',
                'description' => 'Delete affiliate offers',
            ],
            'affiliate_campaign_mappings.view' => [
                'group' => 'Marketing',
                'description' => 'View affiliate campaign mappings',
            ],
            'affiliate_campaign_mappings.create' => [
                'group' => 'Marketing',
                'description' => 'Create affiliate campaign mappings',
            ],
            'affiliate_campaign_mappings.update' => [
                'group' => 'Marketing',
                'description' => 'Edit affiliate campaign mappings',
            ],
            'affiliate_campaign_mappings.delete' => [
                'group' => 'Marketing',
                'description' => 'Delete affiliate campaign mappings',
            ],
            'affiliate_tracking_links.view' => [
                'group' => 'Marketing',
                'description' => 'View affiliate tracking links',
            ],
            'affiliate_tracking_links.create' => [
                'group' => 'Marketing',
                'description' => 'Create affiliate tracking links',
            ],
            'affiliate_tracking_links.update' => [
                'group' => 'Marketing',
                'description' => 'Edit affiliate tracking links',
            ],
            'affiliate_tracking_links.delete' => [
                'group' => 'Marketing',
                'description' => 'Delete affiliate tracking links',
            ],
            'affiliate_commissions.view' => [
                'group' => 'Marketing',
                'description' => 'View affiliate commissions',
            ],
            'affiliate_clicks.view' => [
                'group' => 'Marketing',
                'description' => 'View affiliate clicks',
            ],
            'affiliate_conversions.view' => [
                'group' => 'Marketing',
                'description' => 'View affiliate conversions',
            ],
            'affiliate_payouts.view' => [
                'group' => 'Marketing',
                'description' => 'View affiliate payouts',
            ],
            'affiliate_payouts.manage' => [
                'group' => 'Marketing',
                'description' => 'Approve and manage affiliate payouts',
            ],
            'affiliate_promo_materials.view' => [
                'group' => 'Marketing',
                'description' => 'View affiliate promo materials',
            ],
            'affiliate_promo_materials.create' => [
                'group' => 'Marketing',
                'description' => 'Create affiliate promo materials',
            ],
            'affiliate_promo_materials.update' => [
                'group' => 'Marketing',
                'description' => 'Edit affiliate promo materials',
            ],
            'affiliate_promo_materials.delete' => [
                'group' => 'Marketing',
                'description' => 'Delete affiliate promo materials',
            ],
            'affiliate_settings.manage' => [
                'group' => 'Marketing',
                'description' => 'Manage affiliate program settings',
            ],
            'subscriptions.view' => [
                'group' => 'Subscriptions',
                'description' => 'View subscriptions',
            ],
            'subscriptions.create' => [
                'group' => 'Subscriptions',
                'description' => 'Create subscriptions',
            ],
            'subscriptions.update' => [
                'group' => 'Subscriptions',
                'description' => 'Update subscription records and schedules',
            ],
            'subscriptions.delete' => [
                'group' => 'Subscriptions',
                'description' => 'Delete, restore, and force delete subscriptions',
            ],
            'subscriptions.export' => [
                'group' => 'Subscriptions',
                'description' => 'Export subscriptions',
            ],
            'subscriptions.billing' => [
                'group' => 'Subscriptions',
                'description' => 'Pause, resume, cancel, force charge, and retry billing',
            ],
            'rebill_queue.view' => [
                'group' => 'Rebill Queue',
                'description' => 'View rebill queue tabs and subscription charges',
            ],
            'rebill_queue.manage' => [
                'group' => 'Rebill Queue',
                'description' => 'Manual charge, skip charge, cancel rebill, and retry',
            ],
            'rebill_queue.export' => [
                'group' => 'Rebill Queue',
                'description' => 'Export rebill queue data',
            ],
            'payment_gateways.view' => [
                'group' => 'Payment Gateways',
                'description' => 'View payment gateways and routing load',
            ],
            'payment_gateways.create' => [
                'group' => 'Payment Gateways',
                'description' => 'Create payment gateway configurations',
            ],
            'payment_gateways.update' => [
                'group' => 'Payment Gateways',
                'description' => 'Update credentials, limits, and gateway status',
            ],
            'payment_gateways.delete' => [
                'group' => 'Payment Gateways',
                'description' => 'Delete, restore, and force delete payment gateways',
            ],
            'payment_gateways.export' => [
                'group' => 'Payment Gateways',
                'description' => 'Export payment gateway list',
            ],
            'gateway_routing.view' => [
                'group' => 'Gateway Routing',
                'description' => 'View gateway priority queue and failover path',
            ],
            'gateway_routing.manage' => [
                'group' => 'Gateway Routing',
                'description' => 'Reorder gateway routing priority',
            ],
            'transactions.view' => [
                'group' => 'Transactions',
                'description' => 'View payment transactions and gateway details',
            ],
            'transactions.export' => [
                'group' => 'Transactions',
                'description' => 'Export transaction ledger',
            ],
            'delay_fulfillment.view' => [
                'group' => 'Delay Fulfillment',
                'description' => 'View delayed fulfillment queue and shipment records',
            ],
            'delay_fulfillment.manage' => [
                'group' => 'Delay Fulfillment',
                'description' => 'Submit, reschedule, cancel, and retry delayed fulfillments',
            ],
            'delay_fulfillment.export' => [
                'group' => 'Delay Fulfillment',
                'description' => 'Export delayed fulfillment data',
            ],
            'fulfillment.view' => [
                'group' => 'Fulfillment',
                'description' => 'View fulfillment dashboard, search, and shipment records',
            ],
            'fulfillment.manage' => [
                'group' => 'Fulfillment',
                'description' => 'Manage fulfillments, imports, reships, and settings',
            ],
            'fulfillment_houses.view' => [
                'group' => 'Fulfillment',
                'description' => 'View fulfillment houses',
            ],
            'fulfillment_houses.create' => [
                'group' => 'Fulfillment',
                'description' => 'Create fulfillment houses',
            ],
            'fulfillment_houses.update' => [
                'group' => 'Fulfillment',
                'description' => 'Update fulfillment houses',
            ],
            'fulfillment_houses.delete' => [
                'group' => 'Fulfillment',
                'description' => 'Delete fulfillment houses',
            ],
            'warehouses.view' => [
                'group' => 'Fulfillment',
                'description' => 'View warehouses and inventory',
            ],
            'warehouses.create' => [
                'group' => 'Fulfillment',
                'description' => 'Create warehouses',
            ],
            'warehouses.update' => [
                'group' => 'Fulfillment',
                'description' => 'Update warehouses and inventory',
            ],
            'warehouses.delete' => [
                'group' => 'Fulfillment',
                'description' => 'Delete warehouses',
            ],
            'shipping_carriers.view' => [
                'group' => 'Fulfillment',
                'description' => 'View shipping carriers',
            ],
            'shipping_carriers.create' => [
                'group' => 'Fulfillment',
                'description' => 'Create shipping carriers',
            ],
            'shipping_carriers.update' => [
                'group' => 'Fulfillment',
                'description' => 'Update shipping carriers',
            ],
            'shipping_carriers.delete' => [
                'group' => 'Fulfillment',
                'description' => 'Delete shipping carriers',
            ],
            'shipping_rules.view' => [
                'group' => 'Fulfillment',
                'description' => 'View shipping rules',
            ],
            'shipping_rules.create' => [
                'group' => 'Fulfillment',
                'description' => 'Create shipping rules',
            ],
            'shipping_rules.update' => [
                'group' => 'Fulfillment',
                'description' => 'Update shipping rules',
            ],
            'shipping_rules.delete' => [
                'group' => 'Fulfillment',
                'description' => 'Delete shipping rules',
            ],
            'fulfillment_batches.view' => [
                'group' => 'Fulfillment',
                'description' => 'View fulfillment batches',
            ],
            'fulfillment_batches.create' => [
                'group' => 'Fulfillment',
                'description' => 'Create fulfillment batches',
            ],
            'fulfillment_batches.update' => [
                'group' => 'Fulfillment',
                'description' => 'Update fulfillment batches',
            ],
            'fulfillment_batches.delete' => [
                'group' => 'Fulfillment',
                'description' => 'Delete fulfillment batches',
            ],
            'fulfillment_reports.view' => [
                'group' => 'Fulfillment',
                'description' => 'View fulfillment summary reports',
            ],
            'warehouse.view' => [
                'group' => 'Warehouse',
                'description' => 'View warehouse shipments and integration status',
            ],
            'warehouse.manage' => [
                'group' => 'Warehouse',
                'description' => 'Update warehouse API settings and test connectivity',
            ],
            'warehouse.export' => [
                'group' => 'Warehouse',
                'description' => 'Export warehouse shipment records',
            ],
            'coupons.view' => [
                'group' => 'Coupons',
                'description' => 'View coupons and redemption stats',
            ],
            'coupons.create' => [
                'group' => 'Coupons',
                'description' => 'Create coupons',
            ],
            'coupons.update' => [
                'group' => 'Coupons',
                'description' => 'Update coupon rules and product eligibility',
            ],
            'coupons.delete' => [
                'group' => 'Coupons',
                'description' => 'Delete, restore, and force delete coupons',
            ],
            'coupons.export' => [
                'group' => 'Coupons',
                'description' => 'Export coupon list',
            ],
            'email_providers.manage' => [
                'group' => 'Email',
                'description' => 'Manage email providers and failover configuration',
            ],
            'email_templates.manage' => [
                'group' => 'Email',
                'description' => 'Manage transactional email templates',
            ],
            'email_automations.manage' => [
                'group' => 'Email',
                'description' => 'Manage email automations and triggers',
            ],
            'email_logs.view' => [
                'group' => 'Email',
                'description' => 'View email queue and delivery logs',
            ],
            'email_logs.resend' => [
                'group' => 'Email',
                'description' => 'Resend, retry, and cancel queued emails',
            ],
            'email.test' => [
                'group' => 'Email',
                'description' => 'Send test emails and test provider connections',
            ],
            'email.analytics.view' => [
                'group' => 'Email',
                'description' => 'View email analytics dashboards',
            ],
            'funnels.view' => [
                'group' => 'Funnels',
                'description' => 'View funnels, steps, and performance',
            ],
            'funnels.create' => [
                'group' => 'Funnels',
                'description' => 'Create funnels',
            ],
            'funnels.update' => [
                'group' => 'Funnels',
                'description' => 'Update funnel configuration and steps',
            ],
            'funnels.delete' => [
                'group' => 'Funnels',
                'description' => 'Delete, restore, and force delete funnels',
            ],
            'funnels.export' => [
                'group' => 'Funnels',
                'description' => 'Export funnel list',
            ],
            'funnels.products.manage' => [
                'group' => 'Funnels',
                'description' => 'Bulk manage products assigned to funnels and campaigns',
            ],
            'campaigns.view' => [
                'group' => 'Campaigns',
                'description' => 'View enterprise campaigns and configuration',
            ],
            'campaigns.create' => [
                'group' => 'Campaigns',
                'description' => 'Create campaigns',
            ],
            'campaigns.update' => [
                'group' => 'Campaigns',
                'description' => 'Update campaign configuration',
            ],
            'campaigns.delete' => [
                'group' => 'Campaigns',
                'description' => 'Delete, restore, and force delete campaigns',
            ],
            'campaigns.export' => [
                'group' => 'Campaigns',
                'description' => 'Export campaign list',
            ],
            'campaigns.duplicate' => [
                'group' => 'Campaigns',
                'description' => 'Duplicate campaigns',
            ],
            'campaigns.archive' => [
                'group' => 'Campaigns',
                'description' => 'Archive campaigns',
            ],
            'campaigns.products.manage' => [
                'group' => 'Campaigns',
                'description' => 'Bulk manage products on campaigns (add, remove, sort, priority, rules)',
            ],
            'campaigns.funnels.manage' => [
                'group' => 'Campaigns',
                'description' => 'Map campaigns to funnels, landing URLs, and API keys',
            ],
            'campaigns.merchandising.manage' => [
                'group' => 'Campaigns',
                'description' => 'Configure campaign upsells, downsells, and order bumps',
            ],
            'landing_pages.view' => [
                'group' => 'Landing Pages',
                'description' => 'View landing pages, templates, and analytics',
            ],
            'landing_pages.create' => [
                'group' => 'Landing Pages',
                'description' => 'Create landing pages',
            ],
            'landing_pages.update' => [
                'group' => 'Landing Pages',
                'description' => 'Edit, publish, duplicate, and restore landing page versions',
            ],
            'landing_pages.delete' => [
                'group' => 'Landing Pages',
                'description' => 'Delete landing pages',
            ],
            'landing_pages.assets.manage' => [
                'group' => 'Landing Pages',
                'description' => 'Upload and manage landing page media assets',
            ],
            'landing_pages.templates.manage' => [
                'group' => 'Landing Pages',
                'description' => 'Create, update, duplicate, and delete landing page templates',
            ],
            'analytics.view' => [
                'group' => 'Analytics',
                'description' => 'View analytics dashboards and reports',
            ],
            'logs.view' => [
                'group' => 'Logs',
                'description' => 'View admin activity, payment failures, and webhook logs',
            ],
            'logs.export' => [
                'group' => 'Logs',
                'description' => 'Export log records',
            ],
            'webhooks.view' => [
                'group' => 'Webhooks',
                'description' => 'View inbound webhook URLs and gateway verification status',
            ],
            'webhooks.manage' => [
                'group' => 'Webhooks',
                'description' => 'Verify and reset gateway webhook configuration status',
            ],
            'settings.view' => [
                'group' => 'Settings',
                'description' => 'View platform operational settings',
            ],
            'settings.update' => [
                'group' => 'Settings',
                'description' => 'Update platform operational settings',
            ],
            'users.view' => [
                'group' => 'Users',
                'description' => 'View admin users',
            ],
            'users.create' => [
                'group' => 'Users',
                'description' => 'Create admin users',
            ],
            'users.update' => [
                'group' => 'Users',
                'description' => 'Update admin users and role assignments',
            ],
            'users.delete' => [
                'group' => 'Users',
                'description' => 'Delete, restore, and force delete admin users',
            ],
            'users.export' => [
                'group' => 'Users',
                'description' => 'Export admin user list',
            ],
            'users.impersonate' => [
                'group' => 'Users',
                'description' => 'Impersonate another admin user',
            ],
            'users.duplicate' => [
                'group' => 'Users',
                'description' => 'Duplicate admin users',
            ],
            'users.reset_password' => [
                'group' => 'Users',
                'description' => 'Reset admin user passwords',
            ],
            'roles.view' => [
                'group' => 'Roles',
                'description' => 'View roles and assigned permissions',
            ],
            'roles.create' => [
                'group' => 'Roles',
                'description' => 'Create custom roles',
            ],
            'roles.update' => [
                'group' => 'Roles',
                'description' => 'Update roles and permission assignments',
            ],
            'roles.delete' => [
                'group' => 'Roles',
                'description' => 'Delete custom roles',
            ],
            'permissions.view' => [
                'group' => 'Permissions',
                'description' => 'View permission catalog',
            ],
            'permissions.manage' => [
                'group' => 'Permissions',
                'description' => 'Update permission descriptions',
            ],
        ];
    }

    /**
     * @return array<string, array{description: string, permissions: list<string>}>
     */
    public static function roleTemplates(): array
    {
        $all = array_keys(self::permissionDefinitions());

        $viewExport = static fn (string $prefix): array => array_values(array_filter(
            $all,
            fn (string $name): bool => str_starts_with($name, $prefix.'.')
                && (str_ends_with($name, '.view') || str_ends_with($name, '.export')),
        ));

        $module = static fn (string $prefix, bool $includeManage = false): array => array_values(array_filter(
            $all,
            function (string $name) use ($prefix, $includeManage): bool {
                if (! str_starts_with($name, $prefix.'.')) {
                    return false;
                }

                if ($includeManage) {
                    return true;
                }

                return ! str_ends_with($name, '.delete')
                    && ! str_ends_with($name, '.create')
                    && ! str_ends_with($name, '.manage')
                    && ! str_ends_with($name, '.billing')
                    && ! str_ends_with($name, '.refund');
            },
        ));

        return [
            'Super Admin' => [
                'description' => 'Full unrestricted platform access',
                'permissions' => $all,
            ],
            'Manager' => [
                'description' => 'Operations manager across catalog, campaigns, and orders',
                'permissions' => array_values(array_unique(array_merge(
                    ['dashboard.view', 'analytics.view'],
                    $module('products', true),
                    $module('campaigns', true),
                    $module('orders', true),
                    $module('customers', true),
                    $module('subscriptions'),
                    $viewExport('landing_pages'),
                    $viewExport('funnels'),
                ))),
            ],
            'Customer Support' => [
                'description' => 'Customer support and order assistance',
                'permissions' => array_values(array_unique(array_merge(
                    ['dashboard.view'],
                    $module('customers', true),
                    $module('orders', true),
                    $module('subscriptions'),
                    ['orders.refund'],
                    $viewExport('rebill_queue'),
                    $viewExport('delay_fulfillment'),
                    $viewExport('warehouse'),
                    ['fulfillment.view'],
                    $viewExport('logs'),
                    ['call_center.view'],
                    $module('call_centers'),
                    $module('call_center_agents'),
                    $viewExport('call_logs'),
                    $viewExport('call_dispositions'),
                ))),
            ],
            'Marketing' => [
                'description' => 'Landing pages, funnels, and campaign merchandising',
                'permissions' => array_values(array_unique(array_merge(
                    ['dashboard.view', 'analytics.view'],
                    $module('landing_pages', true),
                    $module('funnels', true),
                    $module('campaigns', true),
                    $viewExport('coupons'),
                    ['affiliate_dashboard.view'],
                    $module('affiliates', true),
                    $module('affiliate_offers', true),
                    $module('affiliate_campaign_mappings', true),
                    $module('affiliate_tracking_links', true),
                    $viewExport('affiliate_commissions'),
                    $viewExport('affiliate_clicks'),
                    $viewExport('affiliate_conversions'),
                    $viewExport('affiliate_payouts'),
                    ['affiliate_payouts.manage'],
                    $module('affiliate_promo_materials', true),
                    ['affiliate_settings.manage'],
                    $module('email_providers', true),
                    $module('email_templates', true),
                    $module('email_automations', true),
                    ['email_logs.view', 'email_logs.resend', 'email.test', 'email.analytics.view'],
                ))),
            ],
            'Finance' => [
                'description' => 'Revenue, refunds, and payment operations',
                'permissions' => array_values(array_unique(array_merge(
                    ['dashboard.view', 'analytics.view'],
                    $viewExport('transactions'),
                    $viewExport('orders'),
                    ['orders.refund', 'orders.update'],
                    $viewExport('subscriptions'),
                    ['subscriptions.billing'],
                    $viewExport('payment_gateways'),
                    $viewExport('rebill_queue'),
                    $viewExport('coupons'),
                ))),
            ],
            'Fulfillment' => [
                'description' => 'Warehouse and delayed shipment operations',
                'permissions' => array_values(array_unique(array_merge(
                    ['dashboard.view'],
                    $module('fulfillment', true),
                    $module('fulfillment_houses', true),
                    $module('warehouses', true),
                    $module('shipping_carriers', true),
                    $module('shipping_rules', true),
                    $module('fulfillment_batches', true),
                    ['fulfillment_reports.view'],
                    $module('delay_fulfillment', true),
                    $module('warehouse', true),
                    $viewExport('products'),
                    $viewExport('orders'),
                ))),
            ],
            'Developer' => [
                'description' => 'Integrations, webhooks, and platform diagnostics',
                'permissions' => array_values(array_unique(array_merge(
                    ['dashboard.view', 'settings.view'],
                    $module('webhooks', true),
                    $module('gateway_routing', true),
                    $viewExport('logs'),
                    $viewExport('payment_gateways'),
                    ['payment_gateways.update'],
                ))),
            ],
        ];
    }

    public static function syncRoleTemplates(string $guard = 'web'): void
    {
        foreach (self::roleTemplates() as $roleName => $meta) {
            $role = Role::query()->updateOrCreate(
                ['name' => $roleName, 'guard_name' => $guard],
                ['description' => $meta['description']],
            );

            $role->syncPermissions($meta['permissions']);
        }
    }

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = 'web';

        foreach (self::permissionDefinitions() as $name => $meta) {
            Permission::query()->updateOrCreate(
                ['name' => $name, 'guard_name' => $guard],
                [
                    'group' => $meta['group'],
                    'description' => $meta['description'],
                ],
            );
        }

        $adminRole = Role::query()->updateOrCreate(
            ['name' => 'Admin', 'guard_name' => $guard],
            ['description' => 'Full platform administrator'],
        );

        $superAdminRole = Role::query()->updateOrCreate(
            ['name' => 'Super Admin', 'guard_name' => $guard],
            ['description' => 'Full unrestricted platform access'],
        );

        $allPermissionNames = Permission::query()->where('guard_name', $guard)->pluck('name');
        $adminRole->syncPermissions($allPermissionNames);
        $superAdminRole->syncPermissions($allPermissionNames);

        self::syncRoleTemplates($guard);

        $admin = User::query()->where('email', 'admin@checkoutplatform.test')->first();

        if ($admin !== null) {
            $admin->syncRoles([$adminRole]);

            if (blank($admin->username)) {
                $admin->update(['username' => 'admin']);
            }
        }
    }
}
