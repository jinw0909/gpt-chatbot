<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class MessageProcessingService
{
    private $tokenService;
    private $cryptoService;
    private $articleService;

    /**
     * @param $tokenService
     * @param $cryptoService
     */
    public function __construct(TokenService $tokenService, CryptoService $cryptoService, ArticleService $articleService)
    {
        $this->tokenService = $tokenService;
        $this->cryptoService = $cryptoService;
        $this->articleService = $articleService;
    }

    public function processMessage($message, $userId, $conversation, $token, $recommended, $revealed, $lang) {
        Log::info("recommended symbols: ", ["symbols" => implode(', ', $recommended)]);
        Log::info("revealed articles: ", ["articles" => implode(', ', $revealed)]);

        // Determine the timezone based on the language
        $timezone = match ($lang) {
            'kr' => 'KST',
            'jp' => 'JST',
            'en' => 'UST',
            default => 'UST' // Default to 'UST' if the language is not one of the specified
        };

        Log::info("timezone determined: ", ["timezone" => $timezone]);

        $systems = $this->getSystemMessages($recommended, $revealed, $lang, $timezone);
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
        $response = $this->sendMessageToOpenAI($messages, $tools, $userId, $lang, $timezone, $functionList = []);

        $maxUsage = $this->tokenService->getMaxUsage($userId);
        $left = $this->tokenService->reduceCharge($userId);
        return response()->json([
            'responseText' => $response['responseText'],
            'maxUsage' => $maxUsage,
            'summary' => $summary,
            'left' => $left,
            'functionCall' => $response['functionCall']
        ]);
    }

    private function getSystemMessages($recommended, $revealed, $lang, $timezone) {
        // Define system messages logic here
        $messages = [
            [
                'role' => 'system',
                'content' => 'When passing "symbols" parameter to the function "analyze_cryptos", MAKE SURE that the last letter of the symbol is not missing or altered. '
            ],
            [
                'role' => 'system',
                'content' =>
                    'Upon receiving any user inquiry related to the price and score of the crypto symbol, or upon receiving any inquiry to show the chart of the symbol, you should call the function "analyze_cryptos" and respond in the "format_type" of "crypto_analysis". The time range specified in the user message has to be calculated into hours unit before being passed as the "hours" argument. If the user did not specify the time range, then use 24 as the "hours" argument. '
            ],
            [
                'role' => 'system',
                'content' => 'When the format_type of the response is "crypto_analysis", "analysis_translated" field and the "recommended_reason_translated" field should be made in the default language of the user if the default language of the user is not English. Also, the "analysis_translated" field must include detailed analysis of the price and score movement of the symbol crypto, not just introducing the overall movement trend but also dealing with the critical points where the price and score largely fluctuated, and also refer the several most recent price movement trend of the symbol. Also, the "analysis_translated" field should also refer to the "recommended_reason_translated" content of the "recommendation_status" field which explains why the symbol is currently recommended. When the "recommended_reason_translated" says there are S, S2, or S3 signals, it means the price of the crypto is in a declining trend which could be an opportunity for the short position traders, while L, L2, L3 signal means the price in on a inclining trend which means it could be an opportunity for the long position traders.'
            ],
            [
                'role' => 'system',
                'content' => 'When you make the function call "analyze_cryptos", but the returned crypto_data (price and score data) is an empty array, then the generated response should be in a format_type of "default" and include the text that informs there are no score and price data of the symbol but soon it will be updated and apologizes for the inconvenience.'
            ],
            [
                'role' => 'system',
                'content' => 'Upon receiving request from the user to recommend cryptocurrencies, or to recommend some more or other cryptocurrencies, call the function "recommend_cryptos" and return the response in the format_type of "crypto_recommendations". If the user did not specify the limit, pass 3 as the "limit" argument. If there are no more cryptos to recommend, respond with format_type of "default". '
            ],
//            [
//                'role' => 'system',
//                'content' => 'When your response "format_type" is "crypto_recommendations", the "recommended_reason_translated" should be in the language of the user and should include the information about the signals. Also the "symbol" value should be capitalized.'
//            ],
            [
                'role' => 'system',
                'content' => 'When your response "format_type" is "crypto_recommendations", the "symbol" value should be capitalized. Also the "recommended_reason_translated" content should always be translated into the local language of the user.'
            ],
            [
                'role' => 'system',
                'content' => 'When the user asks to pick symbols from the previous recommendation list, first pick symbols from the previous list and call the function "analyze_cryptos". Pass the symbols you picked as an argument. The response format should be the format_type of "crypto_analysis". If the user did not specify the number of symbols to pick from the previous list, then just pick one symbol from the previous list. If there is no data retrieved, respond in a format_type of "default".  '
            ],
            [
                'role' => 'system',
                'content' => 'When the user asks to tell about the cryptocurrency symbol, or asks to explain about the cryptocurrency symbol, then respond in the format_type of "default". In this case, the response content should focus on explaining about the cryptocurrency symbol itself.'
            ],
            [
                'role' => 'system',
                'content' => 'Upon receiving inquires related to the cryptocurrency market trend, call the function "show_viewpoint" and respond with a format_type of "viewpoint". The "id" MUST exactly match the retrieved viewpoint id.'
            ],
            [
                'role' => 'system',
                'content' => "When the user asks for crypto related articles or additional/other articles, call the function 'show_articles' and respond in a format_type of 'articles'. Check the previously_shown article id from the system message and pass it as an argument. . "
            ],
            [
                'role' => 'system',
                'content' => 'The score values returned when calling the function "analyze_cryptos" is called a "Goya score". It is translated as "ゴヤースコア" in Japanese, and "고야 스코어" in Korean. Goya score is an indicator to predict the future price of the symbol cryptocurrency.'.
                    'When the Goya score is on a downward trend, the price of the cryptocurrency is likely to go down, and otherwise when the score is showing a upward trend, the actual price of the cryptocurrency is likely to go up.'.
                    'Goya score is derived from collecting and analyzing objective blockchain transaction activity data of the symbol cryptocurrency focused mainly on the movements that has positive or negative impacts on the price of the cryptocurrency. However, there are many other objective indicators from which the Goya score is derived from. '
            ],
//            [
//                'role' => 'system',
//                'content' => 'From the "recommended_reason" content, L1, L2, L3 signal means that the symbol cryptocurrency price is on a upward trend. S1, S2, S3 signal means that the price is on a downward trend for that symbol. However the S1, S2, S3 signals are not necessarily negative signals because futures short trading is also a possible choice in the field of crypto trading. When creating the analysis, this point of view may be reflected as well. '
//            ],
            [
                'role' => 'system',
                'content' =>
                    'Your default response format_type is "default" when there is no specific instruction on the response format_type, and in this case, the content should be in a plain text format not in JSON. '
            ],
            [
                'role' => 'system',
                'content' => 'This is the list of previously recommended cryptos. ' . implode(', ', $recommended) .
                    'If the user asks to provide additional recommendations, avoid providing these cryptos. '
            ],
            [
                'role' => 'system',
                'content' => 'This is the list of articles that is already shown to the user. ' . implode(', ', $revealed) .
                    'If the user asks to provide additional articles, avoid providing articles in this list.'
            ],
            [
                'role' => 'system',
                'content' => 'The default language of the user is ' . $lang . ' and the default timezone of the user is ' . $timezone . '. '
            ],
        ];

        // Define greetings based on language
        $userGreeting = '';
        $assistantGreeting = '';

        if ($lang == 'kr') {
            $userGreeting = '안녕';
            $assistantGreeting = '안녕하세요! 어떻게 도와드릴까요?';
        } elseif ($lang == 'jp') {
            $userGreeting = 'こんにちは';
            $assistantGreeting = 'こんにちは！何かお手伝いできることがあれば教えてください。';
        } else {
            $userGreeting = 'Hello';
            $assistantGreeting = 'Hello! How can I assist you?';
        }

        // Append final user and assistant messages with greetings
        $messages[] = [
            'role' => 'user',
            'content' => $userGreeting
        ];

        $messages[] = [
            'role' => 'assistant',
            'content' => '{"data": {"format_type": "default", "content": '. $assistantGreeting . '}}'
        ];

        return $messages;
    }


    private function getTools() {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'analyze_cryptos',
                    'description' => 'The function to get the overall data of the given cryptocurrency symbol including price data, score data and the recommendation status.',
                    'strict' => true,
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'symbols' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'string',
                                    'description' => 'Each crypto symbol in the array to analyze (e.g., btc)'
                                ],
                                'description' => 'A list cryptocurrency symbols to analyze (e.g., [btc, eth, xrp, ...] ).'
                            ],
