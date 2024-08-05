<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;

use App\Models\User;
use DateInterval;
use DateTime;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Log;
use function MongoDB\BSON\toJSON;

class MessageController extends Controller
{
    private static $totalCost = [];
    private static $maxToken = [];

//    public function processMessage(Request $request)
//    {
//        //Validate the input
//        $request->validate([
//           'message' => 'required|string',
//        ]);
//
//        $message = $request->input('message');
//        $userId = $request->input('userId');
//        $conversation = $request->input('conversation');
//        $wasSummarized = false;
//        $summary = null;
//        $token = $request->input('usage', 0);
//
//        //Ensure userId is a string to handle any type of userId;
//        $userId = (string) $userId;
//        Log::info("userId: ", ["userId" => $userId]);
//
//        //Initialize the user cost if not already set
//        if (!isset(self::$totalCost[$userId])) {
//            self::$totalCost[$userId] = 0;
//        }
//        if (!isset(self::$maxToken[$userId])) {
//            self::$maxToken[$userId] = $token;
//        }
//
//        self::$totalCost[$userId] = 0;
//        self::$maxToken[$userId] = $token;
//        Log::info("usage: ", ["usage" => self::$maxToken[$userId]]);
//
//        // Check if the conversation length exceeds a certain limit (e.g., 3000 tokens)
//        if (self::$maxToken[$userId] > 500) {
//
//            //Summarize the conversation
//            $conversationString = json_encode($conversation);
//
//            $summaryPrompt = [
//                [
//                    'role' => 'system',
//                    'content' => 'Please summarize the following conversation. If there is a summary included in the conversation, the content of the summary too should be included for summarization:'
//                ],
//                [
//                    'role' => 'system',
//                    'content' => $conversationString
//                ]
//            ];
//
//            $summaryResponse = OpenAI::chat()->create([
//                'model' => 'gpt-4o',
//                'messages' => $summaryPrompt,
//                'max_tokens' => 500 //Adjust as needed
//            ]);
//            $summaryResponseMessage = $summaryResponse['choices'][0]['message']['content'];
//            $summaryInput = $summaryResponse['usage']['prompt_tokens'];
//            $summaryOutput = $summaryResponse['usage']['completion_tokens'];
//
//            $summaryCost = $this->calculateTokenCost($summaryInput, $summaryOutput);
//
//            self::$totalCost[$userId] += $summaryCost;
//            Log::info("summary response: ", ['message' => $summaryResponseMessage]);
//            Log::info("summary usage", ["usage" => [$summaryInput, $summaryOutput]]);
//            Log::info("summary cost", ["cost" => $summaryCost]);
//            Log::info("total cost", ["cost" => self::$totalCost[$userId]]);
//
//            $summary =  [
//                'role' => 'system',
//                'content' => 'Summary of previous conversation: ' . $summaryResponseMessage
//            ];
//            $conversation = [$summary];
//            $wasSummarized = true;
//        }
//        // Here, you would integrate with the OpenAI API to process the message
//        // Return the response to the same view with the response text
//        $openAIResponse = $this->sendMessageToOpenAI($message, $userId, $conversation);
//        $responseContent = $openAIResponse->getData(true);
//        $usage = self::$maxToken[$userId];
//
//        return response()->json([
//           'responseText' => $responseContent['responseText'],
//           'wasSummarized' => $wasSummarized,
//           'summary' => $summary,
//            'usage' => $usage
//        ]);
//    }
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
                'model' => 'gpt-4o',
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
                'content' => 'Summary of previous conversation: ' . $summaryResponseMessage
            ];
            $conversation = [$summary];
            $wasSummarized = true;
        }

        $systems = [
            [
                'role' => 'system',
                'content' => "Map the following coin names to their symbols: bitcoin -> btcusdt, ethereum -> ethusdt, solana -> solusdt, ripple or xrp -> xrpusdt. " .
                    "When the user asks for the price of a coin, use the symbol and calculate the start_time and end_time to call the get_crypto_price function. "
            ]
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
                    'name' => 'get_crypto_price',
                    'description' => 'Get the price and score data of a certain cryptocurrency between specified times given the symbol, start_time, and end_time.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'symbol' => [
                                'type' => 'string',
                                'description' => "The symbol of the cryptocurrency (e.g., 'btcusdt')."
                            ],
                            'start_time' => [
                                'type' => 'string',
                                'description' => "The start datetime to filter the price data in ISO 8601 format (e.g., '2024-07-30T16:05:06Z')."
                            ],
                            'end_time' => [
                                'type' => 'string',
                                'description' => "The end datetime to filter the price data in ISO 8601 format (e.g., '2024-07-29T16:05:06Z')."
                            ]
                        ],
                        'required' => ['symbol', 'start_time', 'end_time']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_current_time',
                    'description' => 'Get the current time in UTC'
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_recent_price',
                    'description' => 'Get the most recent price data of a certain cryptocurrency given the symbol ',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'symbol' => [
                                'type' => 'string',
                                'description' => "The symbol of the cryptocurrency (e.g., 'btcusdt')."
                            ]
                        ],
                        'required' => ['symbol']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'subtract_hours_from_time',
                    'description' => 'Subtract a number of hours from a given time',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'time' => [
                                'type' => 'string',
                                'description' => "The time to subtract hours from in ISO 8601 format (e.g., '2024-07-30T16:05:06Z')."
                            ],
                            'hours' => [
                                'type' => 'number',
                                'description' => "The number of hours to subtract."
                            ]
                        ],
                        'required' => ['time', 'hours']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_recommends',
                    'description' => 'Get the data of recommended cryptocurrencies for purchasing',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'limit' => [
                                'type' => 'integer',
                                'description' => "The limit of the recommendation."
                            ]
                        ]
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

