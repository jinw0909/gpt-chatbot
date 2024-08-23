<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class MessageProcessingService
{
    private $tokenService;
    private $cryptoService;

    /**
     * @param $tokenService
     * @param $cryptoService
     */
    public function __construct(TokenService $tokenService, CryptoService $cryptoService)
    {
        $this->tokenService = $tokenService;
        $this->cryptoService = $cryptoService;
    }

    public function processMessage($message, $userId, $conversation, $token) {
        $systems = $this->getSystemMessages();
        $tools = $this->getTools();

        $userId = (string) $userId;
        $this->tokenService->initializeSession($userId, $token);

        //Summarization logic if token exceeds limit
        $summary = null;
        if ($this->tokenService->exceedsMaxLimit($userId, 7000)) {
            $summary = $this->summarizeConversation($conversation);
            $conversation = [$summary];
        }

        $messages = $this->prepareMessages($message, $systems, $conversation);
        $response = $this->sendMessageToOpenAI($messages, $tools, $userId, $functionList = []);

        $maxUsage = $this->tokenService->getMaxUsage($userId);
        $left = $this->tokenService->reduceCharge($userId);
        return response()->json([
            'responseText' => $response['responseText'],
            'maxUsage' => $maxUsage,
            'summary' => $summary,
            'left' => $left
        ]);
    }

    private function getSystemMessages() {
        // Define system messages logic here
        return [
            [
                'role' => 'system',
                'content' => "You can infer the user's locale and the local timezone by the analyzing the language of the user message. If you can not infer the locale of the user from the language, then use 'KST' as the default local timezone of the user."
            ],
//            [
//                'role' => 'system',
//                'content' => 'When you have to convert UTC time to the local time call the function [convert_to_local_time]. If user locale is Japan, pass "JST" as timezone. If the user locale is Korea, pass "KST" as timezone. Else pass "UTC" as timezone'.
//                    'Always call the function [get_current_time] when you have to check what the current time is. Do not use the pre-trained data to get the current time.'
//            ],
            [
                'role' => 'system',
                'content' =>
                    'When the user asks to analyze single or multiple cryptocurrencies, or asks to analyze the its price or score from some time ago, or just gives cryptocurrency symbols, or asks to pick from the recommendation list, then you must follow the steps below and format the response in the following JSON array format {"symbols": [{"symbol": CAPITALIZED SYMBOL STRING, "latest_price": THE LATEST PRICE RETURNED FROM CALLING [GET_LATEST_PRICE], "latest_time": THE DATETIME RETURNED FROM CALLING [GET_LATEST_PRICE], "time_gap": {"days" : INTEGER, "hours": INTEGER, "minutes" : INTEGER, "seconds" : INTEGER},"price_movement": FLOAT ARRAY OF THE PRICES RETURNED FROM CALLING [GET_CRYPTO_DATA], "score_movement": FLOAT ARRAY OF THE SCORES RETURNED FROM CALLING [GET_CRYPTO_DATA], "time_labels": STRING ARRAY OF TIMESTAMPS RETURNED FROM CALLING [GET_CRYPTO_DATA], "analysis_translated": YOUR ANALYSIS ON THE SYMBOL FROM ALL THE RELEVANT DATA SOURCES RETURNED FROM CALLING [GET_CRYPTO_DATA] AND [CHECK_IF_RECOMMENDED] TRANSLATED INTO THE RESPONSE LANGUAGE, "is_recommended" : BOOLEAN RETURNED FROM CALLING [CHECK_IF_RECOMMENDED], "recommend_time": STRING TIMESTAMP RETURNED FROM CALLING [CHECK_IF_RECOMMENDED] TRANSLATED INTO THE RESPONSE LANGUAGE, "recommend_reason_translated": RECOMMEND_REASON RETURNED FROM CALLING [IS_RECOMMENDED] TRANSLATED INTO THE RESPONSE LANGUAGE, "recommend_image_url" : IMAGE URL RETURNED FROM CALLING [CHECK_IF_RECOMMENDED], "recommend_time_gap: {"days" : INTEGER, "hours": INTEGER, "minutes" : INTEGER, "seconds" : INTEGER} }, "interval" : INTEGER}, ...]}. The keys in the JSON response should strictly match this given format. '.
                    'Step 1. Get the "latest_price","latest_time", each value of "time_gap" keys by calling function [get_latest_price] with the symbol and the local timezone of the user as parameters. '
                    .'Step 2. Call [get_crypto_data]. If the user specified a time range, then convert the time into hours and pass it as the "hours" parameter. If the user did not specify any time range, or you can not infer any time range related information from the user message, then call with "hours" parameter set to 24. After calling the function, fill in the values of "price_movement", "score_movement", "time_labels" with the retrieved score, price, datetime data. '
                    .'Step 3. Fill in the value of the key "interval" with integer value of "hours" parameter passed to function [get_crypto_data] at step 2.'
                    .'Step 4. Call [check_if_recommended] then fill in the value of keys "is_recommended", "recommend_time", "recommend_reason_translated" , each keys of "recommend_time_gap.'
                    .'Step 5. The "analysis_translated" is your analysis derived from all the relevant data sources retrieved from calling functions [get_crypto_data] and [check_if_recommended]. The analysis should be translated into the response language. The analysis should contain the analysis on the price and score movements and be at least 4 sentences long. Analysis of the "recommend_reason" should mention the "recommend_time", which is the time when the recommendation_reason was made. '
                    .'Step 6. Check if the "price_movement", "score_movement", "time_labels" include all the price, score, and datetime values retrieved from calling [get_crypto_data]. All three are supposed to have to same length. '
                    .'6. Check if all the response is translated properly into the local language including "analysis_translated" and "recommend_reason_translated". Also check if the "recommendation_image_url" exactly matches the retrieved image url. '
            ],
            [
                'role' => 'system',
                'content' => 'When you invoke the function call [get_recommendations], return the response in the following order.'.
                    '1. When the user did not specify a specific limit of recommendation, then use default 3 as the recommendation limit.'.
                    '2. Return the response in the following JSON format. {"recommendations" : [{"symbol": "STRING", "datetime": "STRING", "image": "URL_STRING", "content": "STRING"}, ...]}. The key should not be "commons"'.
                    "3. Your whole response has be translated into the local language of the user."
            ],
            [
                'role' => 'system',
                'content' => 'When the user asks for additional recommendation, then first call the function [get_recommends] with the limit added to the previous recommendation.'.
                    'For example when the user asks to recommend two more coins, and when the limit parameter passed to the previous [get_recommends] was 3, then this time call the [get_recommends] with limit 6'.
                    'When the user doesnt specify the additional limit (such as recommend me some more cryptocurrencies, recommend few more) then add 3 to the previous limit'.
                    'After the additional [get_recommends] have returned its result, then compare two lists and only return the recommendations that was not included in the previous recommendation list in a json array format'.
                    'The response json array format should be as follows. {"recommendations" : [{"symbol": "STRING", "datetime": "STRING", "image": "URL_STRING", "content": "STRING"}, ...]}'
            ],

            [
                'role' => 'system',
                'content' => "Map the following coin names to their symbols: bitcoin -> btcusdt, ethereum -> ethusdt, solana -> solusdt, ripple or xrp -> xrpusdt. When the user gives symbol that does not end with the word 'usdt', then map it with 'usdt' appended. You can also map any other crypto name and it's symbol that you are already aware of with this rule. "
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
                'content' => 'The score values returned from the function calls [get_crypto_data_in_48], [get_crypto_data_over_48], [get_crypto_data_in_time_range] is called a "Goya score"(ゴヤースコア in Japanese, 고야 스코어 in Korean). It is an indicator to predict the future price of the symbol cryptocurrency.'.
                    'When the Goya score is on a downward trend, the price of the cryptocurrency is likely go down, and otherwise when the score is showing a upward trend, the actual price of the cryptocurrency is likely to go up.'.
                    'This score is derived by analyzing objective blockchain transaction activity data focusing mostly on the movements that has positive or negative impacts on the price of the cryptocurrency. '.
                    'From the content returned from calling [get_recommendations] or [check_if_recommended], L2 signal means that the symbol price is on a upward trend. S2 signal means that the symbol is on a downward trend.'
            ],
            [
                'role' => 'system',
                'content' =>
                    'If there is a system instruction on the formatting your response, the specified format always takes priority.'.
                    'If there is no instruction on the response format, then return the response in this JSON format: {"common" : RESPONSE_CONTENT_NOT_IN_JSON}. '
            ],

//            [
//                'role' => 'system',
            //                'content' => 'When the user asks any further questions on the given price and score analysis, or asks for further explanation of the given analysis, the response should be made in the following JSON format: '.
            //                    '{"symbols" : [{"symbol": STRING, "time": "current_time": STRING, "recent_price" : FLOAT, "price_movement": FLOAT_ARRAY, "score_movement": FLOAT_ARRAY, "time_labels": STRING_ARRAY, "analysis": TEXT}, ...]}'
//            ]
        ];
    }

    private function getTools() {
        return [
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
                                'description' => "The start datetime to filter the price data (e.g., '2024-07-29T16:05:06Z'). UTC formatted and is prior to the 'to_time' parameter."
                            ],
                            'to_time' => [
                                'type' => 'string',
                                'description' => "The end datetime to filter the price data (e.g., '2024-07-30T16:05:06Z'). UTC formatted and is after the 'from_time' parameter."
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
                    'name' => 'get_crypto_data',
                    'description' => 'Get the price and score data of a cryptocurrency symbol from given hours ago. Required parameters are "symbol","hours", and  "timezone". The returned datetime follows the local timezone. The returned price unit is in USD. If the passed "hours" is bigger than 48, then the returned data will be on a daily interval. Else, the returned data will be on an hourly interval',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'symbol' => [
                                'type' => 'string',
                                'description' => "The symbol of the cryptocurrency (e.g., 'btc, eth, xrp')."
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
                        'required' => ['symbol', 'hours', 'timezone']
                    ]
                ]
            ],
//            [
//                'type' => 'function',
//                'function' => [
//                    'name' => 'get_crypto_data_over_48',
//                    'description' => 'Get the price and score data of a cryptocurrency symbol from the given time ago with a 24 hour interval. Passed parameters are "symbol", "days", and "timezone". The returned datetime follows the local timezone and the price unit is USD. Only invoked when the time range is larger than 48 hours',
//                    'parameters' => [
//                        'type' => 'object',
//                        'properties' => [
//                            'symbol' => [
//                                'type' => 'string',
//                                'description' => "The symbol of the cryptocurrency (e.g., 'btcusdt')."
//                            ],
//                            'days' => [
//                                'type' => 'integer',
//                                'description' => "The number of days ago from the current time from when the price and score data will be retrieved."
//                            ],
//                            'timezone' => [
//                                'type' => 'string',
//                                'description' => "The local timezone of the user. Used when formatting the result datetime to the local timezone of the user.",
//                                'enum' => ['UTC', 'JST', 'KST']
//                            ]
//                        ],
//                        'required' => ['symbol', 'days', 'timezone']
//                    ]
//                ]
//            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_current_time',
                    'description' => 'Returns the current local time',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'timezone' => [
                                'type' => 'string',
                                'description' => "The local timezone of the user.",
                                'enum' => ['UTC', 'JST', 'KST']
                            ]
                        ],
                        'required' => ['timezone']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_time_gap',
                    'description' => 'Given the certain datetime and the timezone, calculate and return the gap between the current time and the given datetime.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'datetime' => [
                                'type' => 'string',
                                'description' => 'The target datetime to calculate the gap from the current time. Formatted in the local timezone of the user.'
                            ],
                            'timezone' => [
                                'type' => 'string',
                                'description' => "The local timezone of the user. Used to get the current time of the local timezone.",
                                'enum' => ['UTC', 'JST', 'KST']
                            ]
                        ],
                        'required' => ['datetime', 'timezone']
                    ]
                ],
            ],
