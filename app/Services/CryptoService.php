<?php

namespace App\Services;

use DateInterval;
use DateTime;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class CryptoService
{
    public function getCurrentTime($timezone)
    {
        $timezoneObj = $this->getTimezoneObject($timezone);
        // Create a DateTime object with the current time in the specified timezone
        $dateTime = new DateTime('now', $timezoneObj);
        // Format the time as needed, for example, in 'Y-m-d H:i:s' format
        return $dateTime->format('Y-m-d H:i:s');
    }

//    public function getTimeGap($datetime, $timezone)
//    {
//        try {
//            // Create a DateTime object from the provided $datetime
//            $dateTimeObj = new DateTime($datetime, new DateTimeZone($timezone));
//
//            // Get the current time in the specified timezone
//            $currentDateTime = new DateTime('now', new DateTimeZone($timezone));
//
//            // Calculate the difference between the current time and the provided datetime
//            $interval = $currentDateTime->diff($dateTimeObj);
//
//            // Build the time gap array with all units included
//            $timeGap = [
//                'year' => $interval->y,
//                'month' => $interval->m,
//                'day' => $interval->d,
//                'hours' => $interval->h,
//                'minutes' => $interval->i,
//                'seconds' => $interval->s
//            ];
//
//            Log::info("time gap: ", $timeGap);
//
//            return json_encode($timeGap);
//
//        } catch (Exception $e) {
//            // Handle any errors, for example, if the $datetime format is incorrect
//            return 'Error: ' . $e->getMessage();
//        }
//    }

    public function getCryptoDataInTimeRange($symbol, $startTime, $endTime, $timezone)
    {
        $symbol = $this->normalizeSymbol($symbol);
        $startTimeFormatted = $this->convertIsoToDateTime($startTime);
        $endTimeFormatted = $this->convertIsoToDateTime($endTime);

        $data = DB::connection('mysql')->table('trsi.retri_chart_data')
            ->where('simbol', $symbol)
            ->whereBetween('regdate', [$startTimeFormatted, $endTimeFormatted])
            ->orderBy('idx')
            ->select('simbol as symbol', 'score', 'price', 'regdate')
            ->get();

        $data = $this->convertToTimezone($data, $timezone);

        Log::info("symbol data", ["symboldata" => $data, "symbol" => $symbol]);

        return json_encode(['symbol' => $symbol, 'data' => $data]);
    }
//
//    public function getLatestPrice($symbols, $timezone)
//    {
//        $results = [];
//        foreach ($symbols as $symbol) {
//            $symbol = $this->normalizeSymbol($symbol);
//            $data = DB::connection('mysql')->table('trsi.retri_chart_data')
//                ->where('simbol', $symbol)
//                ->orderBy('idx', 'desc')
//                ->select('simbol as symbol', 'score', 'price', 'regdate as datetime')
//                ->first();
//
//            if ($data) {
//                $convertedDatetime = $this->convertTimeToTimezone($data->datetime, $timezone);
//                $timeGap = $this->calculateTimeGap($convertedDatetime, $timezone);
//
//                $results[] = [
//                    'symbol' => strtoupper($data->symbol),
//                    'score' => $data->score,
//                    'price' => $data->price,
//                    'datetime' => $convertedDatetime,
//                    'time_gap' => $timeGap
//                ];
//            }
//        }
//
//        return json_encode($results);
//
//    }
    public function getLatestPrice(string $symbol, $timezone)
    {
        $symbol = $this->normalizeSymbol($symbol);
        $data = DB::connection('mysql')->table('trsi.retri_chart_data')
            ->where('simbol', $symbol)
            ->orderBy('idx', 'desc')
            ->select('simbol as symbol', 'score', 'price', 'regdate as datetime')
            ->first();

        $result = null;

        if ($data) {
            $convertedDatetime = $this->convertTimeToTimezone($data->datetime, $timezone);
            $timeGap = $this->calculateTimeGap($convertedDatetime, $timezone);



            $result = [
                'symbol' => strtoupper($data->symbol),
                'score' => $data->score,
                'price' => $data->price,
                'datetime' => $convertedDatetime,
                'time_gap' => $timeGap
            ];
        }
        Log::info("get latest price result: ", ['result' => $result]);

        return json_encode($result);
    }


    public function convertToLocalTime($utc_time, $timezone)
    {
        return $this->convertTimeToTimezone($utc_time, $timezone);
    }
//
//    public function checkRecommendationStatus(array $symbols, $timezone)
//    {
//        $results = [];
//
//        foreach ($symbols as $symbol) {
//            $normalizedSymbol = $this->normalizeSymbol($symbol, false);
//
//            // Remove the appended 'USDT' from the symbol if it exists
//            if (str_ends_with($normalizedSymbol, 'USDT')) {
//                $normalizedSymbol = substr($normalizedSymbol, 0, -4); // Remove 'USDT' (4 characters)
//            }
//
//            $currentKST = new DateTime('now', new DateTimeZone('Asia/Seoul'));
//            $twelveHoursAgoKST = clone $currentKST;
//            $twelveHoursAgoKST->modify('-12 hours');
//            Log::info("current KST: ", ["currentKST" => $currentKST]);
//            Log::info("12 hours ago KST: ", ["12HoursAgoKST" => $twelveHoursAgoKST]);
//
//            // Build the query
//            $query = DB::connection('mysql2')->table('beuliping')
//                ->join('vm_beuliping_EN', 'beuliping.id', '=', 'vm_beuliping_EN.m_id')
//                ->where('beuliping.symbol', $normalizedSymbol)
//                ->whereBetween('beuliping.datetime', [$twelveHoursAgoKST, $currentKST])
//                ->orderBy('beuliping.id', 'desc')
//                ->select(
//                    'beuliping.id',
//                    'beuliping.symbol',
//                    DB::raw("DATE_SUB(beuliping.datetime, INTERVAL 9 HOUR) as datetime"),
//                    'beuliping.images',
//                    'vm_beuliping_EN.content'
//                );
//
//            // Log the raw SQL query
//            Log::info("SQL Query: ", [
//                'sql' => $query->toSql(),
//                'bindings' => $query->getBindings()
//            ]);
//
//            // Execute the query
//            $result = $query->first();
//
//            // Determine if a recommendation was found
//            $isRecommended = $result ? true : false;
//
//            // Initialize recommendTimeGap as null
//            $recommendTimeGap = null;
//
//            // Calculate recommendTime and recommendTimeGap if there is a recommendation
//            if ($isRecommended) {
//                $recommendTime = $this->convertTimeToTimezone($result->datetime, $timezone);
//                $recommendTimeGap = $this->calculateTimeGap($recommendTime, $timezone);
//            } else {
//                $recommendTime = null;
//            }
//
//            // Prepare the response for the current symbol
//            $response = [
//                'symbol' => $normalizedSymbol . 'USDT',
//                'is_recommended' => $isRecommended,
//                'recommend_time' => $recommendTime,
//                'image_url' => $isRecommended ? $result->images : null,
//                'recommended_reason' => $isRecommended ? $result->content : null,
//                'time_gap' => $recommendTimeGap
//            ];
//
//            // Log the result
//            Log::info("check_recommendation_status result for symbol: ", ["symbol" => $normalizedSymbol, "result" => $response]);
//
//            // Add the response to the results array
//            $results[] = $response;
//        }
//
//        // Return the results array as JSON
//        return json_encode($results);
//    }
    public function checkRecommendationStatus(string $symbol, $timezone)
    {
        $normalizedSymbol = $this->normalizeSymbol($symbol, false);

        // Remove the appended 'USDT' from the symbol if it exists
        if (str_ends_with($normalizedSymbol, 'USDT')) {
            $normalizedSymbol = substr($normalizedSymbol, 0, -4); // Remove 'USDT' (4 characters)
        }

        $currentKST = new DateTime('now', new DateTimeZone('Asia/Seoul'));
        $twelveHoursAgoKST = clone $currentKST;
        $twelveHoursAgoKST->modify('-12 hours');
        Log::info("current KST: ", ["currentKST" => $currentKST]);
        Log::info("12 hours ago KST: ", ["12HoursAgoKST" => $twelveHoursAgoKST]);

        // Build the query
        $query = DB::connection('mysql2')->table('beuliping')
            ->join('vm_beuliping_EN', 'beuliping.id', '=', 'vm_beuliping_EN.m_id')
            ->where('beuliping.symbol', $normalizedSymbol)
            ->whereBetween('beuliping.datetime', [$twelveHoursAgoKST, $currentKST])
            ->orderBy('beuliping.id', 'desc')
            ->select(
                'beuliping.id',
                'beuliping.symbol',
                DB::raw("DATE_SUB(beuliping.datetime, INTERVAL 9 HOUR) as datetime"),
                'beuliping.images',
                'vm_beuliping_EN.content'
            );

        // Log the raw SQL query
        Log::info("SQL Query: ", [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings()
        ]);

        // Execute the query
        $result = $query->first();

        // Determine if a recommendation was found
        $isRecommended = $result ? true : false;

        // Initialize recommendTimeGap as null
        $recommendTimeGap = null;

        // Calculate recommendTime and recommendTimeGap if there is a recommendation
        if ($isRecommended) {
            $recommendTime = $this->convertTimeToTimezone($result->datetime, $timezone);
            $recommendTimeGap = $this->calculateTimeGap($recommendTime, $timezone);
        } else {
            $recommendTime = null;
        }

        // Prepare the response for the current symbol
        $response = [
            'symbol' => $normalizedSymbol . 'USDT',
            'is_recommended' => $isRecommended,
            'recommend_time' => $recommendTime,
            'image_url' => $isRecommended ? $result->images : null,
            'recommended_reason' => $isRecommended ? $result->content : null,
            'time_gap' => $recommendTimeGap
        ];

        // Log the result
        Log::info("check_recommendation_status result for symbol: ", ["symbol" => $normalizedSymbol, "result" => $response]);

        // Return the result as JSON
        return json_encode($response);
    }

    public function analyzeCrypto($symbol, $hours = 24, $timezone = 'UTC')
    {
        // Initialize the results array
        $combinedResults = [];

        // Loop through each symbol and get the data

            $normalizedSymbol = strtoupper($this->normalizeSymbol($symbol));

            // Call each function for the current symbol
            $latestPriceData = json_decode($this->getLatestPrice($symbol, $timezone), true);
            $cryptoData = json_decode($this->getCryptoData($symbol, $hours, $timezone), true);
            $recommendationStatus = json_decode($this->checkRecommendationStatus($symbol, $timezone), true);

            // Combine the results for the current symbol
            $symbolResult = [
                'symbol' => $normalizedSymbol,
                'symbol_data' => $latestPriceData,
                'crypto_data' => $cryptoData,
                'recommendation_status' => $recommendationStatus,
                'interval' => $hours
            ];

            // Add the result for the current symbol to the combined results array
            $combinedResults[] = $symbolResult;


        // Return the combined results as JSON
        return json_encode($combinedResults);
    }


    public function getRecommendation($limit, $timezone, $already_recommended = []) {

        // Normalize each element in the coin_list and remove 'USDT' suffix
        $sanitizedCoinList = array_map(function($coin) {
            $normalizedCoin = $this->normalizeSymbol($coin);
            if (str_ends_with($normalizedCoin, 'USDT')) {
                $normalizedCoin = substr($normalizedCoin, 0, -4); // Remove 'USDT' (4 characters)
            }
            return $normalizedCoin;
        }, $already_recommended);

        //Get the recommendations using the recursive function
        $result = $this->getRecommendationsRecursive($limit, $sanitizedCoinList, $offset = 0);
        // Parse the datetime using the specified timezone
        $result = $this->convertToTimeZone($result, $timezone);
        // Return the processed result
        // Calculate the time gap for each row
        $result = $result->map(function($item) use ($timezone) {
            $item->time_gap = $this->calculateTimeGap($item->datetime, $timezone);
            return $item;
        });

        Log::info("after conversion: ", ["after" => $result]);

        return json_encode($result);
    }
    private function getRecommendationsRecursive($limit, $coin_list = [], $offset = 0, $accumulatedResults = null)
    {
        // Initialize the accumulated results if not already initialized
        if ($accumulatedResults === null) {
            $accumulatedResults = collect();
        }

        // Base case 1: If the limit is reached or no more rows to query, return the accumulated results
        if ($limit <= 0) {
            return $accumulatedResults;
        }

        // Query 7 rows from the database starting from the given offset
        $queryResults = DB::connection('mysql2')->table('beuliping')
            ->join('vm_beuliping_EN', 'beuliping.id', '=', 'vm_beuliping_EN.m_id')
            ->orderBy('beuliping.id', 'desc')
            ->offset($offset)
            ->limit(7)
            ->select(
                'beuliping.id',
                'beuliping.symbol',
                'beuliping.images as image_url',
                'vm_beuliping_EN.content as recommended_reason',
                DB::raw('DATE_SUB(beuliping.datetime, INTERVAL 9 HOUR) as regdate')
            )
            ->get();

        // Base case 2: If there are no more rows to query, return the accumulated results
        if ($queryResults->isEmpty()) {
            return $accumulatedResults;
        }

        // Filter out coins that are already in the coin list
        $newResults = $queryResults->filter(function ($item) use ($coin_list) {
            return $item->symbol !== '1000BONK'
                && $item->symbol !== 'RAD' // Exclude symbol 'RAD'
                && !is_null($item->image_url)
                && !in_array($item->symbol, $coin_list)
                && stripos($item->recommended_reason, 'no') !== 0
                && stripos($item->recommended_reason, 'there') === false;
        });

        // Add new results to the accumulated results
        $accumulatedResults = $accumulatedResults->merge($newResults);

        // Update the coin list with new symbols
        $newCoinList = array_merge($coin_list, $newResults->pluck('symbol')->toArray());

        // Calculate the remaining limit after adding the new results
        $remainingLimit = $limit - $newResults->count();

        // Base case 3: If we have reached the limit or there is no remaining limit, return the accumulated results
        if ($remainingLimit <= 0) {
            return $accumulatedResults;
        } else {
            // Recursive call logic if we still need more results
            return $this->getRecommendationsRecursive($remainingLimit, $newCoinList, $offset + 7, $accumulatedResults);
        }
    }


//
//    public function getCryptoData(array $symbols, $hours = 24, $timezone = 'UTC')
//    {
//        $results = [];
//
//        $currentDateTime = new DateTime('now', new DateTimeZone('UTC'));
//        $startDateTime = clone $currentDateTime;
//        $startDateTime->modify('-' . $hours . ' hours');
//
//        $startTimeFormatted = $startDateTime->format('Y-m-d H:i:s');
//        $endTimeFormatted = $currentDateTime->format('Y-m-d H:i:s');
//        Log::info("start and end: ", ["start" => $startTimeFormatted, "end" => $endTimeFormatted]);
//
//        foreach ($symbols as $symbol) {
//            $symbol = $this->normalizeSymbol($symbol);
//
//            if ($hours <= 48) {
//                // Logic for hourly interval
//                if ($hours == 1) {
//                    $hours = 24;
//                }
//                $data = DB::connection('mysql')->table('trsi.retri_chart_data')
//                    ->where('simbol', $symbol)
//                    ->whereBetween('regdate', [$startTimeFormatted, $endTimeFormatted])
//                    ->orderBy('regdate')
//                    ->select('simbol as symbol', 'score', 'price', 'regdate')
//                    ->get();
//
//                $formattedData = $this->formatDataWithTimezone($data, $symbol, $timezone);
//            } else {
//                if ($hours > 720) { $hours = 720; }
//                // Logic for daily interval (more than 48 hours)
//                $data = DB::connection('mysql')->table('trsi.retri_chart_data')
//                    ->where('simbol', $symbol)
//                    ->whereBetween('regdate', [$startTimeFormatted, $endTimeFormatted])
//                    ->orderBy('regdate')
//                    ->select('simbol as symbol', 'score', 'price', 'regdate')
//                    ->get();
//
//                $formattedData = $this->averageAndFormatData($data, $symbol, $timezone);
//            }
//
//            // Add the formatted data to the results array
//            $results[$symbol] = $formattedData;
//        }
//
//        Log::info("get_crypto_data results: ", ["results" => $results]);
//
//        return json_encode($results);
//    }
    public function getCryptoData(string $symbol, $hours = 24, $timezone = 'UTC')
    {
        if ($hours < 2) { $hours = 24; }
        if ($hours > 720) { $hours = 720; }

        $currentDateTime = new DateTime('now', new DateTimeZone('UTC'));
        $startDateTime = clone $currentDateTime;
        $startDateTime->modify('-' . $hours . ' hours');

        $startTimeFormatted = $startDateTime->format('Y-m-d H:i:s');
        $endTimeFormatted = $currentDateTime->format('Y-m-d H:i:s');
        Log::info("start and end: ", ["start" => $startTimeFormatted, "end" => $endTimeFormatted]);

        $symbol = $this->normalizeSymbol($symbol);

        if ($hours <= 48) {
            // Logic for hourly interval
            $data = DB::connection('mysql')->table('trsi.retri_chart_data')
                ->where('simbol', $symbol)
                ->whereBetween('regdate', [$startTimeFormatted, $endTimeFormatted])
                ->orderBy('regdate')
                ->select('simbol as symbol', 'score', 'price', 'regdate')
                ->get();

            $formattedData = $this->formatDataWithTimezone($data, $symbol, $timezone);
        } else {

            // Logic for daily interval (more than 48 hours)
            $data = DB::connection('mysql')->table('trsi.retri_chart_data')
                ->where('simbol', $symbol)
                ->whereBetween('regdate', [$startTimeFormatted, $endTimeFormatted])
                ->orderBy('regdate')
                ->select('simbol as symbol', 'score', 'price', 'regdate')
                ->get();

            $formattedData = $this->averageAndFormatData($data, $symbol, $timezone);
        }

        // Add the formatted data to the results array
        return $formattedData;
    }

    // Private helper functions
    private function normalizeSymbol($symbol, $appendUSDT = true)
    {
        $symbol = strtoupper($symbol);

        // Remove 'SDT' or 'USDT' if they are at the end of the symbol
        if (str_ends_with($symbol, 'USDT')) {
            $symbol = substr($symbol, 0, -4); // Remove the last 4 characters ('USDT')
        } elseif (str_ends_with($symbol, 'SDT')) {
            $symbol = substr($symbol, 0, -3); // Remove the last 3 characters ('SDT')
        }

        // Optionally append 'USDT' if $appendUSDT is true
        if ($appendUSDT) {
            $symbol .= 'USDT';
        }

        return $symbol;
    }

    private function convertIsoToDateTime($isoTime)
    {
        $dateTime = DateTime::createFromFormat(DateTime::ISO8601, $isoTime, new DateTimeZone('UTC'));
        if ($dateTime === false) {
            throw new \Exception("Invalid datetime format. Please use ISO 8601 format.");
        }
        return $dateTime->format('Y-m-d H:i:s');
    }

    private function getTimezoneObject($timezone)
    {
        return match (strtoupper($timezone)) {
            'KST' => new DateTimeZone('Asia/Seoul'),
            'JST' => new DateTimeZone('Asia/Tokyo'),
            default => new DateTimeZone('UTC'),
        };
    }

    private function convertTimeToTimezone($time, $timezone)
    {
        $timezoneObj = $this->getTimezoneObject($timezone);
        $dateTime = new DateTime($time, new DateTimeZone('UTC'));
        $dateTime->setTimezone($timezoneObj);
        return $dateTime->format('Y-m-d\TH:i:sP');
    }

    private function convertToTimezone($data, $timezone)
    {
        $timezoneObj = $this->getTimezoneObject($timezone);
        return $data->map(function($item) use ($timezoneObj) {
            $dateTime = new DateTime($item->regdate, new DateTimeZone('UTC'));
            $dateTime->setTimezone($timezoneObj);
            $item->datetime = $dateTime->format('Y-m-d\TH:i:sP');
            unset($item->regdate);
            return $item;
        });
    }

    private function getFormattedCurrentTime($timezone)
    {
        $dateTime = new DateTime('now', new DateTimeZone($timezone));
        return $dateTime->format('Y-m-d\TH:i:s\Z');
    }

    private function subtractHours($time, $hours)
    {
        $dateTime = new DateTime($time, new DateTimeZone('UTC'));
        $dateTime->sub(new DateInterval('PT' . $hours . 'H'));
        return $dateTime->format('Y-m-d\TH:i:s\Z');
    }

    private function formatDataWithTimezone($data, $timezone)
    {
        $timezoneObj = $this->getTimezoneObject($timezone);
        $resultData = [];
        foreach ($data as $item) {
            $regDate = new DateTime($item->regdate, new DateTimeZone('UTC'));
            $regDate->setTimezone($timezoneObj);
            $resultData[] = [
                'symbol' => $item->symbol,
                'score' => $item->score,
                'price' => $item->price,
                'datetime' => $regDate->format('Y-m-d\TH:i:sP'),
            ];
        }
        Log::info("hourlySymbolData", ["symboldata" => $resultData]);
        return json_encode($resultData);
    }

//    private function averageAndFormatData($data, $symbol, $timezone)
//    {
////        $timezoneObj = $this->getTimezoneObject($timezone);
////        $averagedData = [];
////        $chunk = [];
////        foreach ($data as $key => $item) {
////            $chunk[] = $item;
////            if (count($chunk) == 24 || $key == $data->count() - 1) {
////                $avgPrice = round(collect($chunk)->avg('price'), 4);
////                $avgScore = round(collect($chunk)->avg('score'), 4);
////                $lastDate = new DateTime(end($chunk)->regdate, new DateTimeZone('UTC'));
////                $lastDate->setTimezone($timezoneObj);
////                $averagedData[] = [
////                    'symbol' => $symbol,
////                    'average_price' => $avgPrice,
////                    'average_score' => $avgScore,
////                    'datetime' => $lastDate->format('Y-m-d\TH:i:sP'),
////                ];
////                $chunk = [];
////            }
////        }
////        Log::info("Averaged symbol data", ["symboldata" => $averagedData, "symbol" => $symbol]);
////        return ['symbol' => $symbol, 'data' => $averagedData];
//        // Check if the data is not empty
//        if ($data->isEmpty()) {
//            return ['symbol' => $symbol, 'data' => []]; // Return an empty array if no data is present
//        }
//
//        // Get the last element from the data
//        $lastItem = $data->last();
//
//        // Convert the datetime to the desired timezone
//        $timezoneObj = $this->getTimezoneObject($timezone);
//        $dateTime = new DateTime($lastItem->regdate, new DateTimeZone('UTC'));
//        $dateTime->setTimezone($timezoneObj);
//
//        // Format the result with the last element's data
//        $formattedData = [
//            [
//                'symbol' => $symbol,
//                'price' => $lastItem->price,
//                'score' => $lastItem->score,
//                'datetime' => $dateTime->format('Y-m-d\TH:i:sP'),
//            ]
//        ];
//
//        // Log the last element's data
//        Log::info("Last element symbol data", ["symboldata" => $formattedData, "symbol" => $symbol]);
//
//        // Return the result as an array
//        return ['symbol' => $symbol, 'data' => $formattedData];
//    }
    private function averageAndFormatData($data, $timezone)
    {
        // Check if the collection is not empty
        if ($data->isEmpty()) {
            return json_encode([]); // Return an empty array if no data is present
        }

        // Initialize variables
        $formattedData = [];
        $timezoneObj = $this->getTimezoneObject($timezone);
        $chunkSize = 24;

        // Process data in chunks of 24 using the collection's chunk method
        $data->chunk($chunkSize)->each(function ($chunk) use (&$formattedData, $timezoneObj) {
            // Get the last element of the chunk
            $lastItem = $chunk->last();

            // Convert the datetime of the last element to the desired timezone
            $dateTime = new DateTime($lastItem->regdate, new DateTimeZone('UTC'));
            $dateTime->setTimezone($timezoneObj);

            // Format the chunk data
            $formattedData[] = [
                'symbol' => $lastItem->symbol,
                'score' => $lastItem->score,
                'price' => $lastItem->price,
                'datetime' => $dateTime->format('Y-m-d\TH:i:sP'),
            ];
        });

        // Log the formatted symbol data
        Log::info("Formatted symbol data", ["dailySymboldata" => $formattedData]);

        // Return the result as an array
        return json_encode($formattedData);
    }



    private function calculateTimeGap($recommendTime, $timezone)
    {
        $currentDateTime = new DateTime('now', new DateTimeZone($timezone));
        $recommendDateTime = new DateTime($recommendTime, new DateTimeZone($timezone));

        $interval = $currentDateTime->diff($recommendDateTime);

        return [
//            'years' => $interval->y,
//            'months' => $interval->m,
//            'days' => $interval->d,
            'hours' => $interval->h,
            'minutes' => $interval->i,
//            'seconds' => $interval->s
        ];
    }
}
