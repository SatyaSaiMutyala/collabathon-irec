<?php

namespace App\Support;

/**
 * Static placeholder data standing in for the real backend/database.
 * Mirrors the shape used by the mobile app's mockDevelopers.js / mockLeads.js
 * so the two clients present the same underlying domain model.
 */
class AdminMockData
{
    public static function developers(): array
    {
        return [
            ['id' => 1, 'company' => 'Skyline Realty Group', 'contact' => 'Ahmed Al Farsi', 'mobile' => '+971 50 123 4567', 'email' => 'ahmed@skylinerealty.ae', 'city' => 'Dubai', 'cpPayout' => '2.5%', 'properties' => 6, 'status' => 'active', 'createdAt' => '2026-05-12'],
            ['id' => 2, 'company' => 'Meridian Builders', 'contact' => 'Fatima Noor', 'mobile' => '+971 55 987 6543', 'email' => 'fatima@meridianbuilders.ae', 'city' => 'Abu Dhabi', 'cpPayout' => '3.0%', 'properties' => 4, 'status' => 'active', 'createdAt' => '2026-06-02'],
            ['id' => 3, 'company' => 'Palm Coast Developers', 'contact' => 'Rakesh Menon', 'mobile' => '+971 52 456 7890', 'email' => 'rakesh@palmcoast.ae', 'city' => 'Dubai', 'cpPayout' => '2.0%', 'properties' => 9, 'status' => 'active', 'createdAt' => '2026-06-20'],
            ['id' => 4, 'company' => 'Horizon Estates', 'contact' => 'Layla Haddad', 'mobile' => '+971 56 321 0987', 'email' => 'layla@horizonestates.ae', 'city' => 'Sharjah', 'cpPayout' => '2.75%', 'properties' => 3, 'status' => 'active', 'createdAt' => '2026-07-05'],
        ];
    }

    public static function pendingBrokers(): array
    {
        return [
            ['id' => 101, 'name' => 'Sana Iqbal', 'company' => 'Iqbal Realty Partners', 'mobile' => '+971 58 111 2233', 'email' => 'sana@iqbalrealty.ae', 'rera' => 'RERA-88213', 'city' => 'Dubai', 'segments' => ['Residential', 'Commercial'], 'submittedAt' => '2026-07-24'],
            ['id' => 102, 'name' => 'Marco Silva', 'company' => 'Silva & Co Properties', 'mobile' => '+971 50 444 5566', 'email' => 'marco@silvaco.ae', 'rera' => 'RERA-77410', 'city' => 'Abu Dhabi', 'segments' => ['Residential'], 'submittedAt' => '2026-07-23'],
            ['id' => 103, 'name' => 'Priya Nair', 'company' => 'Nair Property Consultants', 'mobile' => '+971 52 778 8899', 'email' => 'priya@nairproperty.ae', 'rera' => 'RERA-90045', 'city' => 'Dubai', 'segments' => ['Commercial', 'Land'], 'submittedAt' => '2026-07-22'],
        ];
    }

    public static function recentDecisions(): array
    {
        return [
            ['name' => 'Yusuf Al Balushi', 'company' => 'Al Balushi Realty', 'decision' => 'approved', 'date' => '2026-07-21'],
            ['name' => 'Karan Mehta', 'company' => 'Mehta Estates', 'decision' => 'rejected', 'date' => '2026-07-20'],
        ];
    }

