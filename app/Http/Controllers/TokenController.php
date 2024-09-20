<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TokenController extends Controller
{
    private $TokenService;

    /**
     * @param $TokenService
     */
    public function __construct(TokenService $TokenService)
    {
        $this->TokenService = $TokenService;
    }

    public function addCharge($id, Request $request) {

        $amount = $request->input('amount', 0);
        return $this->TokenService->addCharge($id, $amount);

    }

//    public function addCharge($id, Request $request) {
//
//        Log::info('Request received', $request->all());
//
//        $user = User::find($id);
//        if ($user) {
//            $chargeToAdd = $request->input('amount', 0); //Get tokens from request, default to 0 if not provided
//            $user->charge += $chargeToAdd;
//            $user->save();
//
//            return response()->json(['message' => 'Charge added successfully', 'after' => $user->charge]);
//        } else {
//            return response()->json(['error' => 'User not found'], 404);
//        }
//    }

    public function getCharge($id)
    {
        return $this->TokenService->getChargeStatus($id);
    }

//    public function reduceCharge($id, Request $request) {
//        $user = User::find($id);
//        if ($user) {
//            $costToReduce = $request->input('cost', 0);
//            if ($user->charge >= $costToReduce) {
//                $user->charge -= $costToReduce;
//                $user->save();
//
//                return response()->json(['message' => 'Charge reduced successfully', 'left' => $user->charge]);
//            } else {
//                return response()->json(['error' => 'Not enough charge'], 400);
//            }
//        } else {
//            return response()->json(['error' => 'User not found'], 404);
//        }
//    }

    public function reduceCharge($id) {

        return $this->TokenService->reduceCharge($id);

    }


    public function addToken($id, Request $request) {

        $user = User::find($id);
        if ($user) {
            $tokensToAdd = $request->input('tokens', 0); //Get tokens from request, default to 0 if not provided
            $user->tokens += $tokensToAdd;
            $user->save();

            return response()->json(['message' => 'Tokens added successfully', 'tokens' => $user->tokens]);
        } else {
            return response()->json(['error' => 'User not found'], 404);
        }
    }

    public function reduceToken($id, Request $request)
    {
        $user = User::find($id);
        if ($user) {
            $tokensToReduce = $request->input('tokens', 0); // Get tokens from request, default to 0 if not provided
            if ($user->tokens >= $tokensToReduce) {
                $user->tokens -= $tokensToReduce;
                $user->save();

                return response()->json(['message' => 'Tokens reduced successfully', 'tokens' => $user->tokens]);
            } else {
                return response()->json(['error' => 'Not enough tokens'], 400);
            }
        } else {
            return response()->json(['error' => 'User not found'], 404);
        }
    }

    /**
     * Get the number of tokens for the user.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getToken($id)
    {
        $user = User::find($id);
        if ($user) {
            return response()->json(['tokens' => $user->tokens]);
        } else {
            return response()->json(['error' => 'User not found'], 404);
        }
    }



}
