<?php

namespace App\Services;

use DateTime;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ArticleService
{
    public function getViewpoint($timezone)
    {
        // Base query to select the first row ordered by createdAt in descending order
        $query = DB::connection('mysql3')
            ->table('bu.Viewpoints')
            ->orderBy('createdAt', 'desc');

        // Fetch the first row from the query
        $viewpoint = $query->select('createdAt', 'id')->first(); // Use first() to execute and get the first row
        Log::info("viewpoint queried by gpt-model: ", ["viewpoint" => $viewpoint]);
        // Initialize the result object
        $result = null;

        if ($viewpoint) {
            // Convert createdAt to the specified timezone using PHP's DateTime
            $convertedDatetime = $this->convertTimeToTimezone($viewpoint->createdAt, $timezone);
            $timeGap = $this->calculateTimeGap($convertedDatetime, $timezone);

            // Create a new object or associative array with the desired information
            $result = [
                'id' => $viewpoint->id,
                'datetime' => $convertedDatetime,     // Converted timestamp
                'time_gap' => $timeGap,                // Calculated time gap
                'title' => '',
                'image_url' => '',
                'content' => '',
                'summary' => '',
                'article' => '',
                'type' => 'viewpoint'
            ];
        }

        Log::info("viewpoint returned: ", ["viewpoint" => $result]);

        return json_encode($result);
    }

    public function getArticles($timezone, $previously_shown, $limit = 2) {
        // Ensure the limit does not exceed 4
        if ($limit > 4) { $limit = 4; }

        // Get the current time in UTC
        $currentDateTimeUTC = new DateTime('now', new DateTimeZone('UTC'));
        $cutoffDateTimeUTC = $currentDateTimeUTC->modify('-24 hours'); // Subtract 24 hours to get the cutoff datetime in UTC

        // Fetch articles from the database
        $articles = DB::connection('mysql3')
            ->table('bu.Translations')
            ->select('id', 'date', 'title')
            ->orderBy('createdAt', 'desc')
            ->whereNotIn('id', $previously_shown) // Exclude articles in the recommended array
            ->where('date', '>=', $cutoffDateTimeUTC->format('Y-m-d H:i:s')) // Only articles within the last 24 hours
            ->take($limit) // Limit to 4 articles, or less if not available
            ->get(); // Fetch the articles

        // Log the initially fetched articles
        Log::info("Initial articles fetched: ", ["articles" => $articles]);

        // Initialize the result array
        $results = [];

        // Determine the language based on the timezone
        $language = match ($timezone) {
            'KST' => 'kr',
            'JST' => 'jp',
            'UST' => 'en',
            default => 'en' // Default to 'en' if the timezone is not one of the specified
        };

        // Process each fetched article
        foreach ($articles as $article) {
            // Convert createdAt to the specified timezone using PHP's DateTime
            $convertedDatetime = $this->convertTimeToTimezone($article->date, $timezone);
            $timeGap = $this->calculateTimeGap($convertedDatetime, $timezone);

            // Add the article information to the results array
            $result = [
                'id' => $article->id,
                'datetime' => $convertedDatetime,     // Converted timestamp
                'time_gap' => $timeGap,               // Calculated time gap
                'title' => $article->title,
                'image_url' => '',
                'content' => '',
                'summary' => '',
                'article' => '',
            ];

            $results[] = $result;
        }

        // Log the formatted articles
        Log::info("Articles returned: ", ["articles" => $results]);

        // Return the results, regardless of the count
        return json_encode($results);
    }


    private function convertTimeToTimezone($time, $timezone)
    {
        $timezoneObj = $this->getTimezoneObject($timezone);
        $dateTime = new DateTime($time, new DateTimeZone('UTC'));
        $dateTime->setTimezone($timezoneObj);
        return $dateTime->format('Y-m-d\TH:i:sP');
    }

    private function calculateTimeGap($localTime, $timezone)
    {
        $currentDateTime = new DateTime('now', new DateTimeZone($timezone));
        $localDateTime = new DateTime($localTime, new DateTimeZone($timezone));

        $interval = $currentDateTime->diff($localDateTime);

        return [
//            'years' => $interval->y,
//            'months' => $interval->m,
//            'days' => $interval->d,
            'hours' => $interval->h,
            'minutes' => $interval->i,
//            'seconds' => $interval->s
        ];
    }

    private function getTimezoneObject($timezone)
    {
        return match (strtoupper($timezone)) {
            'KST' => new DateTimeZone('Asia/Seoul'),
            'JST' => new DateTimeZone('Asia/Tokyo'),
            default => new DateTimeZone('UTC'),
        };
    }
}
