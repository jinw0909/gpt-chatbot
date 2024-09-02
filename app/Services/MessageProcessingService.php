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
                'content' => "If you cannot infer the locale of the user from the language, then use 'KST' as the default local timezone of the user."
            ],
            [
                'role' => 'system',
                'content' => "By default your response language should match the language of the last user message. "
            ],
            [
                'role' => 'system',
                'content' => 'When passing "symbols" parameter to functions "analyze_crypto", "get_symbol_price", "get_crypto_data", "get_recommendation_status", Make sure that the last letter of the symbol is not missing or altered. '
            ],
            [
                'role' => 'system',
                'content' =>
                    'Upon receiving any user inquiry related to the price and score of the crypto symbol, or upon receiving any inquiry to show the chart of the symbol, or upon just receiving crypto symbol name, you should response in the "format_type" of "crypto_analyses" and you should always call the function "analyze_crypto" and pass the symbol as the argument to get all the relevant data to complete the response for these type of inquiries. '
            ],
            [
                'role' => 'system',
                'content' => 'When your response "format_type" is "crypto_analyses", you must call the function "analyze_crypto" to complete the response. The last response field, "analysis_translated", must include detailed analysis on the price and score movement trend of the symbol crypto, not just introducing the overall movement trend but also dealing with the critical points where the price and score largely fluctuated. The analysis should also refer to the "recommended_reason_translated" content which explains why the symbol is currently recommended. '
            ],