//                            'symbol' => [
//                              'type' => 'string',
//                              'description' => 'The symbol of the cryptocurrency to analyze.'
//                            ],
                            'hours' => [
                                'type' => 'number',
                                'description' => 'The number of hours specified in the user message from when the price and score data is retrieved. If you cannot infer the hours from the user message, then use 24. Every time unit (e.g., months, weeks, days) should be calculated into the hours unit. '
                            ],
                            'timezone' => [
                                'type' => 'string',
                                'description' => 'The local timezone of the user. Used when formatting the result datetime to the local timezone of the user.',
                                'enum' => ['UTC', 'JST', 'KST']
                            ]
                        ],
                        'additionalProperties' => false, // Correctly placed
                        'required' => ['symbols', 'hours', 'timezone'],
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
                    'description' => 'The function to get the current local time given the timezone. Call this function only when the user asks for the current time. ',
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
                    'name' => 'recommend_cryptos',
                    'description' => "Provides a list of recommended cryptocurrencies.",
                    'strict' => true,
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'limit' => [
                                'type' => 'number',
                                'description' => "The limit number of the recommended cryptos to show. Pass 3 if you cannot infer the limit number from the user message. "
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
                                    'description' => 'The symbol of cryptocurrency already recommended previously.'
                                ],
                                'description' => 'The list of cryptocurrency symbols that has been previously recommended. You can create this list by checking the previous conversation history and the system message. '
                            ],
