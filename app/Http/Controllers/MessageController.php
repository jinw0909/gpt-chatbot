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
            ],
            [
                'role' => 'system',
                'content' => "Whenever you need to show a time in your response message (assistant message), convert the UTC time to the local time where the response's language is used at." .
                    "For example when the response is sent out in Korean, then convert the time to KST before showing. When the response is Japanese, convert the time into JST. For English, just use the UTC."
            ],
            [
                'role' => 'system',
                'content' => 'When the function call [get_recommends] has been invoked, return the result in the following JSON format. {"recommendations" : [{"symbol": "STRING", "datetime": "STRING", "image": "URL_STRING", "content": "STRING"}, ...]}'.
                    "All of the response content has be translated as the language of the user request asking for recommendation, and the datetime has to be adjusted to match the user's timezone. KST for Korean, JST for Japanese and UTC for English.".
                    "WHen the user doesnt specify a specific limit and says several or some, use limit 3 as a default parameter value"
            ],
            [
                'role' => 'system',
                'content' => 'When the user asks to narrow down the recommended list or asks for further explanation on the recommend list, elaborate the content and return as a response.'.
                    ' This elaboration should reference the current price and score data of the cryptocurrency using function call [get_crypto_price]'.
                    'The response should follow the following json format. {"elaborations" : [{"symbol": "STRING", "datetime": "STRING", "image": "URL_STRING", "price_trend" : "STRING", "score_trend" : "STRING", "content": "STRING"}, ...]}'.
                    "[symbol] [datetime] [image] use the data from the recommend list. [price_trend] [score_trend] trend analysis of the symbol cryptocurrency's recent price and score movement, newly created".
                    '[content] elaboration with details regarding price and score trends applied, newly created'.
                    'Lastly always invoke the function call [elaborate_recommends] before making the response.'
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
                'content' => 'The score returned from the function call [get_crypto_price] is called a Goya score　(ゴヤースコア in Japanese, 고야 스코어 in Korean) and is n indicator to predict the future price of that cryptocurrency.'.
                    'When the score is on a downward trend, the price of the cryptocurrency is likely go down and when the score is on a upward trend the actual price of the cryptocurrency is likely to go up.'.
                    'This score is derived by analyzing the blockchain transaction data focusing on the movements that has positive or negative impacts on the price of the cryptocurrency'
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
                    'description' => 'Get the price and score data of a certain cryptocurrency between specified times given the symbol, start_time, and end_time. The score data can be utilized to predict the future price trend of the cryptocurrency',
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
//            [
//                'type' => 'function',
//                'function' => [
//                    'name' => 'subtract_hours_from_time',
//                    'description' => 'Subtract a number of hours from a given time',
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
                    'name' => 'get_recommends',
                    'description' => "Get the data of recommended cryptocurrencies for purchasing. The limit of the recommendation defaults to 3 when no specific limit is mentions from the user's message."
                                    . "Returns a JSON-encoded array of recommended cryptocurrencies. datetime is applied a UTC timezone.",
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
            [
                'type' => 'function',
                'function' => [
                    'name' => 'elaborate_recommends',
                    'description' => "Called when the user asks for elaboration on the recommended cryptocurrency list"
                ]
            ]
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
        foreach ($functionList as $functionName) {
            if ($functionName == 'get_recommends') {
                $type = $functionName;
                $format = 'json_object'; //json until end
            } else if ($functionName == 'elaborate_recommends') {
                $type = $functionName;
                $format = 'json_object';
            }
        }

        Log::info("messages: ", ["messages" => $messages]);
        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o',
                'messages' => $messages,
                'tools' => $tools,
                'tool_choice' => 'auto',
                'response_format' => ["type" => $format],
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
                    'get_recent_price' => [$this, 'getRecentPrice'],
                    'get_crypto_price' => [$this, 'getCryptoPrice'],
                    'get_current_time' => [$this, 'getCurrentUTCTime'],
//                    'subtract_hours_from_time' => [$this, 'subtractHoursFromTime'],
                    'get_recommends' => [$this, 'getRecommends'],
                    'elaborate_recommends' => [$this, 'elaborateRecommends']
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
        // Capitalize the symbol
        $symbol = strtoupper($symbol);

        // Ensure the symbol ends with 'USDT'
        if (!str_ends_with($symbol, 'USDT')) {
            $symbol .= 'USDT';
        }

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

    private function elaborateRecommends() {
        return "elaborate_recommends called";
    }

//    private function elaborate($info, $userId) {
//        $messages = [
//            [
//                'role' => 'system',
//                'content' => 'You will be provided with the information that represents the recommended cryptocurrency.' .
//                            'The [symbol] is the symbol of the cryptocurrency, and the [content] is the reason for its recommendation and this analyis is based on the [datetime] of the information.'
//            ],
//            [
//              'role' => 'system',
//              'content' => $info
//            ],
//            [
//                'role' => 'user',
//                'content' => 'From the provided information, return the response in a JSON format. The response should include the elaborated explanation on why this particular symbol has been recommended.' .
//                            'When generating the elaborated explanation, you should check the recent price and the goya score trend of this cryptocurrency within the past 24 hours.'
//            ]
//        ];
//        $tools = [
//            [
//                'type' => 'function',
//                'function' => [
//                    'name' => 'get_price_score',
//                    'description' => "Get the price and score data of a particular cryptocurrency within the past 24 hours given its symbol.",
//                    'parameters' => [
//                        'type' => 'object',
//                        'properties' => [
//                            'symbol' => [
//                                'type' => 'string',
//                                'description' => "The symbol of the cryptocurrency to check its price and score. If the symbol doesnt end with the word USDT, then attach the word when passing to the function"
//                            ]
//                        ],
//                        'required' => ['symbol']
//                    ]
//                ]
//            ],
//        ];
//
//        try {
//            $response = OpenAI::chat()->create([
//                'model' => 'gpt-4o',
//                'messages' => $messages,
//                'tools' => $tools,
//                'tool_choice' => 'auto',
//                'response_format' => ["type" => 'json_object']
//            ]);
//            Log:info("response: ", ["response" => $response]);
//
//            $responseMessage = $response['choices'][0]['message'];
//            $promptToken = $response['usage']['prompt_tokens'];
//            $completionToken = $response['usage']['completion_tokens'];
//            $responseCost = $this->calculateTokenCost($promptToken, $completionToken);
//            $responseToken = $response['usage']['total_tokens'];
//
//            self::$totalCost[$userId] += $responseCost;
//            // Update the totalToken to be the maximum of its current value and the responseToken
//            if (!isset(self::$maxToken[$userId])) {
//                self::$maxToken[$userId] = 0;
//            }
//            self::$maxToken[$userId] = max(self::$maxToken[$userId], $responseToken);
//
//            Log::info("total cost: ", ["totalCost" => self::$totalCost[$userId]]);
//            Log::info("maximum token: ", ["maximumToken" => self::$maxToken[$userId]]);
//
//            //append the message
//            $messages[] = $responseMessage;
//            $toolCalls = $responseMessage['tool_calls'] ?? [];
//            if (empty($toolCalls)) {
//                //return
//                Log::info('response content: ', ["responseContent" => $responseMessage['content']]);
//                return [
//                    'responseText' => $responseMessage['content']
//                ];
//            } else {
//                //recurse
//                Log::info("toolCalls: ", ["toolCalls" => $toolCalls]);
//                $availableFunctions = [
//                    'get_price_score' => [$this, 'getCryptoPrice'],
//                    'get_current_time' => [$this, 'getCurrentUTCTime']
//                ];
//
//                foreach ($toolCalls as $toolCall) {
//                    $functionName = $toolCall['function']['name'] ?? null; // Use null coalescing to handle missing keys
//                    if ($functionName) {
//                        Log::info("functionName:  ", ["functionName" => $functionName]);
//                        //$functionList[] = $functionName; // Append to functionList
//                        $functionToCall = $availableFunctions[$functionName];
//                        $functionArgs = json_decode($toolCall['function']['arguments'] ?? '{}', true);
//                        Log::info("functionArgs: ", ["functionArgs" => $functionArgs]);
//                        $functionResponse = call_user_func($functionToCall, ...array_values($functionArgs));
//
//                        $callResponse = [
//                            'tool_call_id' => $toolCall['id'],
//                            'role' => 'tool',
//                            'name' => $functionName,
//                            'content' => $functionResponse
//                        ];
//
//                        $messages[] = $callResponse;
//                    } else {
//                        Log::warning("Function name is null in toolCall: ", ["toolCall" => $toolCall]);
//                    }
//
//                }
//                return $this->elaborate($info, $userId);
//            }
//
//
//
//
//
//
//        } catch ( \Exception $e) {
//            Log::error('Error communicating with OpenAI:', ['error' => $e->getMessage()]);
//            //If error set the totalCost of the user to 0
//            self::$totalCost[$userId] = 0;
//            return ['error' => 'Error: ' . $e->getMessage()];
//        }
//    }

    private function calculateTokenCost($inputTokens, $outputTokens) {
        $inputTokenPrice = 5.00 / 1000000; //US$5.00 / 1M input tokens
        $outputTokenPrice = 15.00 / 1000000; //US$15.00 / 1M output tokens

        $inputCost = $inputTokens * $inputTokenPrice;
        $outputCost = $outputTokens * $outputTokenPrice;

        return $inputCost + $outputCost;
    }
    private function getRecommends($limit) {
        $totalResults = collect(); // Initialize an empty collection to store results
        $initialQueryLimit = $limit * 2; // Query more rows initially to ensure enough rows after filtering
        $offset = 0; // Offset for pagination

        while ($totalResults->count() < $limit) {
            // Query more rows initially
            $initialResults = DB::connection('mysql2')->table('beuliping')
                ->join('vm_beuliping_EN', 'beuliping.id', '=', 'vm_beuliping_EN.m_id') // Adjust join condition
                ->orderBy('beuliping.id', 'desc')
                ->offset($offset)
                ->limit($initialQueryLimit)
                ->select('beuliping.id','beuliping.symbol','beuliping.datetime','beuliping.images' ,'vm_beuliping_EN.content', DB::raw('DATE_SUB(beuliping.datetime, INTERVAL 9 HOUR) as datetime'))
                ->get();

            // Filter out rows with symbol '1000BONK' and content starting with 'No'
            $filteredResults = $initialResults->filter(function($item) {
                return $item->symbol !== '1000BONK' && !str_starts_with($item->content, 'No') && !is_null($item->images);
            });

            // Add filtered results to the total results collection
            $totalResults = $totalResults->merge($filteredResults);

            // Check if no more rows are available
            if ($initialResults->count() < $initialQueryLimit) {
                break;
            }

            // Increase offset for next query
            $offset += $initialQueryLimit;
        }

        // Return only the required number of rows
        return json_encode($totalResults->take($limit)->values());
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
