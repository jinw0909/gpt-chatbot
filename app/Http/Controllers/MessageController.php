<?php

namespace App\Http\Controllers;

use App\Models\User;
use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    private static $totalCost = [];
    private static $maxToken = [];

    public function processMessage(Request $request)
    {
        //Validate the input
        $request->validate([
            'message' => 'required|string',
        ]);

        $message = $request->input('message');
        $userId = $request->input('userId');
        $conversation = $request->input('conversation');
        $wasSummarized = false;
        $summary = null;
        $token = $request->input('maxUsage', 0);

        //Ensure userId is a string to handle any type of userId;
        $userId = (string) $userId;
        Log::info("userId: ", ["userId" => $userId]);

        //Initialize the user cost if not already set
        if (!isset(self::$totalCost[$userId])) {
            self::$totalCost[$userId] = 0;
        }
        if (!isset(self::$maxToken[$userId])) {
            self::$maxToken[$userId] = $token;
        }

        self::$totalCost[$userId] = 0;
        self::$maxToken[$userId] = $token;
        Log::info("max token count: ", ["maxTokenCount" => self::$maxToken[$userId]]);

        // Check if the conversation length exceeds a certain limit (e.g., 3000 tokens)
        if (self::$maxToken[$userId] > 7000) {

            //Summarize the conversation
            $conversationString = json_encode($conversation);

            $summaryPrompt = [
                [
                    'role' => 'system',
                    'content' => 'Please summarize the following conversation. If there is a summary included in the conversation, the content of the summary too should be included for summarization:'
                ],
                [
                    'role' => 'system',
                    'content' => $conversationString
                ]
            ];

            $summaryResponse = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => $summaryPrompt,
                'max_tokens' => 500 //Adjust as needed
            ]);

            $summaryResponseMessage = $summaryResponse['choices'][0]['message']['content'];
            $summaryInput = $summaryResponse['usage']['prompt_tokens'];
            $summaryOutput = $summaryResponse['usage']['completion_tokens'];

            $summaryCost = $this->calculateTokenCost($summaryInput, $summaryOutput);

            self::$totalCost[$userId] += $summaryCost;

            //tokens spent on summarizing will not be considered in the max token competition for it will almost always be the largest.
            //reset the max token usage, it wont exceed the limit anyway
            self::$maxToken[$userId] = 0;

            Log::info("summary response: ", ['message' => $summaryResponseMessage]);
            Log::info("summary usage", ["usage" => ($summaryInput + $summaryOutput)]);
            Log::info("summary cost", ["cost" => $summaryCost]);
            Log::info("total cost", ["cost" => self::$totalCost[$userId]]);

            $summary =  [
                'role' => 'system',
                'content' => 'Summary of the previous conversation: ' . $summaryResponseMessage
            ];
            $conversation = [$summary];
            $wasSummarized = true;
        }

        $systems = [
            [
                'role' => 'system',
                'content' => "You can decide the user's locale and user's timezone by the language of the user message, unless the user specifies his or her locale. KST for Korean, and JST for Japanese."
            ],
            [
                'role' => 'system',
                'content' => 'When you have to convert UTC time to the local time call the function [convert_to_local_time]. If user locale is Japan, pass "JST" as timezone. If the user locale is Korea, pass "KST" as timezone. Else pass "UTC" as timezone'.
                    'Always call the function [get_current_time] when you have to check what the current time is. Do not use the pre-trained data to get the current time.'
            ],
            [
            'role' => 'system',
            'content' =>
                'When the user asks to analyze single or multiple cryptocurrencies, or asks to analyze the its price and score within time range, or just gives cryptocurrency symbols, or asks to pick from the recommendation list, then your should follow the steps below and response in this json format: {"symbols" : [{"symbol": CAPITALIZED SYMBOL STRING, "local_time": CURRENT TIME OF THE LOCAL TIMEZONE, "latest_price": THE LATEST PRICE OF THE SYMBOL, "price_movement": FLOAT ARRAY OF THE SYMBOL PRICE WITHIN TIME RANGE ORDERED BY DATETIME, "score_movement": FLOAT ARRAY OF THE SYMBOL SCORE_ WITHIN TIME RANGE ORDERED BY DATETIME, "time_labels": STRING ARRAY OF EVERY DATETIME VALUE ORDERED BY TIME, "analysis": YOUR ANALYSIS DERIVED FROM THE_PRICE AND SCORE MOVEMENT WITHIN TIME RANGE OR ANY OTHER ANALYSES INCLUDING COMPARISON, "recommendation": JSON FORMAT IF [CHECK_IF_RECOMMENDED] RETURNS VALID RESULT, IF NO VALID RESULT THEN BOOLEAN FALSE}, ...]}. '.
                '1. Get the latest price of the symbol by calling [get_latest_price]'.
                '2. Get the current UTC time by calling [get_current_time] and get the current local time by calling [convert_to_local_time]. Pass current UTC time and the local timezone of the user as parameters. '.
                '3. Call [get_crypto_price_hour_interval] or [get_crypto_price_day_interval]. If the user did not specify a time range, then the set the "hours" parameter as 24.'
                ."4. Call [check_if_recommended] and check if the symbol is in the current recommendation list."
                .'5. If the function [check_if_recommended] returned a valid result, append the following JSON format to the key "recommendation": {"datetime": RETURNED DATETIME FROM FUNCTION CALL CHECK_IF_RECOMMENDED, "content": THE CONTENT TRANSLATED TO LOCAL LANGUAGE FROM THE FUNCTION CALL [CHECK_IF_RECOMMENDED], "image": IMAGES URL RETURNED FROM THE FUNCTION CALL [CHECK_IF_RECOMMENDED]}.'
                .'6. If the function [check_if_recommended] did not return a valid result, append the boolean false to the key "recommendation". '
                ."7. The 'analysis' may include any analysis derived from analyzing the 'price_movement', 'score_movement', 'time_labels' and the 'recommendation.datetime' and 'recommendation.content' data. "
                .'8. Lastly check if all the response is translated properly into the local language including "analysis" and recommendation "content". Also check if the image url exactly matches the returned url.'
            ],
            [
                'role' => 'system',
                'content' => 'When you invoke the function call [get_recommendations], return the response in the following order.'.
                    '1. When the user did not specify a specific limit of recommendation, then use default 3 as the recommendation limit.'.
                    '2. Return the response in a JSON format. {"recommendations" : [{"symbol": "STRING", "datetime": "STRING", "image": "URL_STRING", "content": "STRING"}, ...]}'.
                    "3. Your whole response has be translated into the local language of the user."
            ],
            [
                'role' => 'system',
                'content' => 'When the user asks for additional recommendation, then first call function [get_recommends] with the limit added to the previous recommendation.'.
                    'For example when the user asks to recommend two more coins, and when the limit parameter passed to the previous [get_recommends] was 3, then this time call the [get_recommends] with limit 6'.
                    'When the user doesnt specify the additional limit (such as recommend me some more cryptocurrencies, recommend few more) then add 3 to the previous limit'.
                    'After the additional [get_recommends] have returned its result, then compare two lists and only return the recommendations that was not included in the previous recommendation list in a json array format'.
                    'The response json array format should be as follows. {"recommendations" : [{"symbol": "STRING", "datetime": "STRING", "image": "URL_STRING", "content": "STRING"}, ...]}'
            ],
            [
                'role' => 'system',
                'content' => 'If there is no instruction on the response format, then return the response in this format: {"common" : RESPONSE_CONTENT_NOT_IN_JSON}. '.
                    'However, if there is a system instruction on the response format, the specified format always takes priority'
            ],
            [
                'role' => 'system',
                'content' => "Map the following coin names to their symbols: bitcoin -> btcusdt, ethereum -> ethusdt, solana -> solusdt, ripple or xrp -> xrpusdt. "
            ],

//            [
//                'role' => 'system',
//                'content' => 'When the user asks to pick a certain number of cryptocurrency from the recommendation list, or asks for further explanation on the recommendation list, or asks for language conversion on the recommendation list, you should follow these steps to elaborate and return the response.'.
//                    '1. Invoke the function call [elaborate_recommends]'.
//                    '2. Pick the symbol from the recommendation list to elaborate on. If the user has not specified a specific symbol or the limit, then every symbol on the recommendation list is the target for elaboration' .
//                    '3. Call the [get_crypto_price] and [get_current_time] for each symbol and retrieve the price and score data with a time range of 5 hours ago from the current time'.
//                    '4. The response should follow the following json format. {"elaborations" : [{"symbol": "STRING", "datetime": "STRING", "image": "URL_STRING", "price_trend" : "STRING", "score_trend" : "STRING", "content": "STRING"}, ...]}'.
//                    "5. For the [symbol], [datetime], and [image] values, use the data from the recommendation list. Be sure to check the [datetime] value's timezone is formatted correctly.".
//                    "6. For the [price_trend] and [score_trend] values, analyze symbol cryptocurrency's price and score movement in a plain text (not json format)".
//                    '7. For the [content], elaborate the signal analysis with price and score trends applied. Remember that the price decline may not be as negative indicator as it seems, since futures short trading is also possible'
//            ],

            [
                'role' => 'system',
                'content' => 'The score values returned from the function calls [get_crypto_data_hour_interval], [get_crypto_data_day_interval], [get_crypto_data_in_time_range] is called a "Goya score"(ゴヤースコア in Japanese, 고야 스코어 in Korean). It is an indicator to predict the future price of the symbol cryptocurrency.'.
                    'When the Goya score is on a downward trend, the price of the cryptocurrency is likely go down, and otherwise when the score is showing a upward trend, the actual price of the cryptocurrency is likely to go up.'.
                    'This score is derived by analyzing objective blockchain transaction activity data focusing mostly on the movements that has positive or negative impacts on the price of the cryptocurrency. '
            ],

//            [
//                'role' => 'system',
        //                'content' => 'When the user asks any further questions on the given price and score analysis, or asks for further explanation of the given analysis, the response should be made in the following JSON format: '.
        //                    '{"symbols" : [{"symbol": STRING, "time": "current_time": STRING, "recent_price" : FLOAT, "price_movement": FLOAT_ARRAY, "score_movement": FLOAT_ARRAY, "time_labels": STRING_ARRAY, "analysis": TEXT}, ...]}'
//            ]
        ];

        $messages = [
            [
                'role' => 'user',
                'content' => $message
            ]
        ];

        if (!is_null($conversation) && is_array($conversation)) {
            $messages = array_merge($systems, $conversation, $messages);
        }

        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_crypto_data_in_time_range',
                    'description' => 'Get the price and score data of a certain cryptocurrency between two specified UTC times on an hourly interval, given the "symbol", "from_time", and "to_time". The returned datetime follows the local timezone and the price unit is USD.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'symbol' => [
                                'type' => 'string',
                                'description' => "The symbol of the cryptocurrency (e.g., 'btcusdt')."
                            ],
                            'from_time' => [
                                'type' => 'string',
                                'description' => "The start datetime to filter the price data (e.g., '2024-07-29T16:05:06Z'). Is UTC format and is prior to the 'to_time' parameter."
                            ],
                            'to_time' => [
                                'type' => 'string',
                                'description' => "The end datetime to filter the price data (e.g., '2024-07-30T16:05:06Z'). Is UTC format and is after the 'from_time' parameter."
                            ],
                            'timezone' => [
                                'type' => 'string',
                                'description' => "The local timezone of the user. Used to format the result datetime to the local timezone of the user.",
                                'enum' => ['UTC', 'JST', 'KST']
                            ]
                        ],
                        'required' => ['symbol', 'from_time', 'to_time', 'timezone']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_crypto_data_hour_interval',
                    'description' => 'Get the price and score data of a cryptocurrency symbol from the certain hours ago until the current UTC time with a hourly interval, given "symbol", "current_time", "hours", and "timezone". The returned datetime follows the local timezone and the price unit is in USD',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'symbol' => [
                                'type' => 'string',
                                'description' => "The symbol of the cryptocurrency (e.g., 'btcusdt')."
                            ],
                            'current_time' => [
                                'type' => 'string',
                                'description' => "The end datetime to filter the price data. Formatted in UTC."
                            ],
                            'hours' => [
                                'type' => 'integer',
                                'description' => "The number of hours ago from the current time from when the price and score data will be retrieved."
                            ],
                            'timezone' => [
                                'type' => 'string',
                                'description' => "The local timezone of the user. Used when formatting the result datetime to the local timezone of the user.",
                                'enum' => ['UTC', 'JST', 'KST']
                            ]
                        ],
                        'required' => ['symbol', 'current_time', 'hours', 'timezone']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_crypto_data_day_interval',
                    'description' => 'Get the price and score data of a cryptocurrency symbol from the given days ago until the current UTC time with a 24 hours interval. Passed parameters are "symbol", "current_time", "days", and "timezone". The returned datetime follows the local timezone and the price unit is USD',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'symbol' => [
                                'type' => 'string',
                                'description' => "The symbol of the cryptocurrency (e.g., 'btcusdt')."
                            ],
                            'current_time' => [
                                'type' => 'string',
                                'description' => "The end datetime to filter the price data. Formatted in UTC."
                            ],
                            'days' => [
                                'type' => 'integer',
                                'description' => "The number of days ago from the current time from when the price and score data will be retrieved."
                            ],
                            'timezone' => [
                                'type' => 'string',
                                'description' => "The local timezone of the user. Used when formatting the result datetime to the local timezone of the user.",
                                'enum' => ['UTC', 'JST', 'KST']
                            ]
                        ],
                        'required' => ['symbol', 'current_time', 'days', 'timezone']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_current_time',
                    'description' => 'Returns the current UTC time',
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'convert_to_local_time',
                    'description' => 'Returns the current local time given the UTC time and the local timezone',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'utc_time' => [
                                'type' => 'string',
                                'description' => 'The target UTC time to be converted into the local timezone'
                            ],
                            'timezone' => [
                                'type' => 'string',
                                'description' => "The local timezone of the user. Used to format the UTC time to the local time.",
                                'enum' => ['UTC', 'JST', 'KST']
                            ]
                        ],
                        'required' => ['utc_time','timezone']
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_latest_price',
                    'description' => 'Get the latest price data of a certain cryptocurrency given the symbol. The returned datetime is formatted in UTC. ',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'symbol' => [
                                'type' => 'string',
                                'description' => "The symbol of the cryptocurrency (e.g., 'btc')."
                            ]
                        ],
                        'required' => ['symbol']
                    ]
                ]
            ],
