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

            <button class="open-btn">Click</button>

            <!-- The Modal -->
            <div id="myModal" class="modal">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <main class="main">
                        <div class="chat-box-header">
                            <h3 class="chat-box-title">Goya ChatBox</h3>
                            <div class="charge-wrapper">
                                <div class="remaining">Remaining 0</div>
                                <button type="button" id="add-button" class="button-custom">Charge</button>
                            </div>
                        </div>
                        <div id="chat-box" class="chat-box">
                            <div class="message system">You have just logged in <span id="login-time"></span></div>
                            <div class="message assistant">How may I help you?</div>
                        </div>
                        <div id="generating-message" class="generating" style="display: none;">Generating...</div>
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
                document.querySelector('.remaining').textContent = `Remaining $${formattedCharge}`;
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
        const userMessageDiv = document.createElement('div');
        userMessageDiv.className = 'message user';
        userMessageDiv.textContent = message;
        chatBox.appendChild(userMessageDiv);

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
                        symbolDiv.className = 'message assistant';
                        symbolDiv.textContent = `Symbol: ${parsedResponse.symbol}`;
                        symbolDiv.style.marginTop = '1rem';
                        chatBox.appendChild(symbolDiv);

                        // Create div for datetime
                        const datetimeDiv = document.createElement('div');
                        datetimeDiv.className = 'message assistant';
                        datetimeDiv.textContent = `Datetime: ${parsedResponse.datetime}`;
                        chatBox.appendChild(datetimeDiv);

                        // Create div for image
                        const imageDiv = document.createElement('div');
                        imageDiv.className = 'message assistant';
                        const imageElement = document.createElement('img');
                        imageElement.src = parsedResponse.image;
                        imageElement.style.width = '75%';
                        imageElement.style.borderRadius = '12px';
                        imageDiv.appendChild(imageElement);
                        chatBox.appendChild(imageDiv);

                        // Create div for content
                        const contentDiv = document.createElement('div');
                        contentDiv.className = 'message assistant';
                        contentDiv.textContent = `Content: ${parsedResponse.content}`;
                        chatBox.appendChild(contentDiv);

                    });
                } else {
                    // Add the assistant's message to the chat box
                    const assistantMessageDiv = document.createElement('div');
                    assistantMessageDiv.className = 'message assistant';
                    assistantMessageDiv.innerHTML = data.responseText.replace(/\n/g, '<br>');
                    chatBox.appendChild(assistantMessageDiv);
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

    // Modal handling
    var modal = document.getElementById("myModal");
    var btn = document.querySelector(".open-btn");
    var span = document.querySelector(".close");

    btn.onclick = function() {
        modal.style.display = "block";
    }

    span.onclick = function() {
        modal.style.display = "none";
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }
</script>
</body>
</html>
