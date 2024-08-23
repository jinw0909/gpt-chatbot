<?php

namespace App\Services;

use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

    public function getTimeGap($datetime, $timezone)
    {
        try {
            // Create a DateTime object from the provided $datetime
            $dateTimeObj = new DateTime($datetime, new DateTimeZone($timezone));

            // Get the current time in the specified timezone
            $currentDateTime = new DateTime('now', new DateTimeZone($timezone));

            // Calculate the difference between the current time and the provided datetime
            $interval = $currentDateTime->diff($dateTimeObj);

            // Build the time gap array with all units included
            $timeGap = [
                'year' => $interval->y,
                'month' => $interval->m,
                'day' => $interval->d,
                'hours' => $interval->h,
                'minutes' => $interval->i,
                'seconds' => $interval->s
            ];

            Log::info("time gap: ", $timeGap);

            return json_encode($timeGap);

        } catch (Exception $e) {
            // Handle any errors, for example, if the $datetime format is incorrect
            return 'Error: ' . $e->getMessage();
        }
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

    public function getLatestPrice($symbol, $timezone)
    {
        $symbol = $this->normalizeSymbol($symbol);
        $data = DB::connection('mysql')->table('trsi.retri_chart_data')
            ->where('simbol', $symbol)
            ->orderBy('idx', 'desc')
            ->select('simbol as symbol', 'score', 'price', 'regdate as datetime')
            ->first();

        $convertedDatetime = $this->convertTimeToTimezone($data->datetime, $timezone);
        $timeGap = $this->calculateTimeGap($convertedDatetime, $timezone);

        return json_encode([
            'symbol' => $data->symbol,
            'score' => $data->score,
            'price' => $data->price,
            'datetime' => $convertedDatetime,
            'timeGap' => $timeGap
        ]);
    }

    public function convertToLocalTime($utc_time, $timezone)
    {
        return $this->convertTimeToTimezone($utc_time, $timezone);
    }

    public function checkIfRecommended($symbol, $timezone)
    {
        $symbol = $this->normalizeSymbol($symbol, false);

        // Remove the appended 'USDT' from the symbol if it exists
        if (str_ends_with($symbol, 'USDT')) {
            $symbol = substr($symbol, 0, -4); // Remove 'USDT' (4 characters)
        }

        $currentKST = new DateTime('now', new DateTimeZone('Asia/Seoul'));
        $twelveHoursAgoKST = clone $currentKST;
        $twelveHoursAgoKST->modify('-12 hours');
        Log::info("current KST: ", ["currentKST" => $currentKST]);
        Log::info("12 hours ago KST: ", ["12HoursAgoKST" => $twelveHoursAgoKST]);

        // Build the query
        $query = DB::connection('mysql2')->table('beuliping')
            ->join('vm_beuliping_EN', 'beuliping.id', '=', 'vm_beuliping_EN.m_id')
            ->where('beuliping.symbol', $symbol)
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

        // Prepare the response
        $response = [
            'symbol' => $symbol . 'USDT',
            'isRecommended' => $isRecommended,
            'recommendTime' => $isRecommended ? $this->convertTimeToTimezone($result->datetime, $timezone) : null,
            'recommendImage' => $isRecommended ? $result->images : null,
            'recommendReason' => $isRecommended ? $result->content : null,
            'recommendTimeGap' => $recommendTimeGap
        ];

        // Log the result
        Log::info("check if recommended: ", ["result" => $response]);

        // Return the response as JSON
        return json_encode($response);
    }

    public function getRecommendations($limit, $timezone)
    {
        $totalResults = collect();
        $initialQueryLimit = $limit * 2;
        $offset = 0;
        $selectedSymbols = [];

        $timezoneObj = $this->getTimezoneObject($timezone);

        while ($totalResults->count() < $limit) {
            $initialResults = DB::connection('mysql2')->table('beuliping')
                ->join('vm_beuliping_EN', 'beuliping.id', '=', 'vm_beuliping_EN.m_id')
                ->orderBy('beuliping.id', 'desc')
                ->offset($offset)
                ->limit($initialQueryLimit)
                ->select('beuliping.id', 'beuliping.symbol', 'beuliping.datetime', 'beuliping.images', 'vm_beuliping_EN.content', DB::raw('DATE_SUB(beuliping.datetime, INTERVAL 9 HOUR) as datetime'))
                ->get();

            $filteredResults = $initialResults->filter(function($item) use ($selectedSymbols) {
                return $item->symbol !== '1000BONK' && !str_starts_with($item->content, 'No') && !is_null($item->images) && !in_array($item->symbol, $selectedSymbols);
            });

            $formattedResults = $filteredResults->map(function($item) use ($timezoneObj, &$selectedSymbols) {
                $dateTime = new DateTime($item->datetime, new DateTimeZone('UTC'));
                $dateTime->setTimezone($timezoneObj);
                $item->datetime = $dateTime->format('Y-m-d\TH:i:sP');
                $selectedSymbols[] = $item->symbol;
                return $item;
            });

            $totalResults = $totalResults->merge($formattedResults);

            if ($initialResults->count() < $initialQueryLimit) {
                break;
            }

            $offset += $initialQueryLimit;
        }

        $result = json_encode($totalResults->take($limit)->values());
        Log::info("Recommendations", ["recommendations" => $result]);

        return $result;
    }

    public function getCryptoDataHourInterval($symbol, $hours = 24, $timezone = 'UTC')
    {
        $symbol = $this->normalizeSymbol($symbol);

        $currentDateTime = new DateTime('now', new DateTimeZone('UTC'));

        $startDateTime = clone $currentDateTime;
        $startDateTime->modify('-' . $hours . ' hours');

        $startTimeFormatted = $startDateTime->format('Y-m-d H:i:s');
        $endTimeFormatted = $currentDateTime->format('Y-m-d H:i:s');

        $data = DB::connection('mysql')->table('trsi.retri_chart_data')
            ->where('simbol', $symbol)
            ->whereBetween('regdate', [$startTimeFormatted, $endTimeFormatted])
            ->orderBy('regdate')
            ->select('simbol as symbol', 'score', 'price', 'regdate')
            ->get();

        return $this->formatDataWithTimezone($data, $symbol, $timezone);
    }

    public function getCryptoDataDayInterval($symbol, $days = 30, $timezone ='UTC')
    {
        $symbol = $this->normalizeSymbol($symbol);

        $currentDateTime = new DateTime('now', new DateTimeZone('UTC'));

        $startDateTime = clone $currentDateTime;
        $startDateTime->modify('-' . $days . 'days');

        $startTimeFormatted = $startDateTime->format('Y-m-d H:i:s');
        $endTimeFormatted = $currentDateTime->format('Y-m-d H:i:s');

        $data = DB::connection('mysql')->table('trsi.retri_chart_data')
            ->where('simbol', $symbol)
            ->whereBetween('regdate', [$startTimeFormatted, $endTimeFormatted])
            ->orderBy('regdate')
            ->select('simbol as symbol', 'score', 'price', 'regdate')
            ->get();

        return $this->averageAndFormatData($data, $symbol, $timezone);
    }

    public function getCryptoData($symbol, $hours = 24, $timezone = 'UTC')
    {
        $symbol = $this->normalizeSymbol($symbol);

        $currentDateTime = new DateTime('now', new DateTimeZone('UTC'));
        $startDateTime = clone $currentDateTime;
        $startDateTime->modify('-' . $hours . ' hours');

        $startTimeFormatted = $startDateTime->format('Y-m-d H:i:s');
        $endTimeFormatted = $currentDateTime->format('Y-m-d H:i:s');

        if ($hours <= 48) {
            // Logic for hourly interval
            $data = DB::connection('mysql')->table('trsi.retri_chart_data')
                ->where('simbol', $symbol)
                ->whereBetween('regdate', [$startTimeFormatted, $endTimeFormatted])
                ->orderBy('regdate')
                ->select('simbol as symbol', 'score', 'price', 'regdate')
                ->get();

            return $this->formatDataWithTimezone($data, $symbol, $timezone);
        } else {
            // Logic for daily interval (more than 48 hours)
            $data = DB::connection('mysql')->table('trsi.retri_chart_data')
                ->where('simbol', $symbol)
                ->whereBetween('regdate', [$startTimeFormatted, $endTimeFormatted])
                ->orderBy('regdate')
                ->select('simbol as symbol', 'score', 'price', 'regdate')
                ->get();

            return $this->averageAndFormatData($data, $symbol, $timezone);
        }
    }





    public function subtractHoursFromTime($time, $hours)
    {
        return $this->subtractHours($time, $hours);
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

    private function formatDataWithTimezone($data, $symbol, $timezone)
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
        Log::info("Retrieved symbol data", ["symboldata" => $resultData, "symbol" => $symbol]);
        return json_encode(['symbol' => $symbol, 'data' => $resultData]);
    }

    private function averageAndFormatData($data, $symbol, $timezone)
    {
        $timezoneObj = $this->getTimezoneObject($timezone);
        $averagedData = [];
        $chunk = [];
        foreach ($data as $key => $item) {
            $chunk[] = $item;
            if (count($chunk) == 24 || $key == $data->count() - 1) {
                $avgPrice = collect($chunk)->avg('price');
                $avgScore = collect($chunk)->avg('score');
                $lastDate = new DateTime(end($chunk)->regdate, new DateTimeZone('UTC'));
                $lastDate->setTimezone($timezoneObj);
                $averagedData[] = [
                    'symbol' => $symbol,
                    'average_price' => $avgPrice,
                    'average_score' => $avgScore,
                    'datetime' => $lastDate->format('Y-m-d\TH:i:sP'),
                ];
                $chunk = [];
            }
        }
        Log::info("Averaged symbol data", ["symboldata" => $averagedData, "symbol" => $symbol]);
        return json_encode(['symbol' => $symbol, 'data' => $averagedData]);
    }

    private function calculateTimeGap($recommendTime, $timezone)
    {
        $currentDateTime = new DateTime('now', new DateTimeZone($timezone));
        $recommendDateTime = new DateTime($recommendTime, new DateTimeZone($timezone));

        $interval = $currentDateTime->diff($recommendDateTime);

        return [
            'years' => $interval->y,
            'months' => $interval->m,
            'days' => $interval->d,
            'hours' => $interval->h,
            'minutes' => $interval->i,
            'seconds' => $interval->s
        ];
    }
}