//                            'coin_list' => [
//                                'type' => 'array',
//                                'items' => [
//                                    'type' => 'string',
//                                    'description' => 'The symbol of cryptocurrency already recommended previously.'
//                                ],
//                                'description' => 'The list of cryptocurrency symbols that has been previously recommended. You can freely create this list by checking the previous conversation history and the user message. '
//                            ]
                        ],
//                        'required' => ['limit', 'timezone', 'previously_recommended'],
//                        'required' => ['limit', 'timezone', 'coin_list'],
                        'required' => ['limit', 'timezone', 'already_recommended'],
                        'additionalProperties' => false
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'show_viewpoint',
                    'description' => 'This function returns the most recent viewpoint towards the current cryptocurrency market given the timezone and the language. Call this function only when the user explicitly asks for the viewpoint or the perspectives related to the crypto market. ',
                    'strict' => true,
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'timezone' => [
                                'type' => 'string',
                                'description' => 'The local timezone of the user',
                                'enum' => ['UTC', 'JST', 'KST']
                            ],
                        ],
                        'required' => ['timezone'],
                        'additionalProperties' => false
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'show_articles',
                    'description' => 'This function returns the most recent and relevant articles of the cryptocurrency market given the timezone and the limit. Call this function when the user asked for crypto related articles. If the user did not specified the limit, then pass 2.',
                    'strict' => true,
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'timezone' => [
                                'type' => 'string',
                                'description' => 'The local timezone of the user',
                                'enum' => ['UTC', 'JST', 'KST']
                            ],
                            'previously_shown' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'string',
                                    'description' => "The id of the article which is already shown to the user. "
                                ],
                                'description' => 'The array of article ids that have been already shown to the user. Search the previously shown article ids from the system message. This list is Used to prevent from showing duplicate articles to the user. '
                            ],
                            'limit' => [
                                'type' => 'number',
                                'description' => 'The number of articles to show to the user. If the user did not specify the limit, then pass 2. '
                            ]
                        ],
                        'required' => ['timezone', 'previously_shown', 'limit'],
                        'additionalProperties' => false
                    ]
                ]
            ]
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
                                    'title' => 'default Format',
                                    'description' => 'This format is used for general text content.',
                                    'type' => 'object',
                                    'properties' => [
                                        'format_type' => [
                                            'type' => 'string',
                                            'enum' => ['default']
                                        ],
                                        'content' => ['type' => 'string'],
                                        'language' => [
                                            'type' => 'string',
                                            'enum' => ['kr', 'jp', 'en'],
                                            'description' => 'The default language of the user'
                                        ]
                                    ],
                                    'required' => ['format_type', 'content', 'language'],
                                    'additionalProperties' => false
                                ],
                                [
                                    'title' => 'Crypto Analyses Format',
                                    'description' => 'This format is used for detailed crypto analyses for each given symbol',
                                    'type' => 'object',
                                    'properties' => [
                                        'format_type' => [
                                            'type' => 'string',
                                            'enum' => ['crypto_analysis']
                                        ],
                                        'content' => [
                                            'type' => 'array',
                                            'items' => [
                                                'type' => 'object',
                                                'properties' => [
                                                    'symbol' => ['type' => 'string'],
//                                                    'symbol_logo' => ['type' => 'string', 'description' => 'logo image url of the symbol'],
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
//                                                    'crypto_data' => [
//                                                        'type' => 'array',
//                                                        'items' => [
//                                                            'type' => 'object',
//                                                            'properties' => [
//                                                                'datetime' => ['type' => 'string'],
//                                                                'score' => ['type' => 'number'],
//                                                                'price' => ['type' => 'number']
//                                                            ],
//                                                            'required' => ['datetime', 'score', 'price'],
//                                                            'additionalProperties' => false
//                                                        ]
//                                                    ],
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
                                                    'timezone' => [
                                                        'type' => 'string',
                                                        'enum' => ['KST', 'JST', 'UTC']
                                                    ],
                                                    'analysis_translated' => ['type' => 'string']
                                                ],
                                                'required' => [
//                                                    'symbol', 'symbol_data', 'crypto_data', 'analysis_translated', 'recommendation_status', 'interval'
                                                    'symbol', 'symbol_data', 'analysis_translated', 'recommendation_status', 'interval', 'timezone'
                                                ],
                                                'additionalProperties' => false
                                            ]
                                        ],
                                        'language' => [
                                            'type' => 'string',
                                            'enum' => ['kr', 'jp', 'en'],
                                            'description' => 'The default language of the user'
                                        ]
                                    ],
                                    'required' => ['format_type', 'content', 'language'],
                                    'additionalProperties' => false
                                ],