//            [
//                'role' => 'system',
//                'content' => 'When your response "format_type" is "crypto_analyses", you must call functions "get_symbol_price", "get_crypto_data" , "get_recommendation_status" and follow the next rules to generate the response.'
//                    .'All three functions, "get_symbol_price", "get_crypto_data", and "get_recommendation_status", **must** be called whenever "format_type" is "crypto_analyses".'
//                    .'Rule 1. Retrieve the values of "symbol", "symbol_price", "record_time", "time_gap.hours", "time_gap.minutes" by calling the function "get_symbol_price". The value of "symbol" should be capitalized. '
//                    .'Rule 2. Retrieve the values of "crypto_data.datetime", "crypto_data.score", "crypto_data.price" by calling function "get_crypto_data". Calculate the specified time in user message to the hour unit and pass it as the "hours" parameter. The array should be in time order and no rows should be omitted.'
//                    .'Rule 3. Retrieve the values of "recommendation_status.is_recommended", "recommendation_status.recommended_time", "recommendation_status.getTools_translated", "recommendation_status.image_url", "recommendation_status.time_gap.hours", "recommendation_status.time_gap.minutes" by calling function "get_recommendation_status". "recommended_reason_translated" should be translated into the user language. '
//                    .'Rule 4. "analysis_translated" is your analysis on all of the relevant data retrieved from calling "get_crypto_data", "get_symbol_price" and "get_recommendation_status" translated into the local language of the user. The analysis should be at least 4 sentences long and be presented as plain text. It is important that the "recommended_reason_translated" is considered when creating the analysis. '
//                    .'Rule 5. "interval" is an integer value of the parameter "hours" passed when calling "get_crypto_data". '
//            ],
            [
                'role' => 'system',
                'content' => 'When the user asks to recommend cryptocurrencies, then always call the function "get_recommended_symbols". If the user did not specify the number, then pass 3 as the "limit" parameter. Pass an empty array to the "already_recommended" parameter. The response "format_type" should be "recommendations". '
            ],
            [
                'role' => 'system',
                'content' => 'Every time the user asks for additional recommendations, then call the function "get_recommended_symbols" with the parameter "already_recommended" with already recommended symbols after checking the conversation history. If the user did not specify the number, then pass 3 as the "limit" parameter. If the user specified a specific number, then pass it as the limit parameter. Look up the previous conversation between the user and pass the previously recommended symbols as the "already_recommended" parameter. The response "format_type" should be "recommendations". '
            ],
            [
                'role' => 'system',
                'content' => 'When your response "format_type" is "recommendations", follow the next rules to generate the response. '
                .'Rule 1."symbol", "datetime", "time_gap", "image_url" values can be retrieved by calling the function "get_recommended_symbols". The value of "symbol" should be capitalized. '
                .'Rule 2. "recommended_reason_translated" is the translation of recommended_reason retrieved from calling function "get_recommended_symbols". The original recommended_reason content must not be omitted during the translation process.'
                .'Rule 3. Check if the "image_url" value correctly matches the retrieved url. '
            ],
            [
              'role' => 'system',
              'content' => 'When the user asks to pick symbols from the previous recommendation list, first pick symbols from the previous list and return the response with the format_type of "crypto_analyses". If the user did not specify the number of symbols to pick, then just pick one symbol from the previous list. '
            ],
            [
                'role' => 'system',
                'content' => 'When the user asks to tell him/her about the cryptocurrency symbol, or asks to explain him/her about the cryptocurrency symbol, then respond in the format_type of "commons". In this case, the response content should focus on explaining about the cryptocurrency symbol itself.'
            ],
            [
                'role' => 'system',
                'content' => "Map the following coin names to their symbols: bitcoin -> btcusdt, ethereum -> ethusdt, solana -> solusdt, ripple or xrp -> xrpusdt. When the user gives symbol that does not end with the word 'usdt', then map it with 'usdt' appended. You can also map any other crypto name and it's symbol that you are already aware of with this rule. "
            ],
            [
                'role' => 'system',
                'content' => 'The score values returned from the function calls [get_crypto_data] is called a "Goya score". It is translated as "ゴヤースコア" in Japanese, and "고야 스코어" in Korean. Goya score is an indicator to predict the future price of the symbol cryptocurrency.'.
                    'When the Goya score is on a downward trend, the price of the cryptocurrency is likely go down, and otherwise when the score is showing a upward trend, the actual price of the cryptocurrency is likely to go up.'.
                    'Goya score is derived from collecting and analyzing objective blockchain transaction activity data of the symbol cryptocurrency focused mainly on the movements that has positive or negative impacts on the price of the cryptocurrency. However, there are many other objective indicators from which the Goya score is derived from. '
            ],
            [
                'role' => 'system',
                'content' => 'From the "recommended_reason" content, L1, L2, L3 signal means that the symbol cryptocurrency price is on a upward trend. S1, S2, S3 signal means that the price is on a downward trend for that symbol. However the S1, S2, S3 signals are not necessarily negative signals because futures short trading is also a possible choice in the field of crypto trading. When creating the analysis, this point of view may be reflected as well. '
            ],
            [
                'role' => 'system',
                'content' =>
                    'If there is no instruction on the response format, then return the response in this JSON format: {"data" : { "format_type" : "commons", "content" : RESPONSE_IN_STRING }}. '
            ],
        ];
    }

    private function getTools() {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'analyze_crypto',
                    'description' => 'The function to get overall analysis of the given cryptocurrency symbol including price data, score data and the recommendation status.',
                    'strict' => false,
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'symbol' => [
                                'type' => 'string',
                                'description' => 'A cryptocurrency symbol to analyze (e.g., btc, eth, xrp, ...).'
                            ],
                            'hours' => [
                                'type' => 'number',
                                'description' => 'The number of hours ago from the current time from when the price and score data will be retrieved.'
                            ],
                            'timezone' => [
                                'type' => 'string',
                                'description' => 'The local timezone of the user. Used when formatting the result datetime to the local timezone of the user.',
                                'enum' => ['UTC', 'JST', 'KST']
                            ]
                        ],
                        'additionalProperties' => false, // Correctly placed
                        'required' => ['symbol', 'hours', 'timezone'],
                    ]
                ]
            ],
