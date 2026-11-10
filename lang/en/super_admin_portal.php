<?php

$translations = json_decode(file_get_contents(__DIR__.'/../admin-portal.json'), true, 512, JSON_THROW_ON_ERROR);
$catalog = array_combine(array_keys($translations), array_keys($translations));

return array_replace($catalog, [
    'intro_dashboard' => 'Here is the platform overview.',
    'intro_hospitals' => 'Manage partner hospitals and clinic information.',
    'intro_customers' => 'View and manage demo customer accounts.',
    'intro_requests' => 'View and track all patient requests across the platform.',
    'intro_quotations' => 'Review quotations sent by hospitals to customers.',
    'intro_bookings' => 'Manage all bookings and appointments.',
    'intro_payments' => 'Manage demo booking deposits and payment records.',
    'intro_support' => 'Chat with customers and provide assistance in the demo inbox.',
    'intro_booking-chats' => 'Shared rooms for the customer, selected hospital and support.',
    'intro_reports' => 'View platform statistics and performance.',
    'intro_settings' => 'Manage platform settings in demo mode.',
]);