//                                [
//                                    'title' => 'Crypto Analyses Format',
//                                    'description' => 'This format is used for detailed crypto analyses',
//                                    'type' => 'object',
//                                    'properties' => [
//                                        'format_type' => [
//                                            'type' => 'string',
//                                            'enum' => ['crypto_analysis']
//                                        ],
//                                        'content' => [
//                                            'type' => 'array',
//                                            'items' => [
//                                                'type' => 'object',
//                                                'properties' => [
//                                                    'symbol' => ['type' => 'string'],
//                                                    'symbol_data' => [
//                                                        'type' => 'object',
//                                                        'properties' => [
//                                                            'symbol_price' => ['type' => 'number'],
//                                                            'record_time' => ['type' => 'string'],
//                                                            'time_gap' => [
//                                                                'type' => 'object',
//                                                                'properties' => [
//                                                                    'hours' => ['type' => 'number'],
//                                                                    'minutes' => ['type' => 'number']
//                                                                ],
//                                                                'required' => ['hours', 'minutes'],
//                                                                'additionalProperties' => false
//                                                            ],
//                                                        ],
//                                                        'required' => ['symbol_price', 'record_time', 'time_gap'],
//                                                        'additionalProperties' => false
//                                                    ],
////                                                    'crypto_data' => [
////                                                        'type' => 'array',
////                                                        'items' => [
////                                                            'type' => 'object',
////                                                            'properties' => [
////                                                                'datetime' => ['type' => 'string'],
////                                                                'score' => ['type' => 'number'],
////                                                                'price' => ['type' => 'number']
////                                                            ],
////                                                            'required' => ['datetime', 'score', 'price'],
////                                                            'additionalProperties' => false
////                                                        ]
////                                                    ],
//                                                    "recommendation_status" => [
//                                                        'type' => 'object',
//                                                        'properties' => [
//                                                            'is_recommended' => ['type' => 'boolean'],
//                                                            'recommended_datetime' => ['type' => 'string'],
//                                                            'recommended_reason' => ['type' => 'string'],
//                                                            'image_url' => ['type' => 'string'],
//                                                            'time_gap' => [
//                                                                'type' => 'object',
//                                                                'properties' => [
//                                                                    'hours' => ['type' => 'number'],
//                                                                    'minutes' => ['type' => 'number']
//                                                                ],
//                                                                'required' => ['hours', 'minutes'],
//                                                                'additionalProperties' => false
//                                                            ],
//                                                        ],
//                                                        'required' => ['is_recommended', 'recommended_datetime', 'recommended_reason', 'image_url', 'time_gap'],
//                                                        'additionalProperties' => false
//                                                    ],
//                                                    'interval' => ['type' => 'number'],
//                                                    'timezone' => [
//                                                        'type' => 'string',
//                                                        'enum' => ['KST', 'JST', 'UTC']
//                                                    ],
//                                                    'analysis_translated' => ['type' => 'string']
//                                                ],
//                                                'required' => [
////                                                    'symbol', 'symbol_data', 'crypto_data', 'analysis_translated', 'recommendation_status', 'interval'
//                                                    'symbol', 'symbol_data', 'analysis_translated', 'recommendation_status', 'interval', 'timezone'
//                                                ],
//                                                'additionalProperties' => false
//                                            ]
//                                        ]
//                                    ],
//                                    'required' => ['format_type', 'content'],
//                                    'additionalProperties' => false
//                                ],
                                [
                                    'title' => 'Crypto Recommendations Format',
                                    'description' => 'This format is used to recommend cryptos to the user. ',
                                    'type' => 'object',
                                    'properties' => [
                                        'format_type' => [
                                            'type' => 'string',
                                            'enum' => ['crypto_recommendations']
                                        ],
                                        'content' => [
                                            'type' => 'array',
                                            'items' => [
                                                'type' => 'object',
                                                'properties' => [
                                                    'symbol' => ['type' => 'string'],
//                                                    'symbol_logo' => ['type' => 'string', 'description' => 'image url of the symbol logo'],
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
                                                    'recommended_reason_translated' => ['type' => 'string'],
                                                ],
                                                'required' => ['symbol', 'datetime', 'time_gap', 'image_url', 'recommended_reason_translated'],
                                                'additionalProperties' => false
                                            ]
                                        ],
                                        'language' => [
                                            'type' => 'string',
                                            'enum' => ['kr', 'jp', 'en'],
                                            'description' => 'The default language of the user'
                                        ]
                                    ],
                                    'required' => ['format_type', 'content', 'language'],
                                    'additionalProperties' => false
                                ],