//            [
//                'type' => 'function',
//                'function' => [
//                    'name' => 'get_crypto_data',
//                    'description' => 'The function to get the price and score data of the crypto symbol from given hours ago. Every time should be calculated as hours and passed as the "hours" parameter. The returned price unit is in USD. If the passed "hours" is bigger than 48, then the returned data will be on a daily interval.',
//                    'strict' => false,
//                    'parameters' => [
//                        'type' => 'object',
//                        'properties' => [
//                            'symbol' => [
//                                'type' => 'string',
//                                'description' => 'A cryptocurrency symbol to look for its data (e.g., btc, eth, xrp, ...).'
//                            ],
//                            'hours' => [
//                                'type' => 'number',
//                                'description' => 'The number of hours ago from the current time from when the price and score data will be retrieved.'
//                            ],
//                            'timezone' => [
//                                'type' => 'string',
//                                'description' => 'The local timezone of the user. Used when formatting the result datetime to the local timezone of the user.',
//                                'enum' => ['UTC', 'JST', 'KST']
//                            ]
//                        ],
//                        'additionalProperties' => false, // Correctly placed
//                        'required' => ['symbol', 'hours', 'timezone'],
//                    ]
//                ]
//            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_current_time',
                    'description' => 'The function to get the current local time given the timezone',
                    'strict' => true,
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'timezone' => [
                                'type' => 'string',
                                'description' => "The local timezone of the user.",
                                'enum' => ['UTC', 'JST', 'KST']
                            ]
                        ],
                        'required' => ['timezone'],
                        'additionalProperties' => false
                    ]
                ]
            ],
//            [
//                'type' => 'function',
//                'function' => [
//                    'name' => 'get_symbol_price',
//                    'description' => 'Returns price and score, and other data of the crypto symbol, given the array of symbols',
//                    'strict' => false,
//                    'parameters' => [
//                        'type' => 'object',
//                        'properties' => [
//                            'symbol' => [
//                                'type' => 'string',
//                                "description" => "A cryptocurrency symbol to look for it's latest price, score, recorded time, and time gap. (e.g., 'btc', 'eth', 'xrp')"
//                            ],
//                            'timezone' => [
//                                'type' => 'string',
//                                'description' => "The local timezone of the user. Used to format the 'datetime' and calculate the 'time_gap' in the local timezone of the user. ",
//                                'enum' => ['UTC', 'JST', 'KST']
//                            ]
//                        ],
//                        'required' => ['symbol', 'timezone'],
//                        'additionalProperties' => false
//                    ]
//                ]
//            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_recommended_symbols',
                    'description' => "Get the current recommendation data."
                        . "Returns a JSON-encoded array of recommended cryptocurrencies and their recommendation data. The 'datetime' is the time when the recommendation was made. ",
                    'strict' => true,
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'limit' => [
                                'type' => 'number',
                                'description' => "The limit of the recommendation."
                            ],
                            'timezone' => [
                                'type' => 'string',
                                'description' => "The local timezone of the user",
                                'enum' => ['UTC', 'JST', 'KST']
                            ],
                            'already_recommended' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'string',
                                    'description' => 'The symbol of cryptocurrency already recommended previously (e.g., btc, eth, sol, ...)'
                                ],
                                'description' => 'The list of cryptocurrency symbols that has been already recommended previously. Used to filter coins When the user asks for additional recommendation.'
                            ]
                        ],
                        'required' => ['limit', 'timezone', 'already_recommended'],
                        'additionalProperties' => false
                    ]
                ]
            ],
