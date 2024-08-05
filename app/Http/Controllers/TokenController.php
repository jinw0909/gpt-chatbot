<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class TokenController extends Controller
{
    /**
     * Add tokens to the user.
     *
     * @param  int  $id
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
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

    public function addCharge($id, Request $request) {
        $user = User::find($id);
        if ($user) {
            $chargeToAdd = $request->input('amount', 0); //Get tokens from request, default to 0 if not provided
            $user->charge += $chargeToAdd;
            $user->save();

            return response()->json(['message' => 'Charge added successfully', 'after' => $user->charge]);
        } else {
            return response()->json(['error' => 'User not found'], 404);
        }
    }

    /**
     * Reduce tokens from the user.
     *
     * @param  int  $id
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
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

    public function reduceCharge($id, Request $request) {
        $user = User::find($id);
        if ($user) {
            $costToReduce = $request->input('cost', 0);
            if ($user->charge >= $costToReduce) {
                $user->charge -= $costToReduce;
                $user->save();

                return response()->json(['message' => 'Charge reduced successfully', 'left' => $user->charge]);
            } else {
                return response()->json(['error' => 'Not enough charge'], 400);
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

    public function getCharge($id)
    {
        $user = User::find($id);
        if ($user) {
            return response()->json(['charge' => $user->charge]);
        } else {
            return response()->json(['error' => 'User not found'], 404);
        }
    }


}