//                                [
//                                    'title' => 'Crypto Recommendations Format',
//                                    'description' => 'This format is used to recommend cryptos to the user. ',
//                                    'type' => 'object',
//                                    'properties' => [
//                                        'format_type' => [
//                                            'type' => 'string',
//                                            'enum' => ['crypto_recommendations']
//                                        ],
//                                        'content' => [
//                                            'type' => 'array',
//                                            'items' => [
//                                                'type' => 'object',
//                                                'properties' => [
//                                                    'id' => ['type' => 'number'],
//                                                    'symbol' => ['type' => 'string'],
//                                                    'datetime' => ['type' => 'string'],
//                                                    'time_gap' => [
//                                                        'type' => 'object',
//                                                        'properties' => [
//                                                            'hours' => ['type' => 'integer'],
//                                                            'minutes' => ['type' => 'integer']
//                                                        ],
//                                                        'required' => ['hours', 'minutes'],
//                                                        'additionalProperties' => false
//                                                    ],
//                                                    'image_url' => ['type' => 'string'],
////                                                    'recommended_reason' => ['type' => 'string'],
//                                                    'language' => [
//                                                        'type' => 'string',
//                                                        'enum' => ['kr', 'jp', 'en']
//                                                    ]
//                                                ],
////                                                'required' => ['symbol', 'datetime', 'time_gap', 'image_url', 'recommended_reason'],
//                                                'required' => ['id', 'symbol', 'datetime', 'time_gap', 'image_url', 'language'],
//                                                'additionalProperties' => false
//                                            ]
//                                        ]
//                                    ],
//                                    'required' => ['format_type', 'content'],
//                                    'additionalProperties' => false
//                                ],
                                [
                                    'title' => 'Crypto Articles Format',
                                    'description' => 'Use this format to present crypto related articles to the user.',
                                    'type' => 'object',
                                    'properties' => [
                                        'format_type' => [
                                            'type' => 'string',
                                            'enum' => ['articles']
                                        ],
                                        'content' => [
                                            'type' => 'array',
                                            'items' => [
                                                'type' => 'object',
                                                'properties' => [
                                                    'title' => ['type' => 'string'],
                                                    'datetime' => ['type' => 'string'],
                                                    'time_gap' => [
                                                        'type' => 'object',
                                                        'properties' => [
                                                            'hours' => ['type' => 'number'],
                                                            'minutes' => ['type' => 'number'],
                                                        ],
                                                        'required' => ['hours', 'minutes'],
                                                        'additionalProperties' => false
                                                    ],
                                                    'image_url' => ['type' => 'string'],
                                                    'content' => ['type' => 'string'],
                                                    'summary' => ['type' => 'string'],
                                                    'article' => ['type' => 'string'],
                                                    'id' => ['type' => 'number'],
                                                    'language' => [
                                                        'type' => 'string',
                                                        'enum' => ['kr', 'jp', 'en']
                                                    ]
                                                ],
                                                'required' => ['title', 'datetime', 'time_gap', 'image_url', 'content', 'summary', 'article', 'id', 'language'],
                                                'additionalProperties' => false
                                            ],
                                        ],
                                        'language' => [
                                            'type' => 'string',
                                            'enum' => ['kr', 'jp', 'en'],
                                            'description' => 'The default language of the user'
                                        ]
                                    ],
                                    'required' => ['format_type', 'content', 'language'],
//                                    'required' => ['format_type', 'content'],
                                    'additionalProperties' => false
                                ],
                                [
                                    'title' => 'Crypto Market Viewpoint Format',
                                    'description' => 'This format is used for crypto market viewpoints.',
                                    'type' => 'object',
                                    'properties' => [
                                        'format_type' => [
                                            'type' => 'string',
                                            'enum' => ['viewpoint']
                                        ],
                                        'content' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'id' => [
                                                    'type' => 'string',
                                                    'description' => 'The id of the market viewpoint'
                                                ],
                                                'title' => ['type' => 'string'],
                                                'datetime' => ['type' => 'string'],
                                                'time_gap' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'hours' => ['type' => 'number'],
                                                        'minutes' => ['type' => 'number'],
                                                    ],
                                                    'required' => ['hours', 'minutes'],
                                                    'additionalProperties' => false
                                                ],
                                                'image_url' => ['type' => 'string'],
                                                'content' => ['type' => 'string'],
                                                'summary' => ['type' => 'string'],
                                                'article' => ['type' => 'string'],
                                                'language' => [
                                                    'type' => 'string',
                                                    'enum' => ['kr', 'jp', 'en']
                                                ]
                                            ],
                                            'required' => ['title', 'datetime', 'time_gap', 'image_url', 'content', 'summary', 'article', 'id', 'language'],
                                            'additionalProperties' => false
                                        ],
                                        'language' => [
                                            'type' => 'string',
                                            'enum' => ['kr', 'jp', 'en'],
                                            'description' => 'The default language of the user'
                                        ]
                                    ],
                                    'required' => ['format_type', 'content', 'language'],
                                    'additionalProperties' => false
                                ],
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

        if (in_array('recommend_cryptos', $functionList)) {
            // Filter tools to only include the tool with the name 'recommend_cryptos'
            $tools = array_filter($tools, function ($tool) {
                return $tool['function']['name'] === 'recommend_cryptos';
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

    private function sendMessageToOpenAI($messages, $tools, $userId, $lang, $timezone, $functionList)
    {
        try {
            // Get the response format based on the functionList
            $responseFormat = $this->getResponseFormat();
            $functionCall = false;

            $toolChoice = 'auto';
            if (in_array('recommend_cryptos', $functionList) || in_array('show_viewpoint', $functionList)
                || in_array('show_articles', $functionList) || in_array('analyze_cryptos', $functionList)) {
                $toolChoice = 'none';
                $functionCall = true;
            }
//            if (in_array('show_viewpoint', $functionList) || in_array('show_articles', $functionList)) {
//                $toolChoice = 'none';
//            }
            $response = OpenAI::chat()->create([
//                'model' => 'gpt-4o-2024-08-06',
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
                'tools' => $tools,
                'tool_choice' => $toolChoice,
                'response_format' => $responseFormat,
                'parallel_tool_calls' => false,
                'temperature' => 0
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
            $responseText = $responseMessage['content'];

            if (empty($toolCalls)) { // Return final response
                Log::info("functionList(plain)", ["functionList" => $functionList]);
                Log::info('response message: ', ["message" => $responseMessage]);
                // Check if functionList contains 'analyze_cryptoss' or 'get_recommended_cryptos'

//                $functionCall = !empty($functionList);
                $parsedContent = json_decode($responseMessage['content'], true);
                Log::info("parsedContent: ", ["parsedContent" => $parsedContent]);

                //Viewpoint
                if (isset($parsedContent['data']['content']['id']) && $parsedContent['data']['format_type'] === 'viewpoint') { // Check if a single ID is set
                    $language = $parsedContent['data']['content']['language'] ?? 'en'; // Default to 'en' if not set
                    Log::info('language detected: ', ['language' => $language]);

                    // Determine the column to query based on the language value
                    $columnToQuery = match ($language) {
                        'jp' => 'viewpoint_jp',
                        'kr' => 'viewpoint_kr',
                        default => 'viewpoint', // Default to 'viewpoint' for 'en' or any unexpected value
                    };
                    Log::info('columnToQuery: ', ['columnToQuery' => $columnToQuery]);

                    // Get the single ID value
                    $id = $parsedContent['data']['content']['id'];
                    Log::info('id: ', ['id' => $id]);

                    // Run a query to retrieve the single row from the db using this ID
                    $row = DB::connection('mysql3')
                        ->table('bu.Viewpoints')
                        ->where('id', $id)
                        ->select('id', $columnToQuery, DB::raw('imageUrl as image_url')) // Select both viewpoint and imageUrl
                        ->first(); // Fetch a single row
                    Log::info("row: ", ["row" => $row]);

                    // Check if the row is found and append the retrieved content values to the parsed response
                    if ($row) {
                        $parsedContent['data']['content']['content'] = $row->$columnToQuery; // Retrieve the content
                        $parsedContent['data']['content']['image_url'] = $row->image_url; // Retrieve the imageUrl
                    }

                    Log::info('Modified parsedContent for viewpoint: ', ['parsedContent' => $parsedContent]);

                    // Encode the modified content back to JSON
                    $responseText = json_encode($parsedContent);
                }
                if (isset($parsedContent['data']['content']) && $parsedContent['data']['format_type'] === 'articles') {
                    $firstContentItem = reset($parsedContent['data']['content']); // Get the first element of the content array
                    $language = $firstContentItem['language'] ?? 'en'; // Default to 'en' if the language key is not set
                    Log::info('language detected: ', ['language' => $language]);

                    // Determine the columns to query based on the language value
                    $columnsToQuery = match ($language) {
                        'jp' => [
                            'id',
                            DB::raw('imageUrl as image_url'),
                            DB::raw('analysis_jp as content'), // Alias 'analysis_jp' as 'content_jp'
                            DB::raw('content_jp as article'),  // Alias 'content_jp' as 'article_jp'
                            DB::raw('title_jp as title'),  // Alias 'content_jp' as 'article_jp'
                            DB::raw('summary_jp as summary')
                        ],
                        'kr' => [
                            'id',
                            DB::raw('imageUrl as image_url'),
                            DB::raw('analysis_kr as content'), // Alias 'analysis_kr' as 'content_kr'
                            DB::raw('content_kr as article'),  // Alias 'content_kr' as 'article_kr'
                            DB::raw('title_kr as title'),  // Alias 'content_kr' as 'article_kr'
                            DB::raw('summary_kr as summary')
                        ],
                        default => [
                            'id',
                            DB::raw('imageUrl as image_url'),
                            DB::raw('analysis as content'),       // Alias 'analysis' as 'content'
                            DB::raw('content as article'),        // Alias 'content' as 'article'
                            'title',
                            'summary'
                        ], // Default columns for 'en' or any unexpected value
                    };

                    // Extract all 'id' values from the parsed content
                    $ids = array_column($parsedContent['data']['content'], 'id');
                    Log::info('ids: ', ['ids' => $ids]);

                    // Run a query to retrieve rows from the db using these ids
                    $rows = DB::connection('mysql3')
                        ->table('bu.Translations')
                        ->whereIn('id', $ids)
                        ->select($columnsToQuery) // Select id, imageUrl, and language-specific columns
                        ->get()
                        ->keyBy('id'); // Key the collection by 'id' for easy lookup
                    Log::info("rows: ", ["rows" => $rows]);

                    // Append the retrieved content values to the parsed response
                    // Append the retrieved content values to the parsed response
                    foreach ($parsedContent['data']['content'] as &$item) {
                        if (isset($item['id']) && isset($rows[$item['id']])) {
                            $item['content'] = $rows[$item['id']]->content ?? ''; // Retrieve the aliased content
                            $item['article'] = $rows[$item['id']]->article ?? ''; // Retrieve the aliased article
                            $item['summary'] = $rows[$item['id']]->summary ?? ''; // Retrieve the summary
                            $item['title'] = $rows[$item['id']]->title ?? ''; // Retrieve the title
                            $item['image_url'] = $rows[$item['id']]->image_url ?? ''; // Retrieve the imageUrl
                        }
                    }
                    unset($item); // Unset reference to avoid potential side effects
                    Log::info('Modified parsedContent for articles: ', ['modified parsedContent for articles' => $parsedContent]);

                    // Encode the modified content back to JSON
                    $responseText = json_encode($parsedContent);

                }
                if (isset($parsedContent['data']['content']) && is_array($parsedContent['data']['content']) && $parsedContent['data']['format_type'] === 'crypto_analysis') {
                    Log::info("crypto analysis format");
                    // Loop through each element of the content array
                    foreach ($parsedContent['data']['content'] as &$contentItem) {
                        if (isset($contentItem['symbol'])) {
                            // Extract symbol
                            $symbol = $contentItem['symbol'];
                            $interval = $contentItem['interval'] ?? 24; // Default to 24 if not provided
                            $timezone = $contentItem['timezone'] ?? 'UTC';
                            // Get the new crypto data for the current symbol
                            $cryptoData = $this->cryptoService->getCryptoData($symbol, $interval, $timezone);
                            $symbolLogo = $this->cryptoService->getCryptoLogo($symbol);
//                            $recommendationStatus = $this->cryptoService->checkRecommendationStatus($symbol, $timezone);
                            // Ensure $cryptoData is decoded if it's a JSON string
                            if (is_string($cryptoData)) {
                                $cryptoData = json_decode($cryptoData, true); // Decode the JSON string into an array
                            }
//                            if (is_string($recommendationStatus)) {
//                                $recommendationStatus = json_decode($recommendationStatus, true); // Decode the JSON string into an array
//                            }
                            if (!isset($contentItem['crypto_data'])) {
                                $contentItem['crypto_data'] = []; // Initialize an empty array or set to null if preferred
                            }
//                            if (!isset($contentItem['recommendation_status'])) {
//                                $contentItem['recommendation_status'] = []; // Initialize an empty array or set to null if preferred
//                            }

                            // Replace the original cryptoData with the newly retrieved value
                            $contentItem['crypto_data'] = $cryptoData;
                            $contentItem['symbol_logo'] = $symbolLogo;
//                            $contentItem['recommendation_status'] = $recommendationStatus;
                        }
                    }

                    // Encode the modified parsed content into a JSON string
                    $responseText = json_encode($parsedContent);
                    Log::info("Modified responseText: " . $responseText);
                }
                if (isset($parsedContent['data']['content']) && is_array($parsedContent['data']['content']) && $parsedContent['data']['format_type'] === 'crypto_recommendations') {
                    Log::info("crypto recommendations format");
                    foreach ($parsedContent['data']['content'] as &$contentItem) {
//                        if (isset($contentItem['id']) && isset($contentItem['language'])) {
//                            // Extract symbol
//                            $id = $contentItem['id'];
//                            $language = $contentItem['language'];
//                            $recommended_reason = $this->cryptoService->getRecommendationDetail($id, $language);
//                            Log::info("recommended reason: ", ["recommended_reason" => $recommended_reason]);
//
//                            // Check if recommended_reason is a string (not JSON) and handle accordingly
//                            if (is_string($recommended_reason)) {
//                                // No need to decode if it's just a plain string
//                                $contentItem['recommended_reason'] = $recommended_reason;
//                            } else {
//                                // Handle any case where recommended_reason is not a string (unlikely in this context)
//                                $contentItem['recommended_reason'] = $recommended_reason ?? [];
//                            }
//
//                            if (!isset($contentItem['recommended_reason'])) {
//                                $contentItem['recommended_reason'] = []; // Initialize an empty array or set to null if preferred
//                            }
//                            // Replace the original cryptoData with the newly retrieved value
//                            if ($recommended_reason !== null) {
//                                $contentItem['recommended_reason'] = $recommended_reason;
//                            }
//                        }
                        $symbol = $contentItem['symbol'];
                        $symbolLogo = $this->cryptoService->getCryptoLogo($symbol);
                        $contentItem['symbol_logo'] = $symbolLogo;
                    }

                    $responseText = json_encode($parsedContent);
                    Log::info("Modified responseText: " . $responseText);
                }

                if (!$functionCall) {
                    $this->tokenService->setCostToZero($userId);
                }

                return [
                    'responseText' => $responseText,
                    'functionCall' => $functionCall
                ];

            } elseif (count($functionList) > 10) { // Stop recursion if functionList length exceeds 12
                Log::warning('Function list length exceeded 12, stopping recursion.');
                $this->tokenService->setCostToZero($userId);
                return [
                    'responseMessage' => $responseMessage
                ];
            } else { // Continue recursion
                $availableFunctions = [
                    'analyze_cryptos' => [$this->cryptoService, 'analyzeCrypto'],
//                    'get_symbol_price' => [$this->cryptoService, 'getLatestPrice'],
                    'get_crypto_data' => [$this->cryptoService, 'getCryptoData'],
                     // 'get_crypto_data_in_time_range' => [$this->cryptoService, 'getCryptoDataInTimeRange'],
                    'get_current_time' => [$this->cryptoService, 'getCurrentTime'],
                    'recommend_cryptos' => [$this->cryptoService, 'getRecommendation'],
//                    'recommend_cryptos' => [$this->cryptoService, 'getRecommendationData'],
//                    'get_recommendation_status' => [$this->cryptoService, 'checkRecommendationStatus'],
                    'show_viewpoint' => [$this->articleService, 'getViewpoint'],
                    'show_articles' => [$this->articleService, 'getArticles']
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
                $messages[] = [
                    'role' => 'system',
                    'content' => 'The default language of the user is ' . $lang . ' and the default timezone of the user is ' . $timezone . '. '
                ];
                Log::info("functionList(recurse): ", ["functionList" => $functionList]);
                return $this->sendMessageToOpenAI($messages, $tools, $userId, $lang, $timezone,$functionList);
            }
        } catch (\Exception $e) {
            Log::error('Error Communicating with OpenAI: ', ['error' => $e->getMessage()]);
            $this->tokenService->setCostToZero($userId);
            return ['error' => 'Error: ', $e->getMessage()];
        }
    }

}