//            [
//                'type' => 'function',
//                'function' => [
//                    'name' => 'convert_to_local_time',
//                    'description' => 'Returns the current local time given the UTC time and the local timezone',
//                    'parameters' => [
//                        'type' => 'object',
//                        'properties' => [
//                            'utc_time' => [
//                                'type' => 'string',
//                                'description' => 'The target UTC time to be converted into the local timezone'
//                            ],
//                            'timezone' => [
//                                'type' => 'string',
//                                'description' => "The local timezone of the user. Used to format the UTC time to the local time.",
//                                'enum' => ['UTC', 'JST', 'KST']
//                            ]
//                        ],
//                        'required' => ['utc_time','timezone']
//                    ],
//                ],
//            ],
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
                                'description' => "The symbol of the cryptocurrency (e.g., 'btc, eth, ...')."
                            ],
                            'timezone' => [
                                'type' => 'string',
                                'description' => "The local timezone of the user. Used when formatting the recorded datetime as the local timezone of the user. ",
                                'enum' => ['UTC', 'JST', 'KST']
                            ]
                        ],
                        'required' => ['symbol', 'timezone']
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
                        . "Returns a JSON-encoded array of recommended cryptocurrencies. The datetime value is returned in a local timezone. It indicates the time when the 'content' was written. ",
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
    }

    private function summarizeConversation($conversation)
    {
        $conversationString = json_encode($conversation);
        $summaryPrompt = [
            ['role' => 'system', 'content' => 'Please summarize the following conversation...'],
            ['role' => 'system', 'content' => $conversationString]
        ];

        $summaryResponse = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => $summaryPrompt,
            'max_tokens' => 500
        ]);

        return [
            'role' => 'system',
            'content' => 'Summary of the previous conversation: ' . $summaryResponse['choices'][0]['message']['content']
        ];
    }

    private function prepareMessages($message, $systems, $conversation)
    {
        $userMessage = [['role' => 'user', 'content' => $message]];

        return !is_null($conversation) && is_array($conversation)
            ? array_merge($systems, $conversation, $userMessage)
            : array_merge($systems, $userMessage);
    }

    private function sendMessageToOpenAI($messages, $tools, $userId, $functionList)
    {
        // Message sending and recursive logic
        // This can be refactored further based on specific needs
        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
                'tools' => $tools,
                'tool_choice' => 'auto',
                'response_format' => ['type' => 'json_object'],
                'parallel_tool_calls' => true
            ]);

            $responseMessage = $response['choices'][0]['message'];
            $promptToken = $response['usage']['prompt_tokens'];
            $completionToken = $response['usage']['completion_tokens'];
            $totalToken = $response['usage']['total_tokens'];
            $responseCost = $this->tokenService->calculateTokenCost($promptToken, $completionToken);

            $this->tokenService->addCost($userId, $responseCost);
            $this->tokenService->setMaxToken($userId, $totalToken);

            //append the message
            $messages[] = $responseMessage;

            //recurse logic
            $toolCalls = $responseMessage['tool_calls'] ?? [];
            if (empty($toolCalls)) { //return
                Log::info('response content: ', ["responseContent" => $responseMessage['content']]);
                return [
                    'responseText' => $responseMessage['content']
                ];
            } else { //recurse
//                Log::info("toolCalls: ", ["toolCalls" => $toolCalls]);
                $availableFunctions = [
                  'get_latest_price' => [$this->cryptoService, 'getLatestPrice'], 'get_crypto_data' => [$this->cryptoService, 'getCryptoData'],
                  'get_crypto_data_in_time_range' => [$this->cryptoService, 'getCryptoDataInTimeRange'],
                  'get_current_time' => [$this->cryptoService, 'getCurrentTime'],
                    'get_time_gap' => [$this->cryptoService, 'getTimeGap'],
                    'get_recommendations' => [$this->cryptoService, 'getRecommendations'],
                    'check_if_recommended' => [$this->cryptoService, 'checkIfRecommended']
                ];

                foreach($toolCalls as $toolCall) {
                    $functionName = $toolCall['function']['name'] ?? null; // Use null coalescing to handle missing keys
                    if ($functionName) {
                        Log::info("functionName: ", ["functionName" => $functionName]);
                        $functionList[] = $functionName;
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
                Log::info("functionList: ", ["functionList" => $functionList]);
                return $this->sendMessageToOpenAI($messages, $tools, $userId, $functionList);
            }

        } catch ( \Exception $e) {
            Log::error('Error Communicating with OpenAI: ', ['error' => $e->getMessage()]);
            $this->tokenService->setCostToZero($userId);
            return ['error' => 'Error: ', $e->getMessage()];
        }

    }


}
