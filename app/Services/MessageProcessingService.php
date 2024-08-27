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
                'content' => 'You are a crypto market specialist who can deliberately transfer your analysis on more than 200 crypto symbols, utilizing various indicators such as market price, Goya score (indicator to predict the price movement of the symbol), and the crypto recommendation list that changes every hour. When the user asks to analyze cryptocurrencies, you can provide them with its most recent price and score, the price and score movement within time range, the recommendation status of the symbol, and your profound analysis on the symbol derived by comprehensively analyzing every information you have on the symbol cryptocurrency. Many of following system messages define how you can generate and format your response on some typical user inquiries. You are capable to understand and make response in languages including English, Japanese, and Korean. '
            ],
            [
                'role' => 'system',
                'content' => "If you can not infer the locale of the user from the language, then use 'KST' as the default local timezone of the user."
            ],
            [
                'role' => 'system',
                'content' => 'When passing "symbols" parameter to function calls [get_latest_price], [get_crypto_data], [check_recommendation_status], make sure that no character of any symbol in the array is omitted or modified. '
            ],
            [
                'role' => 'system',
                'content' =>
                    'Upon receiving any inquires related to the price and score of the cryptocurrency symbol, or upon receiving inquires to show chart of the symbol, or upon just receiving cryptocurrency symbol names, you should always call the functions [get_crypto_data], [get_latest_price], and [check_recommendation_status] in generating the response. Do not call any other functions except these. If the user did not specify a time range, then pass 24 as the "hours" parameter when calling [get_crypto_data]. Otherwise calculate the specified time range in the user message into the hour unit and pass it as the "hours" parameter.'
            ],
            [
                'role' => 'system',
                'content' =>'When your response message format is json_schema [symbols_format] follow the next rules to generate the response. '
                    .'Rule 1. Each "symbol", "latest_price", "latest_time", "time_gap.hours", "time_gap.minutes" values are retrieved by calling function [get_latest_price]. "symbol" value has to be capitalized. '
                    .'Rule 2. "price_movement", "score_movement", "time_labels" are arrays of each "price", "score", and "datetime" values returned from calling [get_crypto_data]. Make sure no rows are omitted from the retrieved data.'
                    .'Rule 3. "is_recommended", "recommend_time", "recommend_reason_translated", "recommend_image_url", "recommend_time_gap.hours", "recommend_time_gap.minutes" can be filled in by calling [check_recommendation_status]. "recommend_reason_translated" content has be translated into the local language of the user without any original content being omitted. '
                    .'Rule 4. "analysis_translated" is your analysis on all of the relevant data retrieved from calling [get_crypto_data], [get_latest_price] and [check_recommendation_status]. The analysis should be at least 4 sentences long and be presented in a translated plain text. The "recommend_reason" should be considered in making the analysis. '
                    .'Rule 5. "interval" is an integer value of parameter "hours" passed when calling [get_crypto_data]. '
            ],
            [
                'role' => 'system',
                'content' => 'When the user asks to recommend cryptocurrencies, always call the function [get_recommendation]. If the user did not specify the limit on the numbers of recommendation, then pass 3 as the "limit" parameter. Also if the user is not asking for an additional recommendation, pass an empty array to the "recommended_list" parameter.'
            ],