    public static function properties(): array
    {
        return [
            ['id' => 1, 'name' => 'Azure Bay Residences', 'developer' => 'Skyline Realty Group', 'developerId' => 1, 'city' => 'Dubai Marina', 'type' => 'Residential', 'price' => 'AED 1.8M – 3.2M', 'status' => 'active', 'interested' => 12],
            ['id' => 2, 'name' => 'The Meridian Tower', 'developer' => 'Meridian Builders', 'developerId' => 2, 'city' => 'Al Reem Island', 'type' => 'Residential', 'price' => 'AED 950K – 1.6M', 'status' => 'active', 'interested' => 7],
            ['id' => 3, 'name' => 'Palm Coast Villas', 'developer' => 'Palm Coast Developers', 'developerId' => 3, 'city' => 'Palm Jumeirah', 'type' => 'Villa', 'price' => 'AED 6.5M – 11M', 'status' => 'active', 'interested' => 19],
            ['id' => 4, 'name' => 'Horizon Business Bay', 'developer' => 'Horizon Estates', 'developerId' => 4, 'city' => 'Business Bay', 'type' => 'Commercial', 'price' => 'AED 2.1M – 4M', 'status' => 'draft', 'interested' => 0],
        ];
    }

    public static function leadsAndMatches(): array
    {
        return [
            ['broker' => 'Rohit Sharma', 'property' => 'Azure Bay Residences', 'developer' => 'Skyline Realty Group', 'status' => 'accepted', 'date' => '2026-07-24'],
            ['broker' => 'Aisha Rahman', 'property' => 'Palm Coast Villas', 'developer' => 'Palm Coast Developers', 'status' => 'interested', 'date' => '2026-07-24'],
            ['broker' => 'Daniel Cho', 'property' => 'The Meridian Tower', 'developer' => 'Meridian Builders', 'status' => 'viewed', 'date' => '2026-07-23'],
            ['broker' => 'Nadia Hussain', 'property' => 'Azure Bay Residences', 'developer' => 'Skyline Realty Group', 'status' => 'declined', 'date' => '2026-07-22'],
            ['broker' => 'Vikram Patel', 'property' => 'Palm Coast Villas', 'developer' => 'Palm Coast Developers', 'status' => 'interested', 'date' => '2026-07-22'],
            ['broker' => 'Elena Petrova', 'property' => 'The Meridian Tower', 'developer' => 'Meridian Builders', 'status' => 'viewed', 'date' => '2026-07-21'],
        ];
    }

    public static function dashboardStats(): array
    {
        return [
            'developers' => count(self::developers()),
            'brokers' => 58,
            'properties' => count(self::properties()),
            'pendingApprovals' => count(self::pendingBrokers()),
            'matches' => 14,
        ];
    }

    public static function recentActivity(): array
    {
        return [
            ['icon' => 'user-plus', 'text' => 'Sana Iqbal submitted a broker registration', 'time' => '2 hours ago'],
            ['icon' => 'check', 'text' => 'Rohit Sharma\'s interest in Azure Bay Residences was accepted', 'time' => '5 hours ago'],
            ['icon' => 'building', 'text' => 'Horizon Estates was assigned "Horizon Business Bay"', 'time' => '1 day ago'],
            ['icon' => 'eye', 'text' => 'Elena Petrova viewed The Meridian Tower', 'time' => '1 day ago'],
        ];
    }

    public static function configurableFields(): array
    {
        return [
            'Broker Registration' => [
                ['label' => 'Full Name', 'enabled' => true, 'required' => true],
                ['label' => 'Mobile Number', 'enabled' => true, 'required' => true],
                ['label' => 'Email', 'enabled' => true, 'required' => true],
                ['label' => 'Company Name', 'enabled' => true, 'required' => false],
                ['label' => 'RERA Number', 'enabled' => true, 'required' => true],
                ['label' => 'GST Number', 'enabled' => true, 'required' => false],
                ['label' => 'Years of Experience', 'enabled' => false, 'required' => false],
            ],
            'Property Listing' => [
                ['label' => 'Project Name', 'enabled' => true, 'required' => true],
                ['label' => 'Price Range', 'enabled' => true, 'required' => true],
                ['label' => 'Location', 'enabled' => true, 'required' => true],
                ['label' => 'Amenities', 'enabled' => true, 'required' => false],
                ['label' => 'CP Commission %', 'enabled' => true, 'required' => true],
                ['label' => 'Construction Progress', 'enabled' => false, 'required' => false],
            ],
        ];
    }
}
