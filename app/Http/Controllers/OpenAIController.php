<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAIController extends Controller
{

    public function openAI()
    {
        return view("openai");
    }

    public function assistantList()
    {
        $response = OpenAI::assistants()->list();

        foreach($response->data as $result) {
            echo $result->id . "<br/>";
        }
    }

    public function messageList() {
        //$threadId = session('thread_id');
        $threadId = "thread_BuI6n6MmNxVMiZwqLjEeGfIW";
        $response = OpenAI::threads()->messages()->list($threadId, [
            'limit' => 10
        ]);

        foreach ($response->data as $result) {
            echo $result->id;
            echo $result->content[0]->text->value . "<br>";
        }

        $response->toArray();

    }



}