//            [
//              'role' => 'system',
//              'content' => 'When the user asks for additional cryptocurrency recommendation, always call [get_recommendations] with the increased "limit" parameter value. If the user did not specify the additional limit, then add 2.'
//              .'For example, when you previously recommended 3 symbols but the user asks for additional recommendation call [get_recommendations] with limit of 5. When the user asks again for additional recommendation this time the limit will be 7.'
//              .'After calling [get_recommendations], compare the newly retrieved data with all of the previously recommended symbols. From the retrieved data, delete the elements whose symbol is already included in the previous list and make a response. '
//            ],
            [
                'role' => 'system',
                'content' => 'When the response_format is json_schema [recommendation_format], follow the next rules to generate the response. '
                .'Rule 1."symbol", "datetime", "time_gap", "image_url" values can be retrieved by calling function [get_recommendations]'
                .'Rule 2. "content_translated" is the content retrieved from calling function [get_recommendations] translated into the local language of the user. The original content must not be omitted during the translation process.'
                .'Rule 3. Check if the "image_url" value correctly matches the retrieved url from calling [get_recommendations]. '
            ],
            [
              'role' => 'system',
              'content' => 'When the user asks to pick symbols from the recommendation list, first pick symbols from the list and then call the function [get_crypto_data] [get_latest_price] and [get_recommendation_status] with the symbols array. If the user did not specify the number of symbols to pick, then just pick one symbol from the list. '
            ],
            [
                'role' => 'system',
                'content' => 'When the user asks to tell him/her about the cryptocurrency symbol, or asks to explain him/her about the cryptocurrency symbol, then do not call the function [get_crypto_data] or [get_recommendations]. In this case, the response content should focus on explaining about the cryptocurrency symbol itself.'
            ],
            [
                'role' => 'system',
                'content' => "Map the following coin names to their symbols: bitcoin -> btcusdt, ethereum -> ethusdt, solana -> solusdt, ripple or xrp -> xrpusdt. When the user gives symbol that does not end with the word 'usdt', then map it with 'usdt' appended. You can also map any other crypto name and it's symbol that you are already aware of with this rule. "
            ],
            [
                'role' => 'system',
                'content' => 'The score values returned from the function calls [get_crypto_data] is called a "Goya score". It is translate as ゴヤースコア in Japanese, and 고야 스코어 in Korean. Goya score is an indicator to predict the future price of the symbol cryptocurrency.'.
                    'When the Goya score is on a downward trend, the price of the cryptocurrency is likely go down, and otherwise when the score is showing a upward trend, the actual price of the cryptocurrency is likely to go up.'.
                    'Goya score is derived from collecting and analyzing objective blockchain transaction activity data of the symbol cryptocurrency focused mainly on the movements that has positive or negative impacts on the price of the cryptocurrency. However, there are many other objective indicators from which the Goya score is derived from. '
            ],
            [
                'role' => 'system',
                'content' => 'L2 signal means that the symbol cryptocurrency price is on a upward trend. S2 signal means that the price is on a downward trend for that symbol. '
            ],
            [
                'role' => 'system',
                'content' =>
                    'If there is no instruction on the response format, then return the response in this JSON format: {"commons" : CONTENT_NOT_IN_JSON}. '
            ],
        ];
    }

    private function getTools() {
        return [
//            [
//                'type' => 'function',
//                'function' => [
//                    'name' => 'get_crypto_data_in_time_range',
//                    'description' => 'Get the price and score data of a certain cryptocurrency between two specified UTC times on an hourly interval, given the "symbol", "from_time", and "to_time". The returned datetime follows the local timezone and the price unit is USD.',
//                    'parameters' => [
//                        'type' => 'object',
//                        'properties' => [
//                            'symbol' => [
//                                'type' => 'string',
//                                'description' => "The symbol of the cryptocurrency (e.g., 'btcusdt')."
//                            ],
//                            'from_time' => [
//                                'type' => 'string',
//                                'description' => "The start datetime to filter the price data (e.g., '2024-07-29T16:05:06Z'). UTC formatted and is prior to the 'to_time' parameter."
//                            ],
//                            'to_time' => [
//                                'type' => 'string',
//                                'description' => "The end datetime to filter the price data (e.g., '2024-07-30T16:05:06Z'). UTC formatted and is after the 'from_time' parameter."
//                            ],
//                            'timezone' => [
//                                'type' => 'string',
//                                'description' => "The local timezone of the user. Used to format the result datetime to the local timezone of the user.",
//                                'enum' => ['UTC', 'JST', 'KST']
//                            ]
//                        ],
//                        'required' => ['symbol', 'from_time', 'to_time', 'timezone']
//                    ]
//                ]
//            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_crypto_data',
                    'description' => 'Get the price and score data of the cryptocurrency symbol from given hours ago. Required parameters are "symbol","hours", and  "timezone".  The returned price unit is in USD. If the passed "hours" is bigger than 48, then the returned data will be on a daily interval. Else, the returned data will be on an hourly interval',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'symbols' => [
                                'type' => 'array',
                                'items' => [
                                    "type" => 'string',
                                    "description" => 'A cryptocurrency symbol to look for its data (e.g., btc, eth, xrp, ...).'
                                ],
                                'description' => "An array of cryptocurrency symbols."
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
                        'required' => ['symbols', 'hours', 'timezone']
                    ]
                ]
            ],
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
                    'name' => 'get_latest_price',
                    'description' => 'Returns latest recorded price and score, latest recorded time, and the time gap between the current time and the recorded time of cryptocurrencies, given the array of cryptocurrency symbols. ',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'symbols' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'string',
                                    "description" => "A cryptocurrency symbol to look for it's latest price, score, recorded time, and time gap. (e.g., 'btc', 'eth', 'xrp')"
                                ],
                                'description' => "An array of cryptocurrency symbols to look for their latest price, score, recorded time, and time gap."
                            ],
                            'timezone' => [
                                'type' => 'string',
                                'description' => "The local timezone of the user. Used to format the 'datetime' and calculate the 'time_gap' in the local timezone of the user. ",
                                'enum' => ['UTC', 'JST', 'KST']
                            ]
                        ],
                        'required' => ['symbols', 'timezone']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_recommendations',
                    'description' => "Get the data of recommended cryptocurrencies in order to purchase."
                        . "Returns a JSON-encoded array of recommended cryptocurrencies. The 'datetime' is the time when the recommendation was made. ",
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
                            ],
                            'recommended_list' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'string',
                                    'description' => 'The symbol of cryptocurrency already recommended (e.g., btc, eth, sol, ...)'
                                ],
                                'description' => 'The list of cryptocurrency symbols that has already been recommended. Used to filter coins that has been already recommended from the recommendation list.'
                            ]
                        ],
                        'required' => ['limit', 'timezone', 'coin_list']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'check_recommendation_status',
                    'description' => "This function checks the symbol cryptocurrency from the recommendation list and returns the relevant data if the symbol is in the list.",
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'symbols' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'string',
                                    "description" => "A cryptocurrency symbol to check if included in the recommendation list. (e.g., 'btc', 'eth', 'xrp')"
                                ],
                                'description' => "An array of cryptocurrency symbols to check if included in the recommendation list."
                            ],
                            'timezone' => [
                                'type' => 'string',
                                'description' => 'The local timezone of the user. Used to format the "recommendTime" and the "recommendTimeGap" of the symbol. ',
                                'enum' => ['UTC', 'JST', 'KST']
                            ]
                        ],
                        'required' => ['symbols', 'timezone']
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

    private function getResponseFormat($functionList)
    {
        if (in_array('get_crypto_data', $functionList)) {
            return [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'symbols_format',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'symbols' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'symbol' => ['type' => 'string'],
                                        'latest_time' => ['type' => 'string'],
                                        'latest_price' => ['type' => 'number'],
                                        'time_gap' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'hours' => ['type' => 'integer'],
                                                'minutes' => ['type' => 'integer']
                                            ],
                                            'required' => ['hours', 'minutes'],
                                            'additionalProperties' => false
                                        ],
                                        'price_movement' => [
                                            'type' => 'array',
                                            'items' => ['type' => 'number']
                                        ],
                                        'score_movement' => [
                                            'type' => 'array',
                                            'items' => ['type' => 'number']
                                        ],
                                        'time_labels' => [
                                            'type' => 'array',
                                            'items' => ['type' => 'string']
                                        ],
                                        'analysis_translated' => ['type' => 'string'],
                                        'is_recommended' => ['type' => 'boolean'],
                                        'recommend_time' => ['type' => 'string'],
                                        'recommend_reason_translated' => ['type' => 'string'],
                                        'recommend_image_url' => ['type' => 'string'],
                                        'recommend_time_gap' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'hours' => ['type' => 'integer'],
                                                'minutes' => ['type' => 'integer']
                                            ],
                                            'required' => ['hours', 'minutes'],
                                            'additionalProperties' => false
                                        ],
                                        'interval' => ['type' => 'integer']
                                    ],
                                    'required' => [
                                        'symbol', 'latest_price', 'latest_time', 'time_gap', 'price_movement',
                                        'score_movement', 'time_labels', 'analysis_translated', 'is_recommended',
                                        'recommend_time', 'recommend_reason_translated', 'recommend_image_url',
                                        'recommend_time_gap', 'interval'
                                    ],
                                    'additionalProperties' => false
                                ]
                            ],
                        ],
                        'required' => ['symbols'],
                        'additionalProperties' => false
                    ],
                    'strict' => true
                ],
            ];
        }
        elseif (in_array('get_recommendations', $functionList)) {
            return [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'recommendation_format',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'recommendations' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'symbol' => ['type' => 'string'],
                                        'datetime' => ['type' => 'string'],
                                        'time_gap' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'hours' => ['type' => 'integer'],
                                                'minutes' => ['type' => 'integer']
                                            ],
                                            'required' => ['hours', 'minutes'],
                                            'additionalProperties' => false
                                        ],
                                        'image_url' => ['type' => 'string'],
                                        'content_translated' => ['type' => 'string']
                                    ],
                                    'required' => ['symbol', 'datetime', 'time_gap', 'image_url', 'content_translated'],
                                    'additionalProperties' => false
                                ]
                            ]
                        ],
                        'required' => ['recommendations'],  // This specifies that the "recommendations" array is required
                        'additionalProperties' => false
                    ],
                    'strict' => true
                ]
            ];

        } else {
            return [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'default_format',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'default' => ['type' => 'string']
                        ],
                        'required' => ['commons'],
                        'additionalProperties' => false
                    ]
                ]
            ];
        }

    }


    private function sendMessageToOpenAI($messages, $tools, $userId, $functionList)
    {
        try {
            // Get the response format based on the functionList
            $responseFormat = $this->getResponseFormat($functionList);

            $response = OpenAI::chat()->create([
//                'model' => 'gpt-4o-2024-08-06',
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
                'tools' => $tools,
                'tool_choice' => 'auto',
                'response_format' => $responseFormat,
                'parallel_tool_calls' => true
            ]);

            $responseMessage = $response['choices'][0]['message'];
            $promptToken = $response['usage']['prompt_tokens'];
            $completionToken = $response['usage']['completion_tokens'];
            $totalToken = $response['usage']['total_tokens'];
            $responseCost = $this->tokenService->calculateTokenCost($promptToken, $completionToken);

            $this->tokenService->addCost($userId, $responseCost);
            $this->tokenService->setMaxToken($userId, $totalToken);

            // Append the message
            $messages[] = $responseMessage;
            Log::info('response message: ', ["responseMessage" => $responseMessage]);
            // Recurse logic
            $toolCalls = $responseMessage['tool_calls'] ?? [];
            if (empty($toolCalls)) { // Return final response
//                Log::info('response content: ', ["responseContent" => $responseMessage['content']]);
                return [
                    'responseText' => $responseMessage['content']
                ];
            } elseif (count($functionList) > 12) { // Stop recursion if functionList length exceeds 12
                Log::warning('Function list length exceeded 12, stopping recursion.');
                return [
                    'responseText' => $responseMessage['content']
                ];
            } else { // Continue recursion
                $availableFunctions = [
                    'get_latest_price' => [$this->cryptoService, 'getLatestPrice'],
                    'get_crypto_data' => [$this->cryptoService, 'getCryptoData'],
//                    'get_crypto_data_in_time_range' => [$this->cryptoService, 'getCryptoDataInTimeRange'],
                    'get_current_time' => [$this->cryptoService, 'getCurrentTime'],
                    'get_recommendations' => [$this->cryptoService, 'getRecommendation'],
                    'check_recommendation_status' => [$this->cryptoService, 'checkRecommendationStatus']
                ];

                foreach ($toolCalls as $toolCall) {
                    $functionName = $toolCall['function']['name'] ?? null;
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
        } catch (\Exception $e) {
            Log::error('Error Communicating with OpenAI: ', ['error' => $e->getMessage()]);
            $this->tokenService->setCostToZero($userId);
            return ['error' => 'Error: ', $e->getMessage()];
        }
    }





}