//    private function sendMessageToOpenAI($message, $userId, $conversation)
//    {
//        $systems = [
//            [
//                'role' => 'system',
//                'content' => "Map the following coin names to their symbols: bitcoin -> btcusdt, ethereum -> ethusdt, solana -> solusdt. " .
//                    "When the user asks for the price of a coin, use the symbol and calculate the start_time and end_time to call the get_crypto_price function. ".
//                    "Get the dollar yen ratio using the function call"
////                    "If the user specifies a time range (e.g., 'within the past 5 hours'), calculate the appropriate start_time and end_time. " .
////                    "If start_time is not specified, use the current UTC time. If end_time is not specified, use 5 hours ago from the start_time in UTC."
//            ]
//        ];
//        $messages = [
//          [
//              'role' => 'user',
//              'content' => $message
//          ]
//        ];
//
//        // Prepend the conversation array if it is not null
//        if (!is_null($conversation) && is_array($conversation)) {
//            $messages = array_merge($systems, $conversation, $messages);
//        }
//
//        Log::info("merged message: ", ["merged" => $messages]);
//
//        $tools = [
//            [
//                'type' => 'function',
//                'function' => [
//                    'name' => 'get_crypto_price',
//                    'description' => 'Get the price data of a certain cryptocurrency between specified times given the symbol, start_time, and end_time.',
//                    'parameters' => [
//                        'type' => 'object',
//                        'properties' => [
//                            'symbol' => [
//                                'type' => 'string',
//                                'description' => "The symbol of the cryptocurrency (e.g., 'btcusdt')."
//                            ],
//                            'start_time' => [
//                                'type' => 'string',
//                                'description' => "The start datetime to filter the price data."
//                            ],
//                            'end_time' => [
//                                'type' => 'string',
//                                'description' => "The end datetime to filter the price data."
//                            ]
//                        ],
//                        'required' => ['symbol']
//                    ]
//                ]
//            ],
//            [
//                'type' => 'function',
//                'function' => [
//                    'name' => 'get_current_time',
//                    'description' => 'Get the current time'
//                ]
//            ],
//            [
//              'type' => 'function',
//              'function' => [
//                  'name' => 'get_dollar_yen',
//                  'description' => 'Return the exchange rate between 1 USD and Japanese Yen'
//              ]
//            ],
//            [
//            'type' => 'function',
//            'function' => [
//                'name' => 'get_current_temperature',
//                'description' => 'Get the current temperature'
//            ]
//        ]
//        ];
//        try {
//            $response = OpenAI::chat()->create([
//                'model' => 'gpt-4o',
//                'messages' => $messages,
//                'tools' => $tools,
//                'tool_choice' => 'auto'
//            ]);
//            $initialInput = $response['usage']['prompt_tokens'];
//            $initialOutput = $response['usage']['completion_tokens'];
//
//            $responseMessage = $response['choices'][0]['message'];
//
//            $initialCost = $this->calculateTokenCost($initialInput, $initialOutput);
//            self::$totalCost[$userId] += $initialCost;
//            self::$maxToken[$userId] = ($initialInput + $initialOutput);
//            Log::info("initial usage", ["usage" => ($initialInput + $initialOutput)]);
//            Log::info("total usage", ["usage" => self::$maxToken[$userId]]);
//            Log::info("initial cost", ["cost" => $initialCost]);
//            Log::info("initial response", ["message" => $responseMessage]);
//            Log::info("total cost", ["cost" => self::$totalCost[$userId]]);
//
//            //init function call logic
//            $toolCalls = $responseMessage['tool_calls'] ?? [];
//
//            if (!empty($toolCalls)) {
//                Log::info("toolCalls: ", ["toolCalls" => $toolCalls]);
//                $availableFunctions = [
////                    'get_coin_price_day' => [$this, 'getCoinPriceDay'],
////                    'get_coin_price_week' => [$this, 'getCoinPriceWeek'],
//                    'get_crypto_price' => [$this, 'getCryptoPrice'],
//                    'get_current_time' => [$this, 'getCurrentUTCTime'],
//                    'get_current_temperature' => [$this, 'getCurrentTemperature'],
//                    'get_dollar_yen' => [$this, 'getDollarToYen']
//                ];
//
//                $messages[] = $responseMessage;
//
//                foreach ($toolCalls as $toolCall) {
//                    $functionName = $toolCall['function']['name'];
//                    Log::info("functionName:  ", ["functionName" => $functionName]);
//                    $functionToCall = $availableFunctions[$functionName];
//                    $functionArgs = json_decode($toolCall['function']['arguments'] ?? '{}', true);
//                    Log::info("functionArgs: ", ["functionArgs" => $functionArgs]);
//                    $functionResponse = call_user_func($functionToCall, $functionArgs);
//
//                    $secondMessage = [
//                        'tool_call_id' => $toolCall['id'],
//                        'role' => 'tool',
//                        'name' => $functionName,
//                        'content' => $functionResponse
//                    ];
//
//                    $messages[] = $secondMessage;
//
//                }
//
//                //Second request to OpenAI with function results
//                $secondResponse = OpenAI::chat()->create([
//                    'model' => 'gpt-4o',
//                    'messages' => $messages
//                ]);
//                $secondResponseMessage = $secondResponse['choices'][0]['message'];
//                $additionalInput = $secondResponse['usage']['prompt_tokens'];
//                $additionalOutput = $secondResponse['usage']['completion_tokens'];
//                $additionalCost = $this->calculateTokenCost($additionalInput, $additionalOutput);
//                self::$totalCost[$userId] += $additionalCost;
//                self::$maxToken[$userId] += ($additionalInput + $additionalOutput);
//                Log::info("additional response: ", ['message' => $secondResponseMessage]);
//                Log::info("additional usage", ["usage" => ($additionalInput + $additionalOutput)]);
//                Log::info("total usage", ["usage" => self::$maxToken[$userId]]);
//                Log::info("additional cost", ["cost" => $additionalCost]);
//                Log::info("total cost", ["cost" => self::$totalCost[$userId]]);
//                $this->reduceCharge($userId, self::$totalCost[$userId]);
//                return response()->json([
//                    'responseText' => $secondResponseMessage['content'],
//                ]);
//            }
//
//            $this->reduceCharge($userId, self::$totalCost[$userId]);
//            return response()->json([
//                'responseText' => $responseMessage['content']
//            ]);
//
//        } catch (\Exception $e) {
//            Log::error('Error communicating with OpenAI:', ['error' => $e->getMessage()]);
//            return response()->json(['error' => 'Error: ' . $e->getMessage()], 500);
//        }
//
//    }

    private function sendMessageToOpenAI($messages, $tools, $userId, $functionList = [], $format = 'text')
    {
        $type = "";
        //Iterate over functionList
        foreach ($functionList as $functionName) {
            $type = $functionName;
            if ($functionName == 'get_recommends') {
                $format = 'json_object'; //json until end
                $messages[] = [
                    'role' => 'user',
                    'content' => 'Return the result in the following JSON format. {"recommendations" : [{"symbol": "STRING", "datetime": "STRING", "image": "URL_STRING", "content": "STRING"}, ...]}'
                ];
            }
        }

        Log::info("messages: ", ["messages" => $messages]);
        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o',
                'messages' => $messages,
                'tools' => $tools,
                'tool_choice' => 'auto',
                'response_format' => ["type" => $format]
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
                    'get_recent_price' => [$this, 'getRecentPrice'],
                    'get_crypto_price' => [$this, 'getCryptoPrice'],
                    'get_current_time' => [$this, 'getCurrentUTCTime'],
                    'subtract_hours_from_time' => [$this, 'subtractHoursFromTime'],
                    'get_recommends' => [$this, 'getRecommends']
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

                Log::info('Charge reduced successfully for user ID: ' . $id, ['left' => $user->charge]);
            } else {
                Log::error('Not enough charge for user ID: ' . $id, ['error' => 400]);
            }
        } else {
            Log::error('User not found with ID: ' . $id, ['error' => 404]);
        }
    }

    private function getCurrentUTCTime() {
        $dateTime = new DateTime('now', new DateTimeZone('UTC'));
        Log::info("current time: ", ["time" => $dateTime->format('Y-m-d\TH:i:s\Z')]);
        return $dateTime->format('Y-m-d\TH:i:s\Z');
    }

    private function getCryptoPrice($symbol, $startTime, $endTime) {
        $data = DB::connection('mysql')->table('trsi.retri_chart_data')
            ->where('simbol', $symbol)
            ->whereBetween('regdate', [$startTime, $endTime])
            ->orderBy('idx', 'desc')
            ->get();

        return json_encode([
            'symbol' => $symbol,
            'data' => $data
        ]);
    }

    private function getRecentPrice($symbol) {
        $data = DB::connection('mysql')->table('trsi.retri_chart_data')
            ->where('simbol', $symbol)
            ->orderBy('idx', 'desc')
            ->first();

        return json_encode([
            'symbol' => $symbol,
            'data' => $data
        ]);
    }

    private function subtractHoursFromTime($time, $hours) {
        $dateTime = new DateTime($time, new DateTimeZone('UTC'));
        $dateTime->sub(new DateInterval('PT' . $hours . 'H'));
        return $dateTime->format('Y-m-d\TH:i:s\Z');
    }

    private function getDollarToYen() {
        return "80";
    }

    private function calculateTokenCost($inputTokens, $outputTokens) {
        $inputTokenPrice = 5.00 / 1000000; //US$5.00 / 1M input tokens
        $outputTokenPrice = 15.00 / 1000000; //US$15.00 / 1M output tokens

        $inputCost = $inputTokens * $inputTokenPrice;
        $outputCost = $outputTokens * $outputTokenPrice;

        return $inputCost + $outputCost;
    }
    private function getRecommends($limit) {
//        $resultRow = DB::connection('mysql2')->table('beuliping')
//            ->join('vm_beuliping_EN', 'beuliping.id', '=', 'vm_beuliping_EN.m_id') // Adjust join condition
//            ->where('beuliping.symbol', '!=', '1000BONK')
//            ->orderBy('beuliping.id', 'desc')
//            ->limit($limit)
//            ->select('beuliping.*', 'vm_beuliping_EN.content') // Ensure 'contents' is the correct column name
//            ->get();
//        return json_encode($resultRow);
        $initialQueryLimit = $limit * 2; // Query more rows initially to ensure enough rows after filtering

        // Query more rows initially
        $initialResults = DB::connection('mysql2')->table('beuliping')
            ->join('vm_beuliping_EN', 'beuliping.id', '=', 'vm_beuliping_EN.m_id') // Adjust join condition
            ->orderBy('beuliping.id', 'desc')
            ->limit($initialQueryLimit)
            ->select('beuliping.*', 'vm_beuliping_EN.content') // Ensure 'contents' is the correct column name
            ->get();

        // Filter out rows with symbol '1000BONK' and content starting with 'No'
        $filteredResults = $initialResults->filter(function($item) {
            return $item->symbol !== '1000BONK' && !str_starts_with($item->content, 'No');
        })->take($limit);

        return json_encode($filteredResults->values());
    }

    private function summarizeConversation($conversation, $userid) {

        //Convert the conversation array to a string
        $conversationString = json_encode($conversation);

        $summaryPrompt = [
            [
                'role' => 'system',
                'content' => 'Please summarize the following conversation:'
            ],
            [
                'role' => 'system',
                'content' => $conversationString
            ]
        ];

        $response = OpenAI::chat()->create([
           'model' => 'gpt-4o',
           'messages' => $summaryPrompt,
           'max_tokens' => 300 //Adjust as needed
        ]);

        $summary = $response['choices'][0]['message']['content'];

        return [
              'role' => 'system',
              'content' => 'Summary of previous conversation: ' . $summary
        ];

    }
}