//            [
//                'type' => 'function',
//                'function' => [
//                    'name' => 'subtract_hours_from_time',
//                    'description' => 'Subtract a number of hours from a given UTC time and returns the UTC time',
//                    'parameters' => [
//                        'type' => 'object',
//                        'properties' => [
//                            'time' => [
//                                'type' => 'string',
//                                'description' => "The time to subtract hours from in ISO 8601 format (e.g., '2024-07-30T16:05:06Z')."
//                            ],
//                            'hours' => [
//                                'type' => 'number',
//                                'description' => "The number of hours to subtract."
//                            ]
//                        ],
//                        'required' => ['time', 'hours']
//                    ]
//                ]
//            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_recommendations',
                    'description' => "Get the data of recommended cryptocurrencies for purchasing."
                                    . "Returns a JSON-encoded array of recommended cryptocurrencies. The datetime value is returned in a UTC timezone.",
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'limit' => [
                                'type' => 'integer',
                                'description' => "The limit of the recommendation."
                            ],
                            'timezone' => [
                                'type' => 'string',
                                'description' => "The local timezone of the user",
                                'enum' => ['UTC', 'JST', 'KST']
                            ]
                        ],
                        'required' => ['limit', 'timezone']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'check_if_recommended',
                    'description' => "Given the symbol of the cryptocurrency, check if the symbol is in the current recommendation list",
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'symbol' => [
                                'type' => 'string',
                                'description' => "The symbol of the cryptocurrency. (e.g., 'btc)"
                            ],
                            'timezone' => [
                                'type' => 'string',
                                'description' => 'The local timezone of the user',
                                'enum' => ['UTC', 'JST', 'KST']
                            ]
                        ],
                        'required' => ['symbol', 'timezone']
                    ]
                ]
            ],
        ];

        $openAIResponse = $this->sendMessageToOpenAI($messages, $tools, $userId);

        //$responseContent = $openAIResponse->getData(true);
        $maxUsage = self::$maxToken[$userId];
        $totalCost = self::$totalCost[$userId];
        $this->reduceCharge($userId, $totalCost);

        return response()->json([
            'responseText' => $openAIResponse['responseText'],
            'wasSummarized' => $wasSummarized,
            'summary' => $summary,
            'maxUsage' => $maxUsage,
            'type' => $openAIResponse['type']
        ]);
    }


    private function sendMessageToOpenAI($messages, $tools, $userId, $functionList = [], $format = 'text')
    {
        $type = "";
        //Iterate over functionList
        Log::info("function list: ", ["functionList" => $functionList]);
//        Log::info("messages: ", ["messages" => $messages]);
        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
                'tools' => $tools,
                'tool_choice' => 'auto',
                'response_format' => ["type" => "json_object"],
                'parallel_tool_calls' => true,
            ]);
            Log:info("response: ", ["response" => $response]);

            $responseMessage = $response['choices'][0]['message'];
            $promptToken = $response['usage']['prompt_tokens'];
            $completionToken = $response['usage']['completion_tokens'];
            $responseCost = $this->calculateTokenCost($promptToken, $completionToken);
            $responseToken = $response['usage']['total_tokens'];

            self::$totalCost[$userId] += $responseCost;
            // Update the totalToken to be the maximum of its current value and the responseToken
            if (!isset(self::$maxToken[$userId])) {
                self::$maxToken[$userId] = 0;
            }
            self::$maxToken[$userId] = max(self::$maxToken[$userId], $responseToken);

            Log::info("total cost: ", ["totalCost" => self::$totalCost[$userId]]);
            Log::info("maximum token: ", ["maximumToken" => self::$maxToken[$userId]]);

            //append the message
            $messages[] = $responseMessage;

            //recurse logic
            $toolCalls = $responseMessage['tool_calls'] ?? [];
            if (empty($toolCalls)) {
                //return
                Log::info('response content: ', ["responseContent" => $responseMessage['content']]);
                return [
                    'responseText' => $responseMessage['content'],
                    'type' => $type
                ];
            } else {
                //recurse
                Log::info("toolCalls: ", ["toolCalls" => $toolCalls]);
                $availableFunctions = [
                    'get_latest_price' => [$this, 'getLatestPrice'],
                    'get_crypto_data_in_time_range' => [$this, 'getCryptoDataInTimeRange'],
                    'get_crypto_data_hour_interval' => [$this, 'getCryptoDataHourInterval'],
                    'get_crypto_data_day_interval' => [$this, 'getCryptoDataDayInterval'],
                    'get_current_time' => [$this, 'getCurrentTime'],
                    'convert_to_local_time' => [$this, 'convertToLocalTime'],
//                    'subtract_hours_from_time' => [$this, 'subtractHoursFromTime'],
                    'get_recommendations' => [$this, 'getRecommendations'],
                    'check_if_recommended' => [$this, 'checkIfRecommended']
                ];

                foreach ($toolCalls as $toolCall) {
                    $functionName = $toolCall['function']['name'] ?? null; // Use null coalescing to handle missing keys
                    if ($functionName) {
                        Log::info("functionName:  ", ["functionName" => $functionName]);
                        $functionList[] = $functionName; // Append to functionList
                        $functionToCall = $availableFunctions[$functionName];
                        $functionArgs = json_decode($toolCall['function']['arguments'] ?? '{}', true);
                        Log::info("functionArgs: ", ["functionArgs" => $functionArgs]);
                        $functionResponse = call_user_func($functionToCall, ...array_values($functionArgs));

                        $callResponse = [
                            'tool_call_id' => $toolCall['id'],
                            'role' => 'tool',
                            'name' => $functionName,
                            'content' => $functionResponse
                        ];

                        $messages[] = $callResponse;
                    } else {
                        Log::warning("Function name is null in toolCall: ", ["toolCall" => $toolCall]);
                    }
                }

                return $this->sendMessageToOpenAI($messages, $tools, $userId, $functionList, $format);
            }
        } catch (\Exception $e) {
            Log::error('Error communicating with OpenAI:', ['error' => $e->getMessage()]);
            //If error set the totalCost of the user to 0
            self::$totalCost[$userId] = 0;
            return ['error' => 'Error: ' . $e->getMessage()];
        }
    }

    private function reduceCharge($id, $amount)
    {
        $user = User::find($id);
        if ($user) {
            $costToReduce = $amount;
            if ($user->charge >= $costToReduce) {
                $user->charge -= $costToReduce;
                $user->save();

                Log::info('Charge reduced successfully for user ID: ' . $id, ['left' => $user->charge, 'reduced' => $costToReduce]);
            } else {
                Log::error('Not enough charge for user ID: ' . $id, ['error' => 400]);
            }
        } else {
            Log::error('User not found with ID: ' . $id, ['error' => 404]);
        }
    }

    private function getCurrentTime() {
        // Create a DateTime object with UTC timezone
        $dateTime = new DateTime('now', new DateTimeZone('UTC'));

        // Format the time according to the selected timezone
        $formattedTime = $dateTime->format('Y-m-d\TH:i:s\Z');

        // Log the current time
        Log::info("current time: ", ["time" => $formattedTime]);

        return $formattedTime;
    }

    private function getCryptoDataInTimeRange($symbol, $startTime, $endTime, $timezone) {
        // Capitalize the symbol
        $symbol = strtoupper($symbol);

        // Ensure the symbol ends with 'USDT'
        if (!str_ends_with($symbol, 'USDT')) {
            $symbol .= 'USDT';
        }

        // Convert the ISO 8601 start and end times to MySQL datetime format
        $startDateTime = DateTime::createFromFormat(DateTime::ISO8601, $startTime, new DateTimeZone('UTC'));
        $endDateTime = DateTime::createFromFormat(DateTime::ISO8601, $endTime, new DateTimeZone('UTC'));

        if ($startDateTime === false || $endDateTime === false) {
            throw new Exception("Invalid datetime format. Please use ISO 8601 format, e.g., '2024-08-11T02:08:24Z'.");
        }

        $startTimeFormatted = $startDateTime->format('Y-m-d H:i:s');
        $endTimeFormatted = $endDateTime->format('Y-m-d H:i:s');

        // Retrieve data from the database
        $data = DB::connection('mysql')->table('trsi.retri_chart_data')
            ->where('simbol', $symbol)
            ->whereBetween('regdate', [$startTimeFormatted, $endTimeFormatted])
            ->orderBy('idx')
            ->select(
                'simbol as symbol',
                'score',
                'price',
                'regdate'
            )
            ->get();

        // Determine the timezone and create the appropriate DateTimeZone object
        $timezoneObj = new DateTimeZone('UTC');
        if ($timezone === 'KST') {
            $timezoneObj = new DateTimeZone('Asia/Seoul');
        } elseif ($timezone === 'JST') {
            $timezoneObj = new DateTimeZone('Asia/Tokyo');
        }

        // Convert the regdate to ISO 8601 format and adjust by timezone
        $data = $data->map(function($item) use ($timezoneObj) {
            $dateTime = new DateTime($item->regdate, new DateTimeZone('UTC'));

            // Set the timezone and format accordingly
            $dateTime->setTimezone($timezoneObj);

            $item->datetime = $dateTime->format('Y-m-d\TH:i:sP'); // P adds the timezone offset like +09:00
            unset($item->regdate);
            return $item;
        });

        Log::info("symbol data", ["symboldata" => $data, "symbol" => $symbol]);

        return json_encode([
            'symbol' => $symbol,
            'data' => $data
        ]);
    }

    private function getLatestPrice($symbol) {

        $symbol = strtoupper($symbol);
        if (!str_ends_with($symbol, 'USDT')) {
            $symbol .= 'USDT';
        }

        $data = DB::connection('mysql')->table('trsi.retri_chart_data')
            ->where('simbol', $symbol)
            ->orderBy('idx', 'desc')
            ->select('simbol as symbol', 'score', 'price', 'regdate as datetime') // Select only the needed fields with aliases
            ->first();

        return json_encode([
            'symbol' => $data->symbol,
            'score' => $data->score,
            'price' => $data->price,
            'datetime' => $data->datetime
        ]);
    }

    private function subtractHoursFromTime($time, $hours) {
        $dateTime = new DateTime($time, new DateTimeZone('UTC'));
        $dateTime->sub(new DateInterval('PT' . $hours . 'H'));
        return $dateTime->format('Y-m-d\TH:i:s\Z');
    }

    private function convertToLocalTime($utc_time, $timezone) {
        // Determine the appropriate timezone identifier based on the input
        switch ($timezone) {
            case 'KST':
                $timezoneObj = new DateTimeZone('Asia/Seoul');
                break;
            case 'JST':
                $timezoneObj = new DateTimeZone('Asia/Tokyo');
                break;
            case 'UTC':
            default:
                $timezoneObj = new DateTimeZone('UTC');
                break;
        }

        // Create a DateTime object with the current time in UTC
        $dateTime = new DateTime($utc_time, new DateTimeZone('UTC'));

        // Set the timezone to the selected timezone
        $dateTime->setTimezone($timezoneObj);

        // Return the time in ISO 8601 format
        return $dateTime->format('Y-m-d\TH:i:sP'); // 'P' includes the timezone offset
    }

    private function checkIfRecommended($symbol, $timezone) {
        // Convert the symbol to uppercase and append 'USDT' if it doesn't already end with 'USDT'
        $symbol = strtoupper($symbol);
        if (str_ends_with($symbol, 'USDT')) {
            $symbol = substr($symbol, 0, -4);
        }

        // Get the current time in KST
        $currentKST = new DateTime('now', new DateTimeZone('Asia/Seoul'));

        // Get the time 12 hours ago from the current KST time
        $twelveHoursAgoKST = clone $currentKST;
        $twelveHoursAgoKST->modify('-12 hours');

        // Query to find the row where the symbol matches and datetime is within the last 12 hours
//        $testResult = DB::connection('mysql2')->table('beuliping')
//            ->join('vm_beuliping_EN', 'beuliping.id', '=', 'vm_beuliping_EN.m_id') // Adjust join condition
//            ->where('beuliping.symbol', $symbol)
//            ->whereBetween('beuliping.datetime', [$twelveHoursAgoKST, $currentKST])
//            ->orderBy('beuliping.id', 'desc')
////            ->select('beuliping.id', 'beuliping.symbol', 'beuliping.datetime', 'beuliping.images', 'vm_beuliping_EN.content')
//            ->get();
//        Log::info("testResult: ", ["testResult" => $testResult]);

        // Query to find the row where the symbol matches and datetime is within the last 12 hours
        $result = DB::connection('mysql2')->table('beuliping')
            ->join('vm_beuliping_EN', 'beuliping.id', '=', 'vm_beuliping_EN.m_id') // Adjust join condition
            ->where('beuliping.symbol', $symbol)
            ->whereBetween('beuliping.datetime', [$twelveHoursAgoKST, $currentKST])
            ->orderBy('beuliping.id', 'desc')
            ->select('beuliping.id', 'beuliping.symbol', 'beuliping.datetime', 'beuliping.images', 'vm_beuliping_EN.content')
            ->first();

        Log::info("result: ", ["result" => $result]);

        // Check if a result was found
        if (!$result) {
            return "Not in the recommendation list"; // or return a suitable response indicating no recommendation found
        }

        // Format the datetime based on the provided timezone
        $resultDatetime = new DateTime($result->datetime, new DateTimeZone('Asia/Seoul'));

        if ($timezone === 'UTC') {
            $resultDatetime->setTimezone(new DateTimeZone('UTC'));
            $formattedDatetime = $resultDatetime->format('Y-m-d\TH:i:s\Z');
        } else { // Assuming 'KST' or any other timezone would use the default KST formatting
            $formattedDatetime = $resultDatetime->format('Y-m-d\TH:i:sP'); // +09:00 included in the format
        }

        // Append 'USDT' to the symbol for the return statement
        $symbolWithUSDT = $symbol . 'USDT';

        // Return the desired data
        return json_encode([
            'id' => $result->id,
            'datetime' => $formattedDatetime,
            'symbol' => $symbolWithUSDT,
            'images' => $result->images,
            'content' => $result->content
        ]);
    }

    private function calculateTokenCost($inputTokens, $outputTokens) {
        $inputTokenPrice = 5.00 / 1000000; //US$5.00 / 1M input tokens
        $outputTokenPrice = 15.00 / 1000000; //US$15.00 / 1M output tokens

        $inputCost = $inputTokens * $inputTokenPrice;
        $outputCost = $outputTokens * $outputTokenPrice;

        return $inputCost + $outputCost;
    }

    private function getRecommendations($limit, $timezone) {
        $totalResults = collect(); // Initialize an empty collection to store results
        $initialQueryLimit = $limit * 2; // Query more rows initially to ensure enough rows after filtering
        $offset = 0; // Offset for pagination
        $selectedSymbols = []; // Array to keep track of selected symbols

        // Determine the user's timezone
        switch (strtoupper($timezone)) {
            case 'KST':
                $timezoneObj = new DateTimeZone('Asia/Seoul');
                break;
            case 'JST':
                $timezoneObj = new DateTimeZone('Asia/Tokyo');
                break;
            case 'UTC':
            default:
                $timezoneObj = new DateTimeZone('UTC');
                break;
        }

        while ($totalResults->count() < $limit) {
            // Query more rows initially
            $initialResults = DB::connection('mysql2')->table('beuliping')
                ->join('vm_beuliping_EN', 'beuliping.id', '=', 'vm_beuliping_EN.m_id') // Adjust join condition
                ->orderBy('beuliping.id', 'desc')
                ->offset($offset)
                ->limit($initialQueryLimit)
                ->select('beuliping.id','beuliping.symbol','beuliping.datetime','beuliping.images' ,'vm_beuliping_EN.content', DB::raw('DATE_SUB(beuliping.datetime, INTERVAL 9 HOUR) as datetime'))
                ->get();

            // Filter out rows with symbol '1000BONK', content starting with 'No', and already selected symbols
            $filteredResults = $initialResults->filter(function($item) use ($selectedSymbols) {
                return $item->symbol !== '1000BONK' && !str_starts_with($item->content, 'No') && !is_null($item->images) && !in_array($item->symbol, $selectedSymbols);
            });

            // Format the datetime to the specified timezone
            $formattedResults = $filteredResults->map(function($item) use ($timezoneObj, &$selectedSymbols) {
                $dateTime = new DateTime($item->datetime, new DateTimeZone('UTC'));
                $dateTime->setTimezone($timezoneObj);
                $item->datetime = $dateTime->format('Y-m-d\TH:i:sP'); // Format with timezone offset
                $selectedSymbols[] = $item->symbol; // Add the symbol to the selected symbols list
                return $item;
            });

            // Add filtered results to the total results collection
            $totalResults = $totalResults->merge($formattedResults);

            // Check if no more rows are available
            if ($initialResults->count() < $initialQueryLimit) {
                break;
            }

            // Increase offset for next query
            $offset += $initialQueryLimit;
        }

        $result = json_encode($totalResults->take($limit)->values());
        Log::info("Recommendations", ["recommendations" => $result]);
        // Return only the required number of rows
        return $result;
    }

    private function getCryptoDataHourInterval($symbol, $current_time, $hours = 24, $timezone = 'UTC') {
        // Capitalize the symbol and ensure it ends with 'USDT'
        $symbol = strtoupper($symbol);
        if (!str_ends_with($symbol, 'USDT')) {
            $symbol .= 'USDT';
        }

        // Convert current time to a DateTime object in UTC
        $currentDateTime = new DateTime($current_time, new DateTimeZone('UTC'));

        // Calculate the start time ($hours before the current time)
        $startDateTime = clone $currentDateTime;
        $startDateTime->modify('-' . $hours . ' hours');

        // Format the times for MySQL datetime format
        $startTimeFormatted = $startDateTime->format('Y-m-d H:i:s');
        $endTimeFormatted = $currentDateTime->format('Y-m-d H:i:s');

        // Retrieve data from the database for the specified time range
        $data = DB::connection('mysql')->table('trsi.retri_chart_data')
            ->where('simbol', $symbol)
            ->whereBetween('regdate', [$startTimeFormatted, $endTimeFormatted])
            ->orderBy('regdate')
            ->select('simbol as symbol', 'score', 'price', 'regdate')
            ->get();

        // Set the timezone based on the provided parameter
        switch (strtoupper($timezone)) {
            case 'KST':
                $timezoneObj = new DateTimeZone('Asia/Seoul');
                break;
            case 'JST':
                $timezoneObj = new DateTimeZone('Asia/Tokyo');
                break;
            case 'UTC':
            default:
                $timezoneObj = new DateTimeZone('UTC');
                break;
        }

        // Convert the regdate to the user's timezone and format the results
        $resultData = [];
        foreach ($data as $item) {
            $regDate = new DateTime($item->regdate, new DateTimeZone('UTC'));
            $regDate->setTimezone($timezoneObj);

            $formattedDate = $regDate->format('Y-m-d\TH:i:sP'); // Format with timezone offset

            $resultData[] = [
                'symbol' => $item->symbol,
                'score' => $item->score,
                'price' => $item->price,
                'datetime' => $formattedDate,
            ];
        }

        Log::info("Retrieved symbol data", ["symboldata" => $resultData, "symbol" => $symbol]);

        return json_encode([
            'symbol' => $symbol,
            'data' => $resultData
        ]);
    }

    private function getCryptoDataDayInterval($symbol, $current_time, $days = 30, $timezone) {
        // Capitalize the symbol and ensure it ends with 'USDT'
        $symbol = strtoupper($symbol);
        if (!str_ends_with($symbol, 'USDT')) {
            $symbol .= 'USDT';
        }

        // Convert current time to a DateTime object in UTC
        $currentDateTime = new DateTime($current_time, new DateTimeZone('UTC'));

        // Calculate the start time (30 days before the current time)
        $startDateTime = clone $currentDateTime;
        $startDateTime->modify('-' . $days . 'days');

        // Format the times for MySQL datetime format
        $startTimeFormatted = $startDateTime->format('Y-m-d H:i:s');
        $endTimeFormatted = $currentDateTime->format('Y-m-d H:i:s');

        // Retrieve data from the database for the last 30 days
        $data = DB::connection('mysql')->table('trsi.retri_chart_data')
            ->where('simbol', $symbol)
            ->whereBetween('regdate', [$startTimeFormatted, $endTimeFormatted])
            ->orderBy('regdate')
            ->select('simbol as symbol', 'score', 'price', 'regdate')
            ->get();

        // Determine the user's timezone
        $timezoneObj = new DateTimeZone('UTC');
        if ($timezone === 'KST') {
            $timezoneObj = new DateTimeZone('Asia/Seoul');
        } elseif ($timezone === 'JST') {
            $timezoneObj = new DateTimeZone('Asia/Tokyo');
        } else {
            // If the timezone is neither KST nor JST, assume it's UTC or handle other timezones here
            $timezoneObj = new DateTimeZone($timezone);
        }

        // Group data by 24-hour segments and calculate averages
        $averagedData = [];
        $chunk = [];
        foreach ($data as $key => $item) {
            $chunk[] = $item;
            // Every 24 items or the last chunk (if data is not a multiple of 24)
            if (count($chunk) == 24 || $key == $data->count() - 1) {
                $avgPrice = collect($chunk)->avg('price');
                $avgScore = collect($chunk)->avg('score');

                // Use the regdate of the last element in the chunk as the datetime
                $lastDate = new DateTime(end($chunk)->regdate, new DateTimeZone('UTC'));
                $lastDate->setTimezone($timezoneObj); // Apply the user's timezone

                $formattedDate = $lastDate->format('Y-m-d\TH:i:sP'); // Format with timezone offset

                $averagedData[] = [
                    'symbol' => $symbol,
                    'average_price' => $avgPrice,
                    'average_score' => $avgScore,
                    'datetime' => $formattedDate,
                ];
                // Reset chunk
                $chunk = [];
            }
        }

        Log::info("Averaged symbol data", ["symboldata" => $averagedData, "symbol" => $symbol]);

        return json_encode([
            'symbol' => $symbol,
            'data' => $averagedData
        ]);
    }

}
