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
                                Goya Chat AI
                            </h3>
                            <div class="charge-wrapper">
                                <div class="remaining">Remaining 0</div>
                                <button type="button" id="add-button" class="button-custom">충전</button>
                                <span class="close">&times;</span>
                            </div>
                        </div>
                        <div class="chat-box-body">
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
                                        <p id="question-a" class="question">지금 진입하기 좋은 코인을 추천해줘</p>
                                        <p id="question-b" class="question">오늘 하루 비트코인 움직임을 분석해줘</p>
                                        <p id="question-c" class="question">암호 화폐 시장의 전망을 알려줘</p>
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
                                        <textarea id="message-input" name="message" rows="4" class="textarea-custom" placeholder="Enter your message here...">{{ old('message', session('inputMessage')) }}</textarea>
                                        <input type="hidden" name="userId" id="user-id" value="1">
                                        <input type="hidden" name="maxUsage" id="max-usage" value="0">
                                    </div>
                                    <div class="mt-4 send-button-wrapper">
                                        <button type="button" id="send-button" class="button-custom">Send</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="input-open">
                            <button id="input-open" class="input-open-btn">채팅창 열기</button>
                        </div>
                        <div class="input-close">
                            <button id="input-close" class="input-close-btn">채팅창 닫기</button>
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
        sendMessage();
    });

    document.getElementById('add-button').addEventListener('click', function() {
        addUserCharge();
    });

    document.getElementById('input-open').addEventListener('click', function() {
       let inputWrapper = document.getElementById('input-wrapper');
       let chatBox = document.getElementById('chat-box');
       let inputOpen = document.querySelector('.input-open');
       let inputClose = document.querySelector('.input-close');
       // chatBox.classList.add('closed');
       inputWrapper.style.maxHeight = "175px";

       inputOpen.style.display = 'none';
       inputClose.style.display = 'block';
    });

    document.getElementById('input-close').addEventListener('click', function() {
        let inputWrapper = document.getElementById('input-wrapper');
        let chatBox = document.getElementById('chat-box');
        let inputOpen = document.querySelector('.input-open');
        let inputClose = document.querySelector('.input-close');
        chatBox.classList.remove('closed');
        inputWrapper.style.maxHeight = '0';

        inputClose.style.display = 'none';
        inputOpen.style.display = 'block';
    });

    //function to send message
    let sendMessage = async (custom) => {

        const messageInput = document.getElementById('message-input');
        const userIdInput = document.getElementById('user-id');
        const maxUsageInput = document.getElementById('max-usage');
        const chatBox = document.getElementById('chat-box');

        let message = messageInput.value;
        let userId = userIdInput.value;
        let maxUsage = maxUsageInput.value;

        if (!custom) {
            // Clear the input field
            messageInput.value = '';
        } else {
            message = custom;
        }

        if (message === '') {
            console.log("message is empty");
            return;
        }

        // Show the "Generating..." message
        const generatingMessage = document.getElementById('generating-message');
        generatingMessage.style.display = 'flex';
        messageInput.readOnly = true;
        messageInput.classList.add('locked');

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
            // const response = await fetch('{{ route('process-message') }}', {
            const response = await fetch('{{ route('conversation') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    message: message,
                    userId: userId,
                    maxUsage: maxUsage,
                    conversation: conversation
                })
            });

            if (response.ok) {

                const data = await response.json();
                const parsedResponse = JSON.parse(data.responseText);

                if (parsedResponse.recommendations) {
                    console.log("recommend format");
                    const recommendations = parsedResponse['recommendations'];

                    recommendations.forEach(parsed => {
                        // Create div for symbol
                        const symbolDiv = document.createElement('div');
                        symbolDiv.textContent = `${parsed.symbol}`;
                        symbolDiv.style.fontWeight = 'bold';

                        // Create div for datetime
                        const datetimeDiv = document.createElement('div');
                        datetimeDiv.textContent = `${parsed.datetime}`;

                        // Create div for image
                        const imageDiv = document.createElement('div');
                        const imageElement = document.createElement('img');
                        imageElement.src = parsed.image;
                        imageElement.style.width = '100%';
                        imageElement.style.borderRadius = '8px';
                        imageDiv.appendChild(imageElement);

                        // Create div for content
                        const contentDiv = document.createElement('div');
                        contentDiv.textContent = `${parsed.content}`;

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
                } else if (parsedResponse.symbols) {
                    console.log("symbols format");
                    const symbols = parsedResponse['symbols'];
                    symbols.forEach((parsed) => {
                        console.log("parsed: ", parsed);
                        const canvas = document.createElement('canvas');

                        const symbolDiv = document.createElement('div');
                        symbolDiv.textContent = parsed.symbol;

                        const priceDiv = document.createElement('div');
                        if (parsed.latest_price !== null) {
                            priceDiv.textContent = `$${parsed.latest_price.toLocaleString()}`;
                        } else {
                            priceDiv.textContent = '$0'; // or any default text you want to show when the price is null
                        }

                        const timeDiv = document.createElement('div');
                        timeDiv.textContent = parsed.latest_time;

                        const gapDiv = document.createElement('div');
                        gapDiv.textContent = `${parsed.time_gap.hours ? parsed.time_gap.hours + ' hours': ''} ${parsed.time_gap.minutes ? parsed.time_gap.minutes + ' minutes': ''} ago`
                        gapDiv.style.color = '#bbb';

                        const analysisDiv = document.createElement('div');
                        analysisDiv.textContent = parsed.analysis_translated;

                        const wrapperDiv = document.createElement('div');
                        wrapperDiv.className = 'message';

                        const assistantDiv = document.createElement('div');
                        assistantDiv.className = 'assistant';
                        assistantDiv.appendChild(symbolDiv);
                        assistantDiv.appendChild(priceDiv);
                        assistantDiv.appendChild(timeDiv);
                        assistantDiv.appendChild(gapDiv);
                        assistantDiv.appendChild(canvas);
                        assistantDiv.appendChild(analysisDiv);

                        if (parsed.is_recommended) {
                            const recommendComment = document.createElement('div');
                            recommendComment.textContent = `※ ${parsed.symbol} has signal in the past 12 hours`
                            recommendComment.style.color = 'orange';
                            recommendComment.style.margin = '.25rem 0';
                            const openBtn = document.createElement('button');
                            openBtn.textContent = 'View Signal';
                            openBtn.classList.add("recommend-btn");
                            const closeBtn = document.createElement('button');
                            closeBtn.textContent = 'close';
                            closeBtn.style.display = 'none';
                            closeBtn.classList.add('recommend-btn');
                            const recommendDiv = document.createElement('div');
                            const recommendTimeDiv = document.createElement('div');
                            recommendTimeDiv.textContent = parsed.recommend_time;
                            const recommendImageDiv = document.createElement('img');
                            recommendImageDiv.src = parsed.recommend_image_url;
                            recommendImageDiv.style.width = '100%';
                            recommendImageDiv.style.borderRadius = '8px';
                            const recommendGapDiv = document.createElement('div');
                            recommendGapDiv.textContent = `${parsed.recommend_time_gap.hours ? parsed.recommend_time_gap.hours + ' hours': ''} ${parsed.recommend_time_gap.minutes ? parsed.recommend_time_gap.minutes + ' minutes': ''} ago`;
                            recommendGapDiv.style.color = '#bbb';
                            const recommendContentDiv = document.createElement('div');
                            recommendContentDiv.textContent = parsed.recommend_reason_translated;
                            recommendDiv.appendChild(recommendTimeDiv);
                            recommendDiv.appendChild(recommendGapDiv);
                            recommendDiv.appendChild(recommendImageDiv);
                            recommendDiv.appendChild(recommendContentDiv);
                            // recommendDiv.style.display = 'none';
                            recommendDiv.style.overflow = 'hidden';
                            recommendDiv.classList.add('recommend');
                            openBtn.addEventListener('click', () => {
                                // recommendDiv.style.display = 'block';
                                recommendDiv.classList.add('show');
                                openBtn.style.display = 'none';
                                closeBtn.style.display = 'block';
                            });
                            closeBtn.addEventListener('click', () => {
                               recommendDiv.classList.remove('show');
                               closeBtn.style.display = 'none';
                               openBtn.style.display = 'block';
                            });

                            assistantDiv.appendChild(recommendComment);
                            assistantDiv.appendChild(recommendDiv);
                            assistantDiv.appendChild(openBtn);
                            assistantDiv.appendChild(closeBtn);
                        }

                        //create query options
                        const queryDiv = document.createElement('div');
                        queryDiv.classList.add('message', 'right');
                        const userDiv = document.createElement('div');
                        userDiv.className = 'user';
                        const expected = document.createElement('span');
                        expected.textContent = 'Expected Questions';
                        const question1 = document.createElement('p');
                        const question2 = document.createElement('p');
                        const question3 = document.createElement('p');
                        if (parsed.interval > 48) {
                            question1.textContent = `지난 24시간 ${parsed.symbol} 움직임을 알려줘`;
                        } else {
                            question1.textContent = `지난 한 달간 ${parsed.symbol} 움직임을 알려줘`;
                        }
                        question2.textContent = `최근 ${parsed.symbol} 관련 기사`;
                        question3.textContent = `지금 진입하기 좋은 코인을 추춴해줘`;
                        userDiv.appendChild(expected);
                        userDiv.appendChild(question1);
                        userDiv.appendChild(question2);
                        userDiv.appendChild(question3);
                        queryDiv.appendChild(userDiv);

                        wrapperDiv.appendChild(assistantDiv);
                        chatBox.appendChild(wrapperDiv);
                        chatBox.appendChild(queryDiv);
                        drawChart(parsed.price_movement, parsed.score_movement, canvas, parsed.time_labels);
                    });
                } else if (parsedResponse.common) {
                    // Add the assistant's message to the chat box
                    const wrapperDiv = document.createElement('div');
                    wrapperDiv.className = 'message left';
                    const assistantMessageDiv = document.createElement('div');
                    assistantMessageDiv.className = 'assistant';
                    assistantMessageDiv.innerHTML = parsedResponse['common'].replace(/\n/g, '<br>');
                    wrapperDiv.appendChild(assistantMessageDiv);
                    chatBox.appendChild(wrapperDiv);
                }

                // Hide the "Generating..." message
                generatingMessage.style.display = 'none';

                // Fetch user tokens
                await fetchUserCharge();

                if (data.summary != null) {
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
                // Convert to a number, default to 0 if conversion results in NaN
                maxUsageInput.value = isNaN(Number(data.maxUsage)) ? 0 : Number(data.maxUsage);

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
    }

    let drawChart = (priceMovement, scoreMovement, canvas, labels) => {

        const ctx = canvas.getContext('2d');

        const myChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Market Price',
                        data: priceMovement,
                        borderColor: 'rgba(153, 102, 255, 1)', // Color of the first line
                        borderWidth: 2,
                        fill: false, // Don't fill under the line
                        yAxisID: 'y-left',
                        pointRadius: 1,
                        pointHoverRadius: 3,
                        tension: 0.4
                    },
                    {
                        label: 'Goya Score',
                        data: scoreMovement,
                        borderColor: 'rgba(75, 192, 192, 1)', // Color of the second line
                        borderWidth: 2,
                        fill: false, // Don't fill under the line
                        yAxisID: 'y-right',
                        pointRadius: 1,
                        pointHoverRadius: 3,
                        tension: 0.4
                    }
                ]
            },
            options: {
                scales: {
                    'y-left': { // Left y-axis for Price Movement
                        type: 'linear',
                        position: 'left',

                    },
                    'y-right': { // Right y-axis for Score Movement
                        type: 'linear',
                        position: 'right',

                    },
                    x: { // Hide the x-axis scale as well
                        display: false, // Show the scale for the x-axis
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            title: function(tooltipItems) {
                                // Display the label (time) when hovering over a point
                                return tooltipItems[0].label;
                            }
                        }
                    },
                    legend: {
                        display: false
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        })
    }

    let executeQuestion = (elem) => {
        if (elem.id === 'question-a') {
            message = "지금 진입하기 좋은 코인을 추천해줘";
            console.log('question-a');
            sendMessage(message);
        } else if (elem.id === 'question-b') {
            message = "지난 12시간 동안 비트코인 스코어/가격 움직임 분석해줘"
            console.log('question-b');
            sendMessage(message);
        } else if (elem.id === 'question-c') {
            message = "지난 24시간 동안 비트코인 스코어/가격 움직임 분석해줘"
            console.log('question-c');
            sendMessage(message);
        }
    }

    let questionArray = document.getElementsByClassName('question');
    Array.from(questionArray).forEach((elem) => {
        elem.addEventListener('click', () => {
            executeQuestion(elem);
        });
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

</script>
</body>
</html>