//            [
//                'type' => 'function',
//                'function' => [
//                    'name' => 'get_recommendation_status',
//                    'description' => "This function checks the symbol cryptocurrency from the recommendation list and returns the relevant data if the symbol is in the list. Call this function to get the recommendation status data of a crypto symbol. This function should always triggered when analyze a certain crypto symbol. ",
//                    'strict' => false,
//                    'parameters' => [
//                        'type' => 'object',
//                        'properties' => [
//                            'symbol' => [
//                                'type' => 'string',
//                                "description" => "A cryptocurrency symbol to check if included in the recommendation list. (e.g., 'btc', 'eth', 'xrp')"
//                            ],
//                            'timezone' => [
//                                'type' => 'string',
//                                'description' => 'The local timezone of the user. Used to format the "recommendTime" and the "recommendTimeGap" of the symbol. ',
//                                'enum' => ['UTC', 'JST', 'KST']
//                            ]
//                        ],
//                        'required' => ['symbol', 'timezone'],
//                        'additionalProperties' => false
//                    ]
//                ]
//            ],
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

    private function getResponseFormat()
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'unified_format',
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'data' => [
                            'anyOf' => [
                                [
                                    'title' => 'Crypto Analyses Format',
                                    'description' => 'This format is used for detailed crypto analyses',
                                    'type' => 'object',
                                    'properties' => [
                                        'format_type' => [
                                            'type' => 'string',
                                            'enum' => ['crypto_analyses']
                                        ],
                                        'content' => [
                                            'type' => 'array',
                                            'items' => [
                                                'type' => 'object',
                                                'properties' => [
                                                    'symbol' => ['type' => 'string'],
                                                    'symbol_data' => [
                                                        'type' => 'object',
                                                        'properties' => [
                                                          'symbol_price' => ['type' => 'number'],
                                                          'record_time' => ['type' => 'string'],
                                                          'time_gap' => [
                                                              'type' => 'object',
                                                              'properties' => [
                                                                  'hours' => ['type' => 'number'],
                                                                  'minutes' => ['type' => 'number']
                                                              ],
                                                              'required' => ['hours', 'minutes'],
                                                              'additionalProperties' => false
                                                          ],
                                                      ],
                                                        'required' => ['symbol_price', 'record_time', 'time_gap'],
                                                        'additionalProperties' => false
                                                    ],
                                                    'crypto_data' => [
                                                        'type' => 'array',
                                                        'items' => [
                                                            'type' => 'object',
                                                            'properties' => [
                                                                'datetime' => ['type' => 'string'],
                                                                'score' => ['type' => 'number'],
                                                                'price' => ['type' => 'number']
                                                            ],
                                                            'required' => ['datetime', 'score', 'price'],
                                                            'additionalProperties' => false
                                                        ]
                                                    ],
                                                    "recommendation_status" => [
                                                        'type' => 'object',
                                                        'properties' => [
                                                            'is_recommended' => ['type' => 'boolean'],
                                                            'recommended_datetime' => ['type' => 'string'],
                                                            'recommended_reason_translated' => ['type' => 'string'],
                                                            'image_url' => ['type' => 'string'],
                                                            'time_gap' => [
                                                                'type' => 'object',
                                                                'properties' => [
                                                                    'hours' => ['type' => 'number'],
                                                                    'minutes' => ['type' => 'number']
                                                                ],
                                                                'required' => ['hours', 'minutes'],
                                                                'additionalProperties' => false
                                                            ],
                                                        ],
                                                        'required' => ['is_recommended', 'recommended_datetime', 'recommended_reason_translated', 'image_url', 'time_gap'],
                                                        'additionalProperties' => false
                                                    ],
                                                    'interval' => ['type' => 'number'],
                                                    'analysis_translated' => ['type' => 'string']
                                                ],
                                                'required' => [
                                                    'symbol', 'symbol_data', 'crypto_data', 'analysis_translated', 'recommendation_status', 'interval'
                                                ],
                                                'additionalProperties' => false
                                            ]
                                        ]
                                    ],
                                    'required' => ['format_type', 'content'],
                                    'additionalProperties' => false
                                ],
                                [
                                    'title' => 'Recommendations Format',
                                    'description' => 'This format is used for recommendations data',
                                    'type' => 'object',
                                    'properties' => [
                                        'format_type' => [
                                            'type' => 'string',
                                            'enum' => ['recommendations']
                                        ],
                                        'content' => [
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
                                                    'recommended_reason_translated' => ['type' => 'string']
                                                ],
                                                'required' => ['symbol', 'datetime', 'time_gap', 'image_url', 'recommended_reason_translated'],
                                                'additionalProperties' => false
                                            ]
                                        ]
                                    ],
                                    'required' => ['format_type', 'content'],
                                    'additionalProperties' => false
                                ],
                                [
                                    'title' => 'Commons Format',
                                    'description' => 'This format is used for general text content',
                                    'type' => 'object',
                                    'properties' => [
                                        'format_type' => [
                                            'type' => 'string',
                                            'enum' => ['commons']
                                        ],
                                        'content' => ['type' => 'string']
                                    ],
                                    'required' => ['format_type', 'content'],
                                    'additionalProperties' => false
                                ]
                            ]
                        ]
                    ],
                    'required' => ['data'],
                    'additionalProperties' => false
                ],
                'strict' => true
            ]
        ];
    }

    private function filterTools($functionList, $tools)
    {
        // Initialize tool choice as 'auto' by default
        $toolChoice = 'auto';

        if (in_array('get_recommended_symbols', $functionList)) {
            // Filter tools to only include the tool with the name 'get_recommended_symbols'
            $tools = array_filter($tools, function ($tool) {
                return $tool['function']['name'] === 'get_recommended_symbols';
            });
            // Reindex the array to ensure it's not associative after filtering
            $tools = array_values($tools);
            $toolChoice = 'none';
        } elseif (in_array('get_crypto_data', $functionList)) {
            // Filter tools to include only 'get_crypto_data', 'get_symbol_price', 'get_recommendation_status'
            $tools = array_filter($tools, function ($tool) {
                return in_array($tool['function']['name'], ['get_crypto_data', 'get_symbol_price', 'get_recommendation_status']);
            });
            // Reindex the array to ensure it's not associative after filtering
            $tools = array_values($tools);
            $toolChoice = 'none';
        }

        return ['tools' => $tools, 'toolChoice' => $toolChoice];
    }

    private function sendMessageToOpenAI($messages, $tools, $userId, $functionList)
    {
        try {
            // Get the response format based on the functionList
            $responseFormat = $this->getResponseFormat();
            // Filter tools and get the appropriate tool choice
//            $filteredTools = $this->filterTools($functionList, $tools);
//            $tools = $filteredTools['tools'];
//            $toolChoice = $filteredTools['toolChoice'];
            // Determine the tool choice based on the functionList
            $toolChoice = 'auto'; // Default tool choice
//            if (in_array('analyze_crypto', $functionList) || in_array('get_recommended_symbols', $functionList)) {
//                $toolChoice = 'none'; // Set tool choice to 'none' if either function is in the list
//            }

            $response = OpenAI::chat()->create([
//                'model' => 'gpt-4o-2024-08-06',
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
                'tools' => $tools,
                'tool_choice' => $toolChoice,
//                'response_format' => ['type' => 'json_object'],
                'response_format' => $responseFormat,
                'parallel_tool_calls' => false
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
            //  Log::info('response message: ', ["responseMessage" => $responseMessage]);
            // Recurse logic
            $toolCalls = $responseMessage['tool_calls'] ?? [];
            if (empty($toolCalls)) { // Return final response
                Log::info("functionList(plain)", ["functionList" => $functionList]);
                Log::info('response message: ', ["message" => $responseMessage]);
                return [
                    'responseText' => $responseMessage['content']
                ];
            } elseif (count($functionList) > 20) { // Stop recursion if functionList length exceeds 12
                Log::warning('Function list length exceeded 12, stopping recursion.');
                return [
                    'responseMessage' => $responseMessage
                ];
            } else { // Continue recursion
                $availableFunctions = [
                    'analyze_crypto' => [$this->cryptoService, 'analyzeCrypto'],
                    'get_symbol_price' => [$this->cryptoService, 'getLatestPrice'],
                    'get_crypto_data' => [$this->cryptoService, 'getCryptoData'],
                     // 'get_crypto_data_in_time_range' => [$this->cryptoService, 'getCryptoDataInTimeRange'],
                    'get_current_time' => [$this->cryptoService, 'getCurrentTime'],
                    'get_recommended_symbols' => [$this->cryptoService, 'getRecommendation'],
                    'get_recommendation_status' => [$this->cryptoService, 'checkRecommendationStatus']
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
                Log::info("functionList(recurse): ", ["functionList" => $functionList]);
                return $this->sendMessageToOpenAI($messages, $tools, $userId, $functionList);
            }
        } catch (\Exception $e) {
            Log::error('Error Communicating with OpenAI: ', ['error' => $e->getMessage()]);
            $this->tokenService->setCostToZero($userId);
            return ['error' => 'Error: ', $e->getMessage()];
        }
    }





}
