<?php

namespace App\Helpers;

class GeoHelper
{
    const EARTH_RADIUS = 6371000; // Earth's radius in meters
    const ATTENDANCE_RADIUS = 50; // 50 meters radius for attendance

    /**
     * Calculate distance between two points in meters using Haversine formula
     */
    public static function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lon2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

        return $angle * self::EARTH_RADIUS;
    }

    /**
     * Check if user is within attendance radius of event location
     */
    public static function isWithinAttendanceRadius($userLat, $userLon, $eventLat, $eventLon)
    {
        $distance = self::calculateDistance($userLat, $userLon, $eventLat, $eventLon);
        return $distance <= self::ATTENDANCE_RADIUS;
    }

    /**
     * Calculate distance and check if within radius
     * Returns array with distance and within_radius status
     */
    public static function checkAttendanceRadius($userLat, $userLon, $eventLat, $eventLon)
    {
        $distance = self::calculateDistance($userLat, $userLon, $eventLat, $eventLon);
        
        return [
            'distance' => $distance,
            'within_radius' => $distance <= self::ATTENDANCE_RADIUS,
            'radius_limit' => self::ATTENDANCE_RADIUS
        ];
    }

    /**
     * Format distance for display
     */
    public static function formatDistance($distanceInMeters)
    {
        if ($distanceInMeters < 1000) {
            return round($distanceInMeters, 1) . ' m';
        }
        
        return round($distanceInMeters / 1000, 2) . ' km';
    }

    /**
     * Convert decimal degrees to DMS (Degrees, Minutes, Seconds)
     */
    public static function decimalToDMS($decimal, $isLatitude = true)
    {
        $direction = $decimal < 0 
            ? ($isLatitude ? 'S' : 'W') 
            : ($isLatitude ? 'N' : 'E');
        
        $decimal = abs($decimal);
        $degrees = floor($decimal);
        $minutesDecimal = ($decimal - $degrees) * 60;
        $minutes = floor($minutesDecimal);
        $seconds = round(($minutesDecimal - $minutes) * 60, 1);
        
        return sprintf("%d°%d'%.1f\"%s", $degrees, $minutes, $seconds, $direction);
    }
}