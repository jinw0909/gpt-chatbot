<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Laravel</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />

    <!-- Custom Styles -->
    <link href="{{ asset('css/styles.css') }}" rel="stylesheet">
    <style>
        .generating {
            color: green;
            margin-top: 10px;
        }
        .error-message {
            color: red;
        }
    </style>
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
                                Goya Chat AI
                            </h3>
                            <div class="charge-wrapper">
                                <div class="remaining">Remaining 0</div>
                                <button type="button" id="add-button" class="button-custom">충전</button>
                                <span class="close">&times;</span>
                            </div>
                        </div>
                        <div id="chat-box" class="chat-box">
                            <div class="message center">
                                <div class="system">
                                    <span>접속 시간 <span id="login-time"></span></span>
                                </div>
                            </div>

                            <div class="message left">
                                <div class="assistant content">
                                    <span>안녕하세요.</span>
                                    <span>Goya Chat AI에 오신 것을 환영합니다.</span>
                                    <span>Chat AI는 사용료가 부과되는 유료 서비스입니다.</span>
                                    <span>"Chat AI 질문"을 클릭해 주세요.</span>
                                </div>
                            </div>
                            <div class="message right">
                                <div class="user">
                                    <span>Chat AI 질문하기</span>
                                    <p class="question">지금 진입하기 좋은 코인을 추천해줘</p>
                                    <p class="question">오늘 하루 비트코인 움직임을 분석해줘</p>
                                    <p class="question">암호 화폐 시장의 전망을 알려줘</p>
                                </div>
                            </div>
                        </div>
                        <div id="generating-message" class="generating" style="display: none;">Generating...</div>
                        <div id="input-wrapper" class="input-wrapper">
                            <form id="chat-form" action="{{ route('process-message') }}" method="POST">
                                @csrf
                                <div>
                                    <textarea id="message-input" name="message" rows="4" class="textarea-custom" placeholder="Enter your message here...">{{ old('message', session('inputMessage')) }}</textarea>
                                    <input type="hidden" name="userId" id="user-id" value="1">
                                    <input type="hidden" name="maxUsage" id="max-usage" value="0">
                                </div>
                                <div class="mt-4">
                                    <button type="button" id="send-button" class="button-custom">Send</button>
                                </div>
                            </form>
                        </div>
                        <div class="input-open">
                            <button id="input-open" class="input-open-btn">1대1 문의</button>
                        </div>
                        <div class="input-close">
                            <button id="input-close" class="input-close-btn">일반 문의</button>
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
    let conversation = [];

    async function fetchUserCharge() {
        try {
            const response = await fetch('/user/1/get-charge');
            if (response.ok) {
                const chargeData = await response.json();
                console.log('Current Charge:', chargeData.charge);
                const formattedCharge = parseFloat(chargeData.charge).toFixed(3);
                // Update token display
                document.querySelector('.remaining').textContent = `$${formattedCharge}`;
            } else {
                console.error('Error fetching tokens:', response.statusText);
            }
        } catch (error) {
            console.error('Error:', error);
        }
    }

    async function addUserCharge() {
        try {
            const response = await fetch('/user/1/add-charge', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ amount: 10 }) // Add 10 dollars
            });

            if (response.ok) {
                const data = await response.json();
                console.log('Charge added:', data.after);
                // Fetch the updated token count
                await fetchUserCharge();
            } else {
                console.error('Error adding charge:', response.statusText);
            }
        } catch (error) {
            console.error('Error:', error);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Fetch user tokens when the page loads
        fetchUserCharge();

        // Set the login time
        const loginTimeSpan = document.getElementById('login-time');
        const currentTime = new Date(Date.now());
        const formattedTime = currentTime.toISOString().replace('T', ' ').split('.')[0] + ' UTC';
        loginTimeSpan.textContent = `(${formattedTime})`;
    });

    document.getElementById('send-button').addEventListener('click', async function() {
        const messageInput = document.getElementById('message-input');
        const userIdInput = document.getElementById('user-id');
        const maxUsageInput = document.getElementById('max-usage');
        const chatBox = document.getElementById('chat-box');
        const generatingMessage = document.getElementById('generating-message');
        const message = messageInput.value;

        // Show the "Generating..." message
        generatingMessage.style.display = 'block';
        messageInput.readOnly = true;
        messageInput.classList.add('locked');

        // Clear the input field
        messageInput.value = '';

        // Add the user's message to the chat box
        const userMessageWrapper = document.createElement('div');
        userMessageWrapper.className = 'message right';
        const userMessageDiv = document.createElement('div');
        userMessageDiv.className = 'user';
        userMessageDiv.textContent = message;
        userMessageWrapper.append(userMessageDiv);
        chatBox.appendChild(userMessageWrapper);

        // Scroll to the bottom of the chat box
        chatBox.scrollTop = chatBox.scrollHeight;

        // Send the message via AJAX
        try {
            const response = await fetch('{{ route('process-message') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    message: message,
                    userId: userIdInput.value,
                    maxUsage: maxUsageInput.value,
                    conversation: conversation
                })
            });

            if (response.ok) {
                const data = await response.json();

                // Hide the "Generating..." message
                generatingMessage.style.display = 'none';

                // Add the assistant's message to the chat box
                if (data.type === 'get_recommends') {
                    const parsedResponse = JSON.parse(data.responseText);

                    parsedResponse['recommendations'].forEach(parsedResponse => {
                        // Create div for symbol
                        const symbolDiv = document.createElement('div');
                        //symbolDiv.className = 'message assistant';
                        symbolDiv.textContent = `${parsedResponse.symbol}`;
                        symbolDiv.style.fontWeight = 'bold';
                        // symbolDiv.style.marginTop = '1rem';
                        //chatBox.appendChild(symbolDiv);

                        // Create div for datetime
                        const datetimeDiv = document.createElement('div');
                        //datetimeDiv.className = 'message assistant';
                        datetimeDiv.textContent = `${parsedResponse.datetime}`;
                        //chatBox.appendChild(datetimeDiv);

                        // Create div for image
                        const imageDiv = document.createElement('div');
                        //imageDiv.className = 'message assistant';
                        const imageElement = document.createElement('img');
                        imageElement.src = parsedResponse.image;
                        imageElement.style.width = '100%';
                        imageElement.style.borderRadius = '8px';
                        imageDiv.appendChild(imageElement);
                        //chatBox.appendChild(imageDiv);

                        // Create div for content
                        const contentDiv = document.createElement('div');
                        //contentDiv.className = 'message assistant';
                        contentDiv.textContent = `${parsedResponse.content}`;
                        //chatBox.appendChild(contentDiv);

                        // Create wrapper
                        const wrapperDiv = document.createElement('div');
                        wrapperDiv.className = 'message';
                        const assistantDiv = document.createElement('div');
                        assistantDiv.className = 'assistant';

                        assistantDiv.appendChild(symbolDiv);
                        assistantDiv.appendChild(datetimeDiv);
                        assistantDiv.appendChild(imageDiv);
                        assistantDiv.appendChild(contentDiv);
                        wrapperDiv.appendChild(assistantDiv);
                        chatBox.appendChild(wrapperDiv);

                    });
                } else {
                    // Add the assistant's message to the chat box
                    const wrapperDiv = document.createElement('div');
                    wrapperDiv.className = 'message left'
                    const assistantMessageDiv = document.createElement('div');
                    assistantMessageDiv.className = 'assistant';
                    assistantMessageDiv.innerHTML = data.responseText.replace(/\n/g, '<br>');
                    wrapperDiv.appendChild(assistantMessageDiv);
                    chatBox.appendChild(wrapperDiv);
                }

                // Fetch user tokens
                await fetchUserCharge();

                if (data.wasSummarized) {
                    conversation = [];
                    if (data.summary) {
                        conversation.push(data.summary);
                    }
                }

                // Retrieve the object and parse it
                const userMessage = {
                    role: "user",
                    content: message
                };
                conversation.push(userMessage);

                // Create the assistant message object
                const assistantMessage = {
                    role: "assistant",
                    content: data.responseText
                };
                conversation.push(assistantMessage);
                console.log("conversation: ", conversation);

                //finally set the maxUsage input value
                maxUsageInput.value = data.maxUsage;

                // Scroll to the bottom of the chat box
                chatBox.scrollTop = chatBox.scrollHeight;
            } else {
                // Hide the "Generating..." message and show error message
                generatingMessage.style.display = 'none';
                const errorMessageDiv = document.createElement('div');
                errorMessageDiv.className = 'error-message';
                errorMessageDiv.textContent = 'Something probably went wrong';
                chatBox.appendChild(errorMessageDiv);
            }
        } catch (error) {
            // Hide the "Generating..." message and show error message
            generatingMessage.style.display = 'none';
            const errorMessageDiv = document.createElement('div');
            errorMessageDiv.className = 'error-message';
            errorMessageDiv.textContent = 'Something probably went wrong';
            chatBox.appendChild(errorMessageDiv);
            console.error('Error:', error);
        } finally {
            messageInput.readOnly = false;
            messageInput.classList.remove('locked');

        }
    });

    document.getElementById('add-button').addEventListener('click', function() {
        addUserCharge();
    });

    document.getElementById('input-open').addEventListener('click', function() {
       let inputWrapper = document.getElementById('input-wrapper');
       let chatBox = document.getElementById('chat-box');
       let inputOpen = document.querySelector('.input-open');
       let inputClose = document.querySelector('.input-close');
       chatBox.classList.add('closed');
       inputWrapper.style.display = 'block';

       inputOpen.style.display = 'none';
       inputClose.style.display = 'block';
    });

    document.getElementById('input-close').addEventListener('click', function() {
        let inputWrapper = document.getElementById('input-wrapper');
        let chatBox = document.getElementById('chat-box');
        let inputOpen = document.querySelector('.input-open');
        let inputClose = document.querySelector('.input-close');
        chatBox.classList.remove('closed');
        inputWrapper.style.display = 'none';

        inputClose.style.display = 'none';
        inputOpen.style.display = 'block';
    });

    // Modal handling
    let modal = document.getElementById("myModal");
    let btn = document.querySelector(".open-btn");
    let span = document.querySelector(".close");

    btn.onclick = function() {
        modal.style.display = "block";
    }

    span.onclick = function() {
        modal.style.display = "none";
    }

    // window.onclick = function(event) {
    //     if (event.target == modal) {
    //         modal.style.display = "none";
    //     }
    // }
</script>
</body>
</html>
