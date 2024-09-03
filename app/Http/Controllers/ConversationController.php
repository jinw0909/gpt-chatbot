<?php

namespace App\Http\Controllers;


use App\Services\MessageProcessingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConversationController extends Controller
{
    private $messageProcessingService;

    /**
     * @param $messageProcessingService
     */
    public function __construct(MessageProcessingService $messageProcessingService)
    {
        $this->messageProcessingService = $messageProcessingService;
    }

    public function processMessage(Request $request)
    {
        //Validate the input
        $request->validate([
            'message' => 'required|string'
        ]);

        $message = $request->input('message');
        $userId = $request->input('userId');
        $conversation = $request->input('conversation');
        $token = intval($request->input('maxUsage', 0));
        $recommended = $request->input('recommended');
        Log::info("userId: ", ["userId" => $userId]);
        Log::info("message: ", ["message" => $message]);
        Log::info("token: ", ["token" => $token]);

        $response = $this->messageProcessingService->processMessage($message, $userId, $conversation, $token, $recommended);

        return $response;
    }

}
