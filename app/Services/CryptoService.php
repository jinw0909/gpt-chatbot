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
        Log::info("time", ["time" => $dateTime]);
        return $dateTime->format('Y-m-d H:i:s');
    }
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
                'symbol_data' => [
                    'symbol' => strtoupper($data->symbol),
                    'score' => $data->score,
                    'price' => $data->price,
                    'datetime' => $convertedDatetime,
                    'time_gap' => $timeGap
                ]
            ];
        }
        Log::info("get latest price result: ", ["result" => $result]);

        return json_encode($result);
    }
    public function convertToLocalTime($utc_time, $timezone)
    {
        return $this->convertTimeToTimezone($utc_time, $timezone);
    }
    public function checkRecommendationStatus(string $symbol, $timezone)
    {
        $normalizedSymbol = $this->normalizeSymbol($symbol, false);

        // Remove the appended 'USDT' from the symbol if it exists
        if (str_ends_with($normalizedSymbol, 'USDT')) {
            $normalizedSymbol = substr($normalizedSymbol, 0, -4); // Remove 'USDT' (4 characters)
        }

        $currentKST = new DateTime('now', new DateTimeZone('Asia/Seoul'));
        $twelveHoursAgoKST = clone $currentKST;
        $twelveHoursAgoKST->modify('-4 hours');
        Log::info("current KST: ", ["currentKST" => $currentKST]);
        Log::info("12 hours ago KST: ", ["12HoursAgoKST" => $twelveHoursAgoKST]);

        // Build the query
        $query = DB::connection('mysql2')->table('vm_beuliping')
            ->join('vm_beuliping_EN', 'vm_beuliping.id', '=', 'vm_beuliping_EN.m_id')
            ->where('vm_beuliping.symbol', $normalizedSymbol)
            ->whereBetween('vm_beuliping.datetime', [$twelveHoursAgoKST, $currentKST])
            ->whereNotNull('vm_beuliping.images')
            ->orderBy('vm_beuliping.id', 'desc')
            ->select(
                'vm_beuliping.id',
                'vm_beuliping.symbol',
                DB::raw("DATE_SUB(vm_beuliping.datetime, INTERVAL 9 HOUR) as datetime"),
                'vm_beuliping.images',
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
//    public function checkRecommendationStatus(string $symbol, $timezone)
//    {
//        $normalizedSymbol = $this->normalizeSymbol($symbol, false);
//
//        // Remove the appended 'USDT' from the symbol if it exists
//        if (str_ends_with($normalizedSymbol, 'USDT')) {
//            $normalizedSymbol = substr($normalizedSymbol, 0, -4); // Remove 'USDT' (4 characters)
//        }
//
//        // Determine the language suffix based on the timezone
//        $userLanguage = 'EN'; // Default is English
//        if ($timezone === 'KST') {
//            $userLanguage = 'KR'; // Korean
//        } elseif ($timezone === 'JST') {
//            $userLanguage = 'JP'; // Japanese
//        }
//
//        // Build the dynamic table name
//        $tableWithLanguage = 'vm_beuliping_' . $userLanguage;
//
//        $currentKST = new DateTime('now', new DateTimeZone('Asia/Seoul'));
//        $twelveHoursAgoKST = clone $currentKST;
//        $twelveHoursAgoKST->modify('-6 hours');
//        Log::info("current KST: ", ["currentKST" => $currentKST]);
//        Log::info("12 hours ago KST: ", ["12HoursAgoKST" => $twelveHoursAgoKST]);
//
//        // Build the query
//        $query = DB::connection('mysql2')->table('vm_beuliping')
//            ->join($tableWithLanguage, 'vm_beuliping.id', '=', "$tableWithLanguage.m_id")
//            ->where('vm_beuliping.symbol', $normalizedSymbol)
//            ->whereBetween('vm_beuliping.datetime', [$twelveHoursAgoKST, $currentKST])
//            ->whereNotNull('vm_beuliping.images')
//            ->orderBy('vm_beuliping.id', 'desc')
//            ->select(
//                'vm_beuliping.id',
//                'vm_beuliping.symbol',
//                DB::raw("DATE_SUB(vm_beuliping.datetime, INTERVAL 9 HOUR) as datetime"),
//                'vm_beuliping.images',
//                "$tableWithLanguage.content"
//            );
//
//        // Log the raw SQL query
//        Log::info("SQL Query: ", [
//            'sql' => $query->toSql(),
//            'bindings' => $query->getBindings()
//        ]);
//
//        // Execute the query
//        $result = $query->first();
//
//        // Determine if a recommendation was found
//        $isRecommended = $result ? true : false;
//
//        // Initialize recommendTimeGap as null
//        $recommendTimeGap = null;
//
//        // Calculate recommendTime and recommendTimeGap if there is a recommendation
//        if ($isRecommended) {
//            $recommendTime = $this->convertTimeToTimezone($result->datetime, $timezone);
//            $recommendTimeGap = $this->calculateTimeGap($recommendTime, $timezone);
//        } else {
//            $recommendTime = null;
//        }
//
//        // Prepare the response for the current symbol
//        $response = [
//            'symbol' => $normalizedSymbol . 'USDT',
//            'is_recommended' => $isRecommended,
//            'recommend_time' => $recommendTime,
//            'image_url' => $isRecommended ? $result->images : null,
//            'recommended_reason' => $isRecommended ? $result->content : null,
//            'time_gap' => $recommendTimeGap
//        ];
//
//        // Log the result
//        Log::info("check_recommendation_status result for symbol: ", ["symbol" => $normalizedSymbol, "result" => $response]);
//
//        // Return the result as JSON
//        return json_encode($response);
//    }
    public function analyzeCrypto(array $symbols, $hours = 24, $timezone = 'UTC')
    {
        // Initialize the results array
        $combinedResults = [];

        // Loop through each symbol and get the data
        foreach ($symbols as $symbol) {
            $normalizedSymbol = strtoupper($this->normalizeSymbol($symbol));
            // Call each function for the current symbol
            $latestPriceData = json_decode($this->getLatestPrice($symbol, $timezone), true);
            $cryptoData = json_decode($this->getCryptoData($symbol, $hours, $timezone), true);
            $recommendationStatus = json_decode($this->checkRecommendationStatus($symbol, $timezone), true);
//            $cryptoLogo = $this->getCryptoLogo($symbol);

            // Combine the results for the current symbol
            $symbolResult = [
                'symbol' => $normalizedSymbol,
//                'symbol_logo' => $cryptoLogo,
                'symbol_data' => $latestPriceData,
                'crypto_data' => $cryptoData,
                'recommendation_status' => $recommendationStatus,
                'interval' => $hours
            ];

            // Add the result for the current symbol to the combined results array
            $combinedResults[] = $symbolResult;
        }

        // Return the combined results as JSON
        return json_encode($combinedResults);
    }
    public function getRecommendation($limit = 3, $timezone, $already_recommended = []) {
        if ($limit === 0) { $limit = 3; }
        if ($limit > 10) { $limit = 10; }
        // Normalize each element in the coin_list and remove 'USDT' suffix
        $sanitizedCoinList = array_map(function($coin) {
            $normalizedCoin = $this->normalizeSymbol($coin);
            if (str_ends_with($normalizedCoin, 'USDT')) {
                $normalizedCoin = substr($normalizedCoin, 0, -4); // Remove 'USDT' (4 characters)
            }
            return $normalizedCoin;
        }, $already_recommended);

        // Map timezones to specific strings
        switch ($timezone) {
            case 'UTC':
                $mappedTimezone = 'EN';
                break;
            case 'KST':
                $mappedTimezone = 'KR';
                break;
            case 'JST':
                $mappedTimezone = 'JP';
                break;
            default:
                $mappedTimezone = 'EN'; // Default to 'EN'
                break;
        }

        //Get the recommendations using the recursive function
        $result = $this->getRecommendations($limit, $sanitizedCoinList);
        // Parse the datetime using the specified timezone
        $result = $this->convertToTimeZone($result, $timezone);
        // Return the processed result
        // Calculate the time gap for each row
        $result = $result->map(function($item) use ($timezone) {
            $item->time_gap = $this->calculateTimeGap($item->datetime, $timezone);
//            $item->symbol_logo = $this->getCryptoLogo($item->symbol);
            return $item;
        });

        $jsonResult = json_encode($result);
        Log::info($jsonResult);

        return $jsonResult;
    }
//    private function getRecommendations($limit, $timezone, $coin_list = [])
//    {
//        // Define the excluded symbols directly in the SQL query
//        $excludedSymbols = ['1000BONK', 'RAD', 'BANANA', 'ALPACA' , 'NULS', 'DOGS', 'SUN', 'OMG', 'QUICK'];
//
//        // Get the current UTC time
//        $currentUtcTime = new DateTime('now', new DateTimeZone('UTC'));
//        // Subtract 4 hours from the current UTC time to get the lower bound
//        $fourHoursAgoUtcTime = $currentUtcTime->modify('-4 hours')->format('Y-m-d H:i:s');
//
//        $tableName = 'vm_beuliping_' . strtoupper($timezone);
//
//        // Step 1: Apply filters first to reduce the number of rows
//        $filteredRows = DB::connection('mysql2')->table('beuliping')
//            ->join('vm_beuliping_EN', 'beuliping.id', '=', 'vm_beuliping_EN.m_id')
//            ->select(
//                'beuliping.id',
//                'beuliping.symbol',
//                'beuliping.images as image_url',
//                'vm_beuliping_EN.content as recommended_reason',
//                DB::raw('DATE_SUB(beuliping.datetime, INTERVAL 9 HOUR) as regdate')
//            )
//            ->whereNotIn('beuliping.symbol', $excludedSymbols) // Exclude specific symbols
//            ->whereNotNull('beuliping.images') // Ensure image_url is not null
//            ->whereNotIn('beuliping.symbol', $coin_list) // Exclude symbols already in the coin list
//            ->where(function ($query) {
//                // Apply conditions to filter based on recommended_reason content
//                $query->where('vm_beuliping_EN.content', 'not like', 'no%')
//                    ->where('vm_beuliping_EN.content', 'not like', '%there%');
//            })
//            ->whereRaw("DATE_SUB(beuliping.datetime, INTERVAL 9 HOUR) >= ?", [$fourHoursAgoUtcTime]) // Add condition for regdate within the last 4 hours
//            ->orderBy('beuliping.id', 'desc') // Order by id in descending order to get recent rows first
//            ->get(); // Fetch all filtered rows
//
//        // Step 2: Use Collection methods to group by 'symbol' and keep only the most recent row
//        $uniqueResults = $filteredRows->groupBy('symbol')->map(function ($group) {
//            return $group->first(); // Keep only the most recent row for each symbol
//        });
//
//        // Step 3: Take the desired number of rows (limit)
//        $finalResults = $uniqueResults->take($limit);
//
//        // Log the fetched results
//        Log::info("Fetched recommendations: ", ["recommendations" => $finalResults]);
//
//        // Return the results
//        return $finalResults->values(); // Return as a collection or array
//    }
//    private function getRecommendations($limit, $timezone, $coin_list = [])
//{
//    // Define the excluded symbols directly in the SQL query
//    $excludedSymbols = ['1000BONK', 'RAD', 'BANANA', 'ALPACA', 'NULS', 'DOGS', 'SUN', 'OMG', 'QUICK'];
//
//    // Get the current UTC time
//    $currentUtcTime = new DateTime('now', new DateTimeZone('UTC'));
//    // Subtract 4 hours from the current UTC time to get the lower bound
//    $fourHoursAgoUtcTime = $currentUtcTime->modify('-4 hours')->format('Y-m-d H:i:s');
//
//    // Dynamically set the table name based on the $timezone
//    $tableName = 'vm_beuliping_' . strtoupper($timezone); // Concatenate the timezone
//
//    // Step 1: Apply filters first to reduce the number of rows
//    $filteredRows = DB::connection('mysql2')->table('beuliping')
//        ->join($tableName, 'beuliping.id', '=', "$tableName.m_id") // Use dynamic table name
//        ->select(
//            'beuliping.id',
//            'beuliping.symbol',
//            'beuliping.images as image_url',
//            "$tableName.content as recommended_reason", // Use dynamic table name for content
//            DB::raw('DATE_SUB(beuliping.datetime, INTERVAL 9 HOUR) as regdate')
//        )
//        ->whereNotIn('beuliping.symbol', $excludedSymbols) // Exclude specific symbols
//        ->whereNotNull('beuliping.images') // Ensure image_url is not null
//        ->whereNotIn('beuliping.symbol', $coin_list) // Exclude symbols already in the coin list
//        ->where(function ($query) use ($tableName) {
//            // Apply conditions to filter based on recommended_reason content dynamically
//            $query->where("$tableName.content", 'not like', 'no%')
//                ->where("$tableName.content", 'not like', '%there%');
//        })
//        ->whereRaw("DATE_SUB(beuliping.datetime, INTERVAL 9 HOUR) >= ?", [$fourHoursAgoUtcTime]) // Add condition for regdate within the last 4 hours
//        ->orderBy('beuliping.id', 'desc') // Order by id in descending order to get recent rows first
//        ->get(); // Fetch all filtered rows
//
//    // Step 2: Use Collection methods to group by 'symbol' and keep only the most recent row
//    $uniqueResults = $filteredRows->groupBy('symbol')->map(function ($group) {
//        return $group->first(); // Keep only the most recent row for each symbol
//    });
//
//    // Step 3: Take the desired number of rows (limit)
//    $finalResults = $uniqueResults->take($limit);
//
//    // Log the fetched results
//    Log::info("Fetched recommendations: ", ["recommendations" => $finalResults]);
//
//    // Return the results
//    return $finalResults->values(); // Return as a collection or array
//}
    private function getRecommendations($limit, $coin_list = [])
    {
        // Define the excluded symbols directly in the SQL query
        $excludedSymbols = ['1000BONK'];

        // Get the current UTC time
        $currentUtcTime = new DateTime('now', new DateTimeZone('UTC'));
        // Subtract 4 hours from the current UTC time to get the lower bound
        $fourHoursAgoUtcTime = $currentUtcTime->modify('-4 hours')->format('Y-m-d H:i:s');

        // Initialize the query builder to join vm_beuliping with vm_beuliping_EN
        $query = DB::connection('mysql2')->table('vm_beuliping')
            ->join('vm_beuliping_EN', 'vm_beuliping.id', '=', 'vm_beuliping_EN.m_id') // Join with vm_beuliping_EN based on the id and m_id
            ->select(
                'vm_beuliping.id',
                'vm_beuliping.symbol',
                'vm_beuliping.images as image_url',
                'vm_beuliping_EN.content as recommended_reason', // Select content from vm_beuliping_EN as recommended_reason
                DB::raw('DATE_SUB(vm_beuliping.datetime, INTERVAL 9 HOUR) as regdate') // Adjust datetime for KST
            )
            ->whereNotIn('vm_beuliping.symbol', $excludedSymbols) // Exclude specific symbols
            ->whereNotNull('vm_beuliping.images') // Ensure image_url is not null
            ->whereNotIn('vm_beuliping.symbol', $coin_list) // Exclude symbols already in the coin list
            ->whereRaw("DATE_SUB(vm_beuliping.datetime, INTERVAL 9 HOUR) >= ?", [$fourHoursAgoUtcTime]) // Add condition for regdate within the last 4 hours
            ->where(function ($subQuery) {
                $subQuery->where('vm_beuliping_EN.content', 'not like', 'no%')
                    ->where('vm_beuliping_EN.content', 'not like', '%there%');
            })
            ->orderBy('vm_beuliping.id', 'desc'); // Order by id in descending order to get recent rows first

        // Step 1: Fetch all filtered rows
        $filteredRows = $query->get();

        // Step 2: Use Collection methods to group by 'symbol' and keep only the most recent row
        $uniqueResults = $filteredRows->groupBy('symbol')->map(function ($group) {
            return $group->first(); // Keep only the most recent row for each symbol
        });

        // Step 3: Take the desired number of rows (limit)
        $finalResults = $uniqueResults->take($limit);

        // Log the fetched results
        Log::info("Fetched recommendations: ", ["recommendations" => $finalResults]);

        // Return the results
        return $finalResults->values(); // Return as a collection or array
    }
    public function getRecommendationData($limit, $timezone, $coin_list = [])
    {
        if ($limit === 0) { $limit = 3; }
        if ($limit > 10) { $limit = 10; }

        // Define the excluded symbols directly in the SQL query
        $excludedSymbols = ['1000BONK'];

        // Get the current UTC time
        $currentUtcTime = new DateTime('now', new DateTimeZone('UTC'));
        // Subtract 4 hours from the current UTC time to get the lower bound
        $fourHoursAgoUtcTime = $currentUtcTime->modify('-4 hours')->format('Y-m-d H:i:s');

        // Use the fixed table name `vm_beuliping_EN`
        $tableName = 'vm_beuliping_EN';

        // Initialize the query builder
        $query = DB::connection('mysql2')->table('vm_beuliping')
            ->select(
                'vm_beuliping.id as id',
                'vm_beuliping.symbol as symbol',
                'vm_beuliping.images as image_url',
                'vm_beuliping.datetime as datetime'
            )
            ->whereNotIn('vm_beuliping.symbol', $excludedSymbols) // Exclude specific symbols
            ->whereNotIn('vm_beuliping.symbol', $coin_list) // Exclude symbols already in the coin list
            ->whereNotNull('vm_beuliping.images')
            ->whereRaw("DATE_SUB(vm_beuliping.datetime, INTERVAL 9 HOUR) >= ?", [$fourHoursAgoUtcTime]) // Add condition for datetime within the last 4 hours
            ->orderBy('vm_beuliping.id', 'desc'); // Order by id in descending order

        // Join the `vm_beuliping_EN` table to check the content condition
        $query->join('vm_beuliping_EN', 'vm_beuliping.id', '=', 'vm_beuliping_EN.m_id')
            ->where(function ($subQuery) {
                $subQuery->where('vm_beuliping_EN.content', 'not like', 'no%')
                    ->where('vm_beuliping_EN.content', 'not like', '%there%');
            });

        // Step 1: Fetch all filtered rows
        $filteredRows = $query->get();

        // Step 2: Use Collection methods to group by 'symbol' and keep only the most recent row
        $uniqueResults = $filteredRows->groupBy('symbol')->map(function ($group) {
            return $group->first(); // Keep only the most recent row for each symbol
        });

        // Step 3: Take the desired number of rows (limit)
        $finalResults = $uniqueResults->take($limit);

        // Step 4: Append 'language' key and adjust 'datetime' if necessary
        $finalResults = $finalResults->map(function ($row) use ($timezone) {
            // Add language key based on the timezone
            switch (strtolower($timezone)) {
                case 'kst':
                    $row->language = 'kr';
                    break;
                case 'jst':
                    $row->language = 'jp';
                    break;
                case 'utc':
                default:
                    $row->language = 'en';
                    // If the timezone is UTC (EN), subtract 9 hours from datetime
                    $row->datetime = (new DateTime($row->datetime))->modify('-9 hours')->format('Y-m-d H:i:s');
                    break;
            }
            return $row;
        });

//        $result = $result->map(function($item) use ($timezone) {
//            $item->time_gap = $this->calculateTimeGap($item->datetime, $timezone);
//            return $item;
//        });

        $finalData = $finalResults->map(function($item) use ($timezone) {
           $item->time_gap = $this->calculateTimeGap($item->datetime, $timezone);
           return $item;
        });

        // Convert the results to a JSON string
        $finalString = $finalData->toJson();

        // Log the fetched results as a string
        Log::info("Fetched recommendations: ", ["recommendations" => $finalString]);

        // Return the JSON string
        return $finalString;
    }
    public function getRecommendationDetail($id, $lang) {
        Log::info("get recommendation detail");
        // Dynamically set the table name based on the language
        $tableName = 'vm_beuliping_' . strtoupper($lang);

        // Query the content from the dynamic language table using the provided ID
        $query = DB::connection('mysql2')->table($tableName)
            ->select('content as recommended_reason')  // Select the content as recommended_reason
            ->where('m_id', $id)                       // Match the provided ID
            ->first();                                 // Get the first result

        // If no result is found, return null
        if (!$query) {
            return null;
        }

        // Return the content (recommended_reason)
        return $query->recommended_reason;
    }
    public function getCryptoData(string $symbol, $hours = 24, $timezone = 'UTC')
    {
        if ($hours < 12) { $hours = 24; }
        if ($hours > 720) { $hours = 720; }

        $currentDateTime = new DateTime('now', new DateTimeZone('UTC'));
        $startDateTime = clone $currentDateTime;
        $startDateTime->modify('-' . $hours . ' hours');

        $startTimeFormatted = $startDateTime->format('Y-m-d H:i:s');
        $endTimeFormatted = $currentDateTime->format('Y-m-d H:i:s');
        Log::info("start and end: ", ["start" => $startTimeFormatted, "end" => $endTimeFormatted]);

        $symbol = $this->normalizeSymbol($symbol);
        Log::info("Normalized Symbol: ", ["symbol" => $symbol]);

        if ($hours <= 48) {
            // Logic for hourly interval
            $data = DB::connection('mysql')->table('trsi.retri_chart_data')
                ->where('simbol', $symbol)
                ->whereBetween('regdate', [$startTimeFormatted, $endTimeFormatted])
                ->orderBy('regdate')
                ->select('simbol as symbol', 'score', 'price', 'regdate')
                ->get();
            $formattedData = $this->formatDataWithTimezone($data, $timezone);
        } else {
            // Logic for daily interval (more than 48 hours)
            $data = DB::connection('mysql')->table('trsi.retri_chart_data')
                ->where('simbol', $symbol)
                ->whereBetween('regdate', [$startTimeFormatted, $endTimeFormatted])
                ->orderBy('regdate')
                ->select('simbol as symbol', 'score', 'price', 'regdate')
                ->get();
            $formattedData = $this->averageAndFormatData($data, $timezone);
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
        // Map short timezone names to PHP timezone identifiers
        $timezoneMap = [
            'KST' => 'Asia/Seoul',  // KST is UTC+9
            'JST' => 'Asia/Tokyo',  // JST is UTC+9
            'UTC' => 'UTC',         // UTC
        ];
        // Ensure the timezone is valid by mapping it to the correct PHP identifier
        $phpTimezone = isset($timezoneMap[$timezone]) ? $timezoneMap[$timezone] : 'UTC';

        $currentDateTime = new DateTime('now', new DateTimeZone($phpTimezone));
        $recommendDateTime = new DateTime($recommendTime, new DateTimeZone($phpTimezone));

        $interval = $currentDateTime->diff($recommendDateTime);

        return [
            'hours' => $interval->h,
            'minutes' => $interval->i,
        ];
    }
    public function getCryptoLogo($symbol) {
        $symbol = $this->normalizeSymbol($symbol, false);
        Log::info("Normalized Symbol: ", ["symbol" => $symbol]);
        // Logic for hourly interval
        $result = DB::connection('mysql3')
            ->table('bu.Symbols')
            ->where('symbol', $symbol)
            ->select('symbol', 'imageUrl')
            ->first();
        if ($result) {
            Log::info("symbol & image: ", ["logo" => $result]);
            return $result->imageUrl;
        }

        //optionally return null or some default value if the symbol is not found
        return null;

    }
}
