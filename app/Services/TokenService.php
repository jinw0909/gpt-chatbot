<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TokenService
{
    private static $totalCost = [];
    private static $maxToken = [];

    public function initializeSession($userId, $token) {
        // Ensure $userId is consistently treated as a string
        $userId = (string) $userId;

        // Initialize or reset totalCost and maxToken for the given user
        self::$totalCost[$userId] = 0;
        self::$maxToken[$userId] = $token ?? 0;

        // Log the initialized values for debugging
        Log::info("Initialized Usage: ", [
            "userId" => $userId,
            "totalCost" => self::$totalCost[$userId],
            "maxTokenUsage" => self::$maxToken[$userId]
        ]);
    }

    public function exceedsMaxLimit($userId, $limit) {
        return self::$maxToken[$userId] > $limit;
    }

    public function calculateTokenCost($inputTokens, $outputTokens) {

        $inputTokenPrice = 5.00 / 1000000; //US$5.00 / 1M input tokens
        $outputTokenPrice = 15.00 / 1000000; //US$15.00 / 1M output tokens

        $inputCost = $inputTokens * $inputTokenPrice;
        $outputCost = $outputTokens * $outputTokenPrice;
        return $inputCost + $outputCost;
    }

    public function setCostToZero($userId) {
        self::$totalCost[$userId] = 0;
    }

    public function addCost($userId, $cost) {
        self::$totalCost[$userId] += $cost;
        Log::info("total cost before recurse: ", ["totalCost" => self::$totalCost[$userId]]);
    }

    public function setMaxToken($userId, $token) {
        if (!isset(self::$maxToken[$userId])) {
            self::$maxToken[$userId] = 0;
        }
        self::$maxToken[$userId] = max(self::$maxToken[$userId], $token);

        Log::info("maximum token usage before recurse: ", ["maximumTokenUsage" => self::$maxToken[$userId]]);

    }

    public function getMaxUsage($userId) {
        return self::$maxToken[$userId];
    }

    public function reduceCharge($userId) {
        $user = User::find($userId);
        if ($user) {
            $costToReduce = self::$totalCost[$userId];
            if ($user->charge >= $costToReduce) {
                $user->charge -= $costToReduce;
                $user->save();

                Log::info('Charge reduced successfully for user ID: ' . $userId, ['left' => $user->charge, 'reduced' => $costToReduce]);
            } else {
                Log::error('Not enough charge for user ID: ' . $userId, ['error' => 400]);
            }
            return $user->charge;
        } else {
            Log::error("User not found with ID: " . $userId, ['error' => 404]);
        }
    }

//    public function reduceCharge($userId) {
//        $costToReduce = self::$totalCost[$userId];
//        $response = Http::post('https://api.retri.io/chatbot/reduce', [
//            'user_id' => $userId,
//            'amount' => $costToReduce
//        ]);
//
//        //checking the response
//        $responseData = $response->json();
//        if ($responseData['code'] === 200) {
//            return $responseData['charge'];
//        } else {
//            throw new \Exception($responseData['message']);
//        }
//    }

//    public function addCharge($userId, $amount) {
//        $response = Http::post('https://api.retri.io/chatbot/charge', [
//            'user_id' => $userId,
//            'amount' => $amount
//        ]);
//
//        $responseData = $response->json();
//        if ($responseData['code'] === 200) {
//            return "Charged " . $amount . " dollars successfully to " . $userId . ". Left: " . $responseData['charge'] . ".";
//        } else {
//            return "Error with reason: " . $responseData['message'] . ".";
//        }
//    }

    public function addCharge($userId, $amount) {
        $user = User::find($userId);
        if ($user) {
            $user->charge += $amount;
            $user->save();
            return response()->json(['message' => 'Charge added successfully.', 'after' => $user->charge]);
        } else {
            return response()->json(['error' => 'User not found'], 402);
        }
    }

    public function getChargeStatus($userId) {
        $user = User::find($userId);
        if ($user) {
            return response()->json(['charge' => $user->charge]);
        } else {
            return response()->json(['error' => 'User not found'], 402);
        }
    }

}
