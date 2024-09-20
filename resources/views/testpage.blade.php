<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Goya Chatbot</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />

    <!-- Custom Styles -->
    <link href="{{ asset('css/styles.css') }}" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="font-sans antialiased dark:bg-black dark:text-white/50">
<div class="bg-gray-50 text-black/50 dark:bg-black dark:text-white/50">
    <div class="relative min-h-screen flex flex-col items-center justify-center selection:bg-[#FF2D20] selection:text-white">
        <div class="relative w-full max-w-2xl px-6 lg:max-w-7xl">
            <header class="grid grid-cols-2 items-center gap-2 py-10 lg:grid-cols-3">
                @if (Route::has('login'))
                <nav class="-mx-3 flex flex-1 justify-end">
                    @auth
                    <a
                        href="{{ url('/dashboard') }}"
                        class="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white"
                    >
                        Dashboard
                    </a>
                    @else
                    <a
                        href="{{ route('login') }}"
                        class="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white"
                    >
                        Log in
                    </a>

                    @if (Route::has('register'))
                    <a
                        href="{{ route('register') }}"
                        class="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white"
                    >
                        Register
                    </a>
                    @endif
                    @endauth
                </nav>
                @endif
            </header>

            <div class="open-btn">
                <i></i>
                Chat AI
            </div>

            <!-- The Modal -->
            <div id="myModal" class="modal">
                <div class="modal-content">
                    <main class="main">
                        <div class="chat-box-header">
                            <h3 class="chat-box-title">
                                <i></i>
                                <a href="/test">Goya Chat AI</a>
                            </h3>
<!--                            <div class="language-wrapper">-->
<!--                                <select name="language" id="language-select">-->
<!--                                    <option value="kr">KR</option>-->
<!--                                    <option value="jp">JP</option>-->
<!--                                    <option value="en">EN</option>-->
<!--                                </select>-->
<!--                            </div>-->
                            <div class="charge-wrapper">
                                <div class="remaining">$0</div>
                                <button type="button" id="add-button" class="button-custom">충전</button>
                                <span class="close">&times;</span>
                            </div>
                        </div>
                        <div class="chat-box-body">
                            <div id="chat-box" class="chat-box">
<!--                                <div class="message center">-->
<!--                                    <div class="system">-->
<!--                                        <span id="connect-time">접속 시간</span><span id="login-time"></span>-->
<!--                                    </div>-->
<!--                                </div>-->

                                <div class="message left">
                                    <div class="assistant content" id="header-text">
                                        <span>안녕하세요.</span>
                                        <span>Goya Chat AI에 오신 것을 환영합니다.</span>
                                        <span>Chat AI는 사용료가 부과되는 유료 서비스입니다.</span>
                                        <span>"Chat AI 질문"을 클릭해 주세요.</span>
                                    </div>
                                </div>
                                <div class="message right">
                                    <div class="user" id="initial-query">
                                        <span id="ask-question">Chat AI 질문하기</span>
                                        <p id="question-a" class="question">진입하기 좋은 암호 화폐 추천</p>
                                        <p id="question-b" class="question">암호 화폐 시장 동향</p>
                                        <p id="question-c" class="question">고야 스코어란?</p>
                                    </div>
                                </div>
                            </div>
                            <div id="generating-message" class="generating" style="display: none;">
                                <span>Generating...</span>
                            </div>
                            <div id="input-wrapper" class="input-wrapper">
<!--                                <form id="chat-form" action="{{ route('process-message') }}" method="POST">-->
                                <form id="chat-form" action="{{ route('conversation') }}" method="POST">
                                    @csrf
                                    <div class="message-input-wrapper">
                                        <textarea id="message-input" rows="3" name="message" class="textarea-custom" placeholder="메시지를 입력해주세요...">{{ old('message', session('inputMessage')) }}</textarea>
                                        <input type="hidden" name="userId" id="user-id" value="1">
                                        <input type="hidden" name="maxUsage" id="max-usage" value="0">
                                    </div>
                                    <div class="mt-4 send-button-wrapper">
                                        <button type="button" id="send-button" class="button-custom">Send</button>
                                    </div>
                                </form>
                            </div>
                            <div id="etc-wrapper" class="etc-wrapper">
                                <div id="language-wrapper" class="language-wrapper">
                                    <select name="language" id="language-select">
                                        <option value="kr">KR</option>
                                        <option value="jp">JP</option>
                                        <option value="en">EN</option>
                                    </select>
                                </div>
                                <div id="toggle-wrapper" class="toggle-wrapper">
                                    <div class="input-open">
                                        <button id="input-open" class="input-open-btn">채팅창 열기</button>
                                    </div>
                                    <div class="input-close">
                                        <button id="input-close" class="input-close-btn">채팅창 닫기</button>
                                    </div>
                                </div>
                            </div>

                        </div>


                        @if (session('responseText'))
                        <div class="mt-6">
                            <h2 class="text-xl font-semibold">Response:</h2>
                            <p class="mt-2">{{ session('responseText') }}</p>
                        </div>
                        @endif
                    </main>
                </div>
            </div>

        </div>
    </div>
</div>
<script>
    let processMessageUrl = "{{ route('conversation')}}";
    console.log("conversationUrl: ", processMessageUrl);
</script>
<script src="{{ asset('js/chat.js') }}"></script>
<!--<script src="{{ asset('js/conversation.js') }}"></script>-->
<script src="{{ asset('js/texts.js') }}"></script>
</body>
</html>
