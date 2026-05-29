<?php
/**
 * Affiliate Marketing Plugin Hooks
 * Include this file from application bootstrap, public index, checkout success, payment callback, refund, and cancel handlers.
 */

require_once __DIR__ . '/affiliate_marketing.php';

function affiliate_plugin_boot()
{
    return affiliate_track_visit_from_request();
}

function affiliate_plugin_on_order_created($orderId, $orderTotal)
{
    return affiliate_create_order_record($orderId, $orderTotal);
}

function affiliate_plugin_on_payment_paid($orderId, $paidAt = null)
{
    return affiliate_mark_order_paid($orderId, $paidAt);
}

function affiliate_plugin_on_order_disputed($orderId, $reason = 'Order disputed')
{
    return affiliate_mark_order_disputed($orderId, $reason);
}

function affiliate_plugin_on_order_refunded($orderId, $reason = 'Order refunded')
{
    return affiliate_mark_order_refunded($orderId, $reason);
}

function affiliate_plugin_on_order_cancelled($orderId, $reason = 'Order cancelled')
{
    return affiliate_mark_order_cancelled($orderId, $reason);
}

function affiliate_plugin_admin_menu_items()
{
    return [
        [
            'label' => 'Affiliate Dashboard',
            'url' => 'affiliate_admin.php?page=dashboard',
            'permission' => 'affiliate.view',
        ],
        [
            'label' => 'Affiliate Users',
            'url' => 'affiliate_admin.php?page=users',
            'permission' => 'affiliate.manage_users',
        ],
        [
            'label' => 'Affiliate Campaigns',
            'url' => 'affiliate_admin.php?page=campaigns',
            'permission' => 'affiliate.manage_campaigns',
        ],
        [
            'label' => 'Affiliate Traffic',
            'url' => 'affiliate_admin.php?page=traffic',
            'permission' => 'affiliate.report',
        ],
        [
            'label' => 'Affiliate Commissions',
            'url' => 'affiliate_admin.php?page=commissions',
            'permission' => 'affiliate.commission',
        ],
    ];
}
