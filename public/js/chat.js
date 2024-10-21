let conversation = [];

document.addEventListener('DOMContentLoaded', async () => {
    await getUsername();
});
let isComposing = false;
let messageInput = document.getElementById('message-input');
messageInput.addEventListener('compositionstart', () => {
   isComposing = true;
});
messageInput.addEventListener('compositionend', () => {
   isComposing = false;
});
messageInput.addEventListener('keydown', function(event) {
   if (event.key === 'Enter' && !isComposing) {
       if (event.shiftKey) {
           return;
       } else {
           //check if the focused element is the message input
           if (document.activeElement.id === 'message-input') {
               event.preventDefault(); // Prevent the default behavior
               sendMessage(); //Call the sendMessageFunction
           }
       }
   }
});
document.getElementById('send-button').addEventListener('click', async function() {
    sendMessage();
});
document.getElementById('input-open').addEventListener('click', function() {
    let inputWrapper = document.getElementById('input-wrapper');
    // let chatBox = document.getElementById('chat-box');
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

let recommendedSymbols = [];
let revealedArticles = [];
let articleList = [];
let viewpointList = [];

//function to send message
let sendMessage = async (custom) => {

    const chatForm = document.getElementById('chat-form');
    const messageInput = document.getElementById('message-input');
    const userIdInput = document.getElementById('user-id');
    const maxUsageInput = document.getElementById('max-usage');
    const chatBox = document.getElementById('chat-box');

    let message = messageInput.value;
    let userId = userIdInput.value;
    let maxUsage = maxUsageInput.value;
    let flattenedSymbols = recommendedSymbols.flat();

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

    messageInput.readOnly = true;
    // messageInput.classList.add('locked');
    chatForm.classList.add('locked');
    document.querySelectorAll('.question').forEach(div => {
        div.classList.add('locked');
    });

    // Add the user's message to the chat box
    const userMessageWrapper = document.createElement('div');
    userMessageWrapper.className = 'message right';
    const userMessageDiv = document.createElement('div');
    userMessageDiv.className = 'user';

    userMessageDiv.textContent = message;
    userMessageWrapper.append(userMessageDiv);
    chatBox.appendChild(userMessageWrapper);

    // Add generating message to the chat box
    const generatingWrapper = document.createElement('div');
    generatingWrapper.classList.add('message', 'left');
    const generatingContent = document.createElement('div');
    generatingContent.classList.add('assistant');
    const generatingMessage = document.createElement('div');
    generatingMessage.classList.add('generating');
    switch (selectedLanguage) {
        case 'kr':
            generatingMessage.innerText = '응답 생성중입니다...';
            break;
        case 'jp':
            generatingMessage.innerText = '回答を生成中です。。。';
            break;
        case 'en':
            generatingMessage.innerText = 'Generating message...';
            break;
        default:
            generatingMessage.innerText = 'Generating message...';
            break;
    }
    generatingContent.appendChild(generatingMessage);
    generatingWrapper.appendChild(generatingContent);
    chatBox.appendChild(generatingWrapper);

    // Scroll to the bottom of the chat box
    chatBox.scrollTop = chatBox.scrollHeight;

    // Send the message via AJAX
    try {
        // const response = await fetch('{{ route('process-message') }}', {
        const response = await fetch(processMessageUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                message: message,
                userId: userId,
                maxUsage: maxUsage,
                conversation: conversation,
                symbols: flattenedSymbols,
                articles: revealedArticles,
                lang: selectedLanguage
            })
        });

        if (response.ok) {
            const data = await response.json();
            const parsedResponse = JSON.parse(data.responseText);
            console.log("parsedResponse: ", parsedResponse);
            if (parsedResponse.data.format_type === 'crypto_recommendations') {
                revealedArticles.shift();
                const recommendations = parsedResponse.data.content;
                const symbols = [];
                const language = parsedResponse.data.language || 'en';
                recommendations.forEach(parsed => {
                    console.log("parsed recommendation: ", parsed);
                    parsed.symbol = parsed.symbol.toUpperCase();
                    symbols.push(parsed.symbol);

                    const headerDiv = document.createElement('div');
                    headerDiv.classList.add('header-div');
                    const logoDiv = document.createElement('div');
                    logoDiv.classList.add('logo-div');
                    if (!parsed.symbol_logo) {
                        logoDiv.style.display = 'none';
                    } else {
                        logoDiv.style.backgroundImage = `url(${parsed.symbol_logo})`;
                    }
                    // Create div for symbol
                    const symbolDiv = document.createElement('div');
                    symbolDiv.textContent = `${parsed.symbol}`;
                    symbolDiv.style.color = 'aqua';
                    symbolDiv.classList.add('symbol-div');

                    headerDiv.appendChild(logoDiv);
                    headerDiv.appendChild(symbolDiv);

                    // Create div for datetime
                    const datetimeDiv = document.createElement('div');
                    // datetimeDiv.textContent = `${parsed.datetime}`;
                    datetimeDiv.textContent = `${parsed.datetime.replace('T', ' ').split(':').slice(0, 2).join(":")}`;

                    const gapDiv = document.createElement('div');
                    // const timeTexts = timeUnits[selectedLanguage];
                    const timeTexts = timeUnits[language];

                    let timeGapText = '';
                    if (parsed.time_gap.hours) {
                        timeGapText += `${parsed.time_gap.hours}${parsed.time_gap.hours === 1 ? timeTexts.hour : timeTexts.hours}`;
                    }
                    if (parsed.time_gap.minutes) {
                        timeGapText += `${parsed.time_gap.minutes}${parsed.time_gap.minutes === 1 ? timeTexts.minutes : timeTexts.minutes}`;
                    }
                    if (timeGapText) {
                        timeGapText += timeTexts.recommend;
                    }

                    gapDiv.textContent = timeGapText;
                    gapDiv.style.color = '#bbb';

                    const timeDiv = document.createElement('div');
                    timeDiv.classList.add('time');
                    timeDiv.appendChild(datetimeDiv);
                    timeDiv.appendChild(gapDiv);

                    // Create div for image
                    const imageDiv = document.createElement('div');
                    imageDiv.classList.add('recommend-img');
                    const imageElement = document.createElement('img');
                    imageElement.src = parsed.image_url;
                    imageElement.style.width = '100%';
                    imageElement.style.borderRadius = '8px';
                    imageDiv.appendChild(imageElement);

                    // Create div for content
                    const contentDiv = document.createElement('div');
                    contentDiv.textContent = `${parsed.recommended_reason_translated}`;
                    // contentDiv.textContent = `${parsed.recommended_reason}`;

                    const cautionDiv = document.createElement('div');
                    cautionDiv.textContent = texts[language]['caution'];
                    cautionDiv.classList.add('caution');

                    // Create wrapper
                    const wrapperDiv = document.createElement('div');
                    wrapperDiv.className = 'message';
                    const assistantDiv = document.createElement('div');
                    assistantDiv.className = 'assistant';

                    // assistantDiv.appendChild(symbolDiv);
                    assistantDiv.appendChild(headerDiv);
                    // assistantDiv.appendChild(datetimeDiv);
                    // assistantDiv.appendChild(gapDiv);
                    assistantDiv.appendChild(timeDiv);
                    assistantDiv.appendChild(imageDiv);
                    assistantDiv.appendChild(contentDiv);
                    assistantDiv.appendChild(cautionDiv);

                    //create query options
                    const queryDiv = document.createElement('div');
                    queryDiv.classList.add('message', 'right');
                    queryDiv.style.marginTop = '0';
                    const userDiv = document.createElement('div');
                    userDiv.className = 'user';
                    const expected = document.createElement('span');
                    expected.textContent = 'Expected Questions';
                    const question1 = document.createElement('p');
                    const question2 = document.createElement('p');
                    const question3 = document.createElement('p');


                    if (parsed.symbol.endsWith("USDT")) {
                        // Remove "USDT" from the end of the symbol
                        parsed.symbol = parsed.symbol.substring(0, parsed.symbol.length - 4);
                    }

                    question1.classList.add('analyze-symbol', 'question');
                    question2.classList.add('analyze-symbol-month', 'question');
                    question3.classList.add('explain-symbol', 'question');
                    question1.setAttribute('data-symbol', parsed.symbol);
                    question2.setAttribute('data-symbol', parsed.symbol);
                    question3.setAttribute('data-symbol', parsed.symbol);

                    if (selectedLanguage === 'kr') {
                        question1.textContent = `${parsed.symbol} 24시간 스코어 정보`;
                        question2.textContent = `${parsed.symbol} 한 달간 스코어 정보`;
                        question3.textContent = `암호화폐 ${parsed.symbol}에 대해 알려줘`;
                    } else if (selectedLanguage === 'jp') {
                        question1.textContent = `${parsed.symbol}の24時間スコアデータ`;
                        question2.textContent = `過去1ヶ月の${parsed.symbol}のスコアデータ`;
                        question3.textContent = `暗号通貨${parsed.symbol}について説明してください`;
                    } else if (selectedLanguage === 'en') {
                        question1.textContent = `${parsed.symbol} 24 hours score data`;
                        question2.textContent = `${parsed.symbol} one-month score data`;
                        question3.textContent = `Tell me about the cryptocurrency ${parsed.symbol}`;
                    }

                    question1.addEventListener('click', function() {
                        executeQuestion(this);
                    });
                    question2.addEventListener('click', function() {
                        executeQuestion(this);
                    });
                    question3.addEventListener('click', function() {
                        executeQuestion(this);
                    });

                    // userDiv.appendChild(expected);
                    userDiv.appendChild(question1);
                    userDiv.appendChild(question2);
                    userDiv.appendChild(question3);
                    queryDiv.appendChild(userDiv);

                    wrapperDiv.appendChild(assistantDiv);
                    chatBox.appendChild(wrapperDiv);
                    chatBox.appendChild(queryDiv);
                });

                recommendedSymbols.push(symbols);

                //create query options
                const queryDiv = document.createElement('div');
                queryDiv.classList.add('message', 'right');
                queryDiv.style.marginTop = '0';
                const userDiv = document.createElement('div');
                userDiv.className = 'user';
                const expected = document.createElement('span');
                expected.textContent = 'Expected Questions';
                const question1 = document.createElement('p');
                const question2 = document.createElement('p');
                const question3 = document.createElement('p');

                question1.classList.add("other-crypto", "question");
                question2.classList.add("explain-criteria", "question");
                question3.classList.add("market-trend", "question");

                // Update text content based on the selected language
                if (selectedLanguage === 'kr') {
                    question1.textContent = `다른 암호 화폐 추천`;
                    question2.textContent = `추천 기준에 대해 알려줘`;
                    question3.textContent = `암호 화폐 시장 전망`;
                } else if (selectedLanguage === 'jp') {
                    question1.textContent = `他の暗号通貨のおすすめ`;
                    question2.textContent = `おすすめの基準について教えてください`;
                    question3.textContent = `暗号通貨市場の展望`;
                } else if (selectedLanguage === 'en') {
                    question1.textContent = `Other cryptocurrency recommendations`;
                    question2.textContent = `Tell me about the recommendation criteria`;
                    question3.textContent = `Cryptocurrency market outlook`;
                }

                question1.addEventListener('click', function() {
                    executeQuestion(this);
                });
                question2.addEventListener('click', function() {
                    executeQuestion(this);
                });
                question3.addEventListener('click', function() {
                    executeQuestion(this);
                });

                // userDiv.appendChild(expected);
                userDiv.appendChild(question1);
                userDiv.appendChild(question2);
                userDiv.appendChild(question3);
                queryDiv.appendChild(userDiv);

                chatBox.appendChild(queryDiv);


            }
            else if (parsedResponse.data.format_type === 'crypto_analysis') {
                const symbols = parsedResponse.data.content;
                const language = parsedResponse.data.language || 'en';
                recommendedSymbols.shift();
                revealedArticles.shift();
                symbols.forEach((parsed) => {
                    console.log("parsed crypto_analysis: ", parsed);
                    parsed.symbol = parsed.symbol.toUpperCase();
                    const canvas = document.createElement('canvas');
                    const headerDiv = document.createElement('div');
                    const logoDiv = document.createElement('div');
                    logoDiv.classList.add('logo-div');
                    if (!parsed.symbol_logo) {
                        logoDiv.style.display = 'none';
                    } else {
                        logoDiv.style.backgroundImage = `url(${parsed.symbol_logo})`;
                    }

                    const symbolDiv = document.createElement('div');
                    symbolDiv.textContent = parsed.symbol;
                    symbolDiv.style.color = 'aqua';
                    symbolDiv.classList.add('symbol-div');

                    headerDiv.appendChild(logoDiv);
                    headerDiv.appendChild(symbolDiv);
                    headerDiv.classList.add('header-div');

                    const priceDiv = document.createElement('div');
                    priceDiv.classList.add('price');

                    console.log('symbol_price:', parsed.symbol_data.symbol_price, typeof parsed.symbol_data.symbol_price);
                    if (parsed.symbol_price !== null && !isNaN(parsed.symbol_data.symbol_price)) {
                        priceDiv.textContent = `$${Number(parsed.symbol_data.symbol_price).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 7 })}`;
                    } else {
                        priceDiv.textContent = '$0'; // or any default text you want to show when the price is null
                    }

                    const datetimeDiv = document.createElement('div');
                    // datetimeDiv.textContent = parsed.symbol_data.record_time;
                    datetimeDiv.textContent = parsed.symbol_data.record_time.replace('T', ' ').split(":").slice(0, 2).join(":");
                    const gapDiv = document.createElement('div');
                    console.log("response language: ", language);
                    const timeTexts = timeUnits[language];
                    // const timeTexts = timeUnits[selectedLanguage];

                    let timeGapText = '';
                    if (parsed.symbol_data.time_gap.hours) {
                        timeGapText += `${parsed.symbol_data.time_gap.hours}${parsed.symbol_data.time_gap.hours === 1 ? timeTexts.hour : timeTexts.hours}`;
                    }
                    if (parsed.symbol_data.time_gap.minutes) {
                        timeGapText += `${parsed.symbol_data.time_gap.minutes}${parsed.symbol_data.time_gap.minutes === 1 ? timeTexts.minutes : timeTexts.minutes}`;
                    }
                    if (timeGapText) {
                        timeGapText += timeTexts.analysis;
                    }

                    gapDiv.textContent = timeGapText;
                    gapDiv.style.color = '#bbb';

                    const timeDiv = document.createElement('div');
                    timeDiv.classList.add('time');
                    timeDiv.appendChild(datetimeDiv);
                    timeDiv.appendChild(gapDiv);

                    const analysisDiv = document.createElement('div');
                    analysisDiv.textContent = parsed.analysis_translated;

                    const wrapperDiv = document.createElement('div');
                    wrapperDiv.className = 'message';

                    const assistantDiv = document.createElement('div');
                    assistantDiv.className = 'assistant';

                    //divide by checking the existence of the crypto_data
                    if (Array.isArray(parsed.crypto_data) && parsed.crypto_data.length > 0) {
                        // If parsed.crypto_data is a non-empty array, append all elements
                        // assistantDiv.appendChild(symbolDiv);
                        assistantDiv.appendChild(headerDiv);
                        assistantDiv.appendChild(priceDiv);
                        assistantDiv.appendChild(timeDiv);
                        // assistantDiv.appendChild(datetimeDiv);
                        // assistantDiv.appendChild(gapDiv);
                        assistantDiv.appendChild(canvas);
                        assistantDiv.appendChild(analysisDiv);
                    } else if (Array.isArray(parsed.crypto_data) && parsed.crypto_data.length === 0) {
                        // If parsed.crypto_data is an empty array, append only certain elements
                        assistantDiv.appendChild(symbolDiv);
                        assistantDiv.appendChild(analysisDiv);
                    }

                    if (parsed.recommendation_status.is_recommended) {
                        const status = parsed.recommendation_status;
                        const recommendComment = document.createElement('div');
                        recommendComment.classList.add("recommend-comment");

                        const openBtn = document.createElement('button');
                        openBtn.style.cursor = 'pointer';
                        openBtn.classList.add("recommend-btn", "recommend-open-btn");

                        const closeBtn = document.createElement('button');
                        closeBtn.classList.add('close-btn');
                        closeBtn.style.display = 'none';
                        closeBtn.style.cursor = 'pointer';
                        closeBtn.classList.add('recommend-btn', 'recommend-close-btn');

                        if (language === 'kr') {
                            recommendComment.textContent = `※ ${parsed.symbol}에서 지난 4시간 내에 신호가 발생했습니다.`; // "※ {symbol} has signal in the past 6 hours" in Korean
                            openBtn.textContent = '시그널 보기';
                            closeBtn.textContent = '닫기';
                        } else if (language === 'jp') {
                            recommendComment.textContent = `※ ${parsed.symbol}には過去4時間で発生したシグナルがあります。`; // "※ {symbol} has signal in the past 6 hours" in Japanese
                            openBtn.textContent = 'シグナルを確認';
                            closeBtn.textContent = '閉じる';
                        } else if (language === 'en') {
                            recommendComment.textContent = `※ ${parsed.symbol} has signal in the past 4 hours.`;
                            openBtn.textContent = 'View Signal';
                            closeBtn.textContent = 'Close';
                        }
                        // recommendComment.style.color = 'orange';
                        // recommendComment.style.margin = '.25rem 0';

                        openBtn.textContent = 'View Signal';

                        const recommendDiv = document.createElement('div');
                        const recommendDatetimeDiv = document.createElement('div');
                        recommendDatetimeDiv.textContent = status.recommended_datetime.replace('T', ' ').split(':').slice(0, 2).join(":");

                        const recommendGapDiv = document.createElement('div');
                        // const timeTexts = timeUnits[selectedLanguage];
                        const timeTexts = timeUnits[language];

                        let recommendGapText = '';
                        if (parsed.recommendation_status.time_gap.hours) {
                            recommendGapText += `${parsed.recommendation_status.time_gap.hours}${parsed.recommendation_status.time_gap.hours === 1 ? timeTexts.hour : timeTexts.hours}`;
                        }
                        if (parsed.recommendation_status.time_gap.minutes) {
                            recommendGapText += `${parsed.recommendation_status.time_gap.minutes}${parsed.recommendation_status.time_gap.minutes === 1 ? timeTexts.minutes : timeTexts.minutes}`;
                        }
                        if (timeGapText) {
                            recommendGapText += timeTexts.recommend;
                        }

                        recommendGapDiv.textContent = recommendGapText;
                        recommendGapDiv.style.color = '#bbb';

                        const recommendTimeDiv = document.createElement('div');
                        recommendTimeDiv.classList.add('time');
                        recommendTimeDiv.style.marginTop = '0';
                        recommendTimeDiv.appendChild(recommendDatetimeDiv);
                        recommendTimeDiv.appendChild(recommendGapDiv);

                        const recommendImageDiv = document.createElement('img');
                        recommendImageDiv.src = status.image_url;
                        recommendImageDiv.style.width = '100%';
                        recommendImageDiv.style.borderRadius = '8px';

                        const recommendContentDiv = document.createElement('div');
                        recommendContentDiv.textContent = status.recommended_reason_translated;

                        const cautionDiv = document.createElement('div');
                        cautionDiv.classList.add('caution');
                        cautionDiv.textContent = texts[language]['caution'];

                        // recommendContentDiv.textContent = status.recommended_reason;
                        recommendDiv.appendChild(recommendTimeDiv);
                        // recommendDiv.appendChild(recommendDatetimeDiv);
                        // recommendDiv.appendChild(recommendGapDiv);
                        recommendDiv.appendChild(recommendImageDiv);
                        recommendDiv.appendChild(recommendContentDiv);
                        recommendDiv.appendChild(cautionDiv);
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
                    queryDiv.style.marginTop = '0';
                    const userDiv = document.createElement('div');
                    userDiv.className = 'user';
                    const expected = document.createElement('span');
                    expected.textContent = 'Expected Questions';
                    const question1 = document.createElement('p');
                    const question2 = document.createElement('p');
                    const question3 = document.createElement('p');

                    if (parsed.symbol.endsWith("USDT")) {
                        // Remove "USDT" from the end of the symbol
                        parsed.symbol = parsed.symbol.substring(0, parsed.symbol.length - 4);
                    }


                    if (parsed.interval > 48) {
                        question1.classList.add('analyze-symbol');
                        if (selectedLanguage === 'kr') {
                            question1.textContent = `${parsed.symbol} 24시간 스코어 정보`;
                        } else if (selectedLanguage === 'jp') {
                            question1.textContent = `${parsed.symbol}の過去24時間のスコアデータ`;
                        } else if (selectedLanguage === 'en') {
                            question1.textContent = `${parsed.symbol} 24 hours score data`;
                        }
                    } else {
                        question1.classList.add('analyze-symbol-month');
                        if (selectedLanguage === 'kr') {
                            question1.textContent = `${parsed.symbol} 한 달간 스코어 정보`;
                        } else if (selectedLanguage === 'jp') {
                            question1.textContent = `過去1ヶ月の${parsed.symbol}の過去一ヶ月間のスコアデータ`;
                        } else if (selectedLanguage === 'en') {
                            question1.textContent = `${parsed.symbol} one-month score data`;
                        }
                    }

                    question1.classList.add("question");
                    question2.classList.add("explain-symbol", "question");
                    question3.classList.add("recommend-crypto", "question");
                    question1.setAttribute('data-symbol', parsed.symbol);
                    question2.setAttribute('data-symbol', parsed.symbol);
                    question3.setAttribute('data-symbol', parsed.symbol);

                    if (selectedLanguage === 'kr') {
                        question2.textContent = `암호화폐 ${parsed.symbol}에 대해 알려줘`;
                        question3.textContent = `진입하기 좋은 암호 화폐 추천`;
                    } else if (selectedLanguage === 'jp') {
                        question2.textContent = `暗号通貨${parsed.symbol}について説明してください`;
                        question3.textContent = `エントリーに適した暗号通貨のおすすめ`;
                    } else if (selectedLanguage === 'en') {
                        question2.textContent = `Tell me about the cryptocurrency ${parsed.symbol}`;
                        question3.textContent = `Recommended cryptocurrencies to enter`;
                    }

                    question1.addEventListener('click', function() {
                        executeQuestion(this);
                    });
                    question2.addEventListener('click', function() {
                        executeQuestion(this);
                    });
                    question3.addEventListener('click', function() {
                        executeQuestion(this);
                    });

                    // userDiv.appendChild(expected);
                    userDiv.appendChild(question1);
                    userDiv.appendChild(question2);
                    userDiv.appendChild(question3);
                    queryDiv.appendChild(userDiv);

                    wrapperDiv.appendChild(assistantDiv);
                    chatBox.appendChild(wrapperDiv);
                    chatBox.appendChild(queryDiv);
                    const timeLabels = parsed.crypto_data.map(item => item.datetime);
                    console.log("timeLabel passed to the function drawchart(): ", timeLabels);
                    const scoreMovement = parsed.crypto_data.map(item => item.score);
                    const priceMovement = parsed.crypto_data.map(item => item.price);
                    drawChart(priceMovement, scoreMovement, timeLabels, canvas);
                });
            }
            else if (parsedResponse.data.format_type === 'articles') {
                recommendedSymbols.unshift();
                const articles = parsedResponse.data.content;
                const language = parsedResponse.data.language;

                articles.forEach(parsed => {
                    console.log("parsed article: ", parsed);

                    const articleId = parseInt(parsed.id);
                    if (!revealedArticles.includes(articleId)) {
                        revealedArticles.push(articleId);
                    }

                    const titleDiv = document.createElement('div');
                    titleDiv.style.color = 'aqua';
                    // Check if the type is "viewpoint"
                    titleDiv.textContent = parsed.title;

                    const datetimeDiv = document.createElement('div');
                    // datetimeDiv.textContent = parsed.datetime;
                    datetimeDiv.textContent = `${parsed.datetime.replace('T', ' ').split(':').slice(0, 2).join(":")}`;
                    const timegapDiv = document.createElement('div');
                    // const timeTexts = timeUnits[selectedLanguage];
                    const timeTexts = timeUnits[language];

                    let timeGapText = '';
                    if (parsed.time_gap.hours) {
                        timeGapText += `${parsed.time_gap.hours}${parsed.time_gap.hours === 1 ? timeTexts.hour : timeTexts.hours}`;
                    }
                    if (parsed.time_gap.minutes) {
                        timeGapText += `${parsed.time_gap.minutes}${parsed.time_gap.minutes === 1 ? timeTexts.minutes : timeTexts.minutes}`;
                    }
                    if (timeGapText) {
                        timeGapText += timeTexts.articles;
                    }

                    timegapDiv.textContent = timeGapText;
                    timegapDiv.style.color = '#bbb';

                    const timeDiv = document.createElement('div');
                    timeDiv.classList.add('time');
                    timeDiv.appendChild(datetimeDiv);
                    timeDiv.appendChild(timegapDiv);

                    // Create div for image
                    const imageDiv = document.createElement('div');
                    imageDiv.style.width = '100%';
                    const imageElement = document.createElement('img');
                    imageElement.src = parsed.image_url;
                    imageElement.style.width = '100%';
                    imageElement.style.borderRadius = '8px';
                    imageDiv.appendChild(imageElement);

                    const contentDiv = document.createElement('div');
                    contentDiv.textContent = parsed.content;
                    contentDiv.style.marginTop = '.5rem';

                    const summaryDiv = document.createElement('div');
                    summaryDiv.textContent = parsed.summary;
                    summaryDiv.style.display = 'none';
                    summaryDiv.style.marginTop = '.5rem';

                    const articleDiv = document.createElement('div');
                    articleDiv.textContent = parsed.article;
                    articleDiv.style.display = 'none';
                    articleDiv.style.marginTop = '.5rem';

                    // Create a container for buttons
                    const buttonContainer = document.createElement('div');
                    buttonContainer.style.display = 'flex';  // Use flexbox to align buttons horizontally
                    buttonContainer.style.gap = '0.5rem';

                    // Create buttons
                    const contentButton = document.createElement('button');
                    contentButton.textContent = 'Analysis';
                    contentButton.style.width = '5rem';
                    contentButton.classList.add('active', 'article-button');

                    const summaryButton = document.createElement('button');
                    summaryButton.textContent = 'Summary';
                    summaryButton.style.width = '5rem';
                    summaryButton.classList.add('article-button');

                    const articleButton = document.createElement('button');
                    articleButton.textContent = 'Article';
                    articleButton.style.width = '5rem';
                    articleButton.classList.add('article-button');

                    // Append buttons to the button container
                    buttonContainer.appendChild(contentButton);
                    buttonContainer.appendChild(summaryButton);
                    buttonContainer.appendChild(articleButton);
                    // Add event listeners for buttons
                    contentButton.addEventListener('click', () => {
                        contentButton.classList.add('active');
                        summaryButton.classList.remove('active');
                        articleButton.classList.remove('active');
                        contentDiv.style.display = 'block';
                        summaryDiv.style.display = 'none';
                        articleDiv.style.display = 'none';
                    });

                    summaryButton.addEventListener('click', () => {
                        summaryButton.classList.add('active');
                        contentButton.classList.remove('active');
                        articleButton.classList.remove('active');
                        contentDiv.style.display = 'none';
                        summaryDiv.style.display = 'block';
                        articleDiv.style.display = 'none';
                    });

                    articleButton.addEventListener('click', () => {
                        articleButton.classList.add('active');
                        contentButton.classList.remove('active');
                        summaryButton.classList.remove('active');
                        contentDiv.style.display = 'none';
                        summaryDiv.style.display = 'none';
                        articleDiv.style.display = 'block';
                    });

                    const wrapperDiv = document.createElement('div');
                    wrapperDiv.className = 'message left';
                    const assistantMessageDiv = document.createElement('div');
                    assistantMessageDiv.className = 'assistant';
                    assistantMessageDiv.appendChild(titleDiv);
                    // assistantMessageDiv.appendChild(datetimeDiv);
                    // assistantMessageDiv.appendChild(timegapDiv);
                    assistantMessageDiv.appendChild(timeDiv);
                    assistantMessageDiv.appendChild(imageDiv);
                    assistantMessageDiv.appendChild(buttonContainer);
                    assistantMessageDiv.appendChild(contentDiv);
                    assistantMessageDiv.appendChild(summaryDiv);
                    assistantMessageDiv.appendChild(articleDiv);
                    wrapperDiv.appendChild(assistantMessageDiv);
                    chatBox.appendChild(wrapperDiv);

                });

                //create query options
                const queryDiv = document.createElement('div');
                queryDiv.classList.add('message', 'right');
                queryDiv.style.marginTop = '0';
                const userDiv = document.createElement('div');
                userDiv.className = 'user';
                const expected = document.createElement('span');
                expected.textContent = 'Expected Questions';
                const question1 = document.createElement('p');
                const question2 = document.createElement('p');
                const question3 = document.createElement('p');

                question1.classList.add("other-news", "question");
                question2.classList.add("analyze-btc", "question");
                question3.classList.add("recommend-crypto", "question");

                if (selectedLanguage === 'kr') {
                    question1.textContent = `다른 주요 암호 화폐 뉴스`; // "Other major cryptocurrency news" in Korean
                    question2.textContent = `비트코인 스코어 및 가격`; // "Bitcoin score and price analysis" in Korean
                    question3.textContent = `진입하기 좋은 암호 화폐 추천`; // "Recommended cryptocurrencies to enter" in Korean
                } else if (selectedLanguage === 'jp') {
                    question1.textContent = `他の主要な暗号通貨ニュース`; // "Other major cryptocurrency news" in Japanese
                    question2.textContent = `ビットコインのスコアと価格`; // "Bitcoin score and price analysis" in Japanese
                    question3.textContent = `エントリーに適した暗号通貨のおすすめ`; // "Recommended cryptocurrencies to enter" in Japanese
                } else if (selectedLanguage === 'en') {
                    question1.textContent = `Other major cryptocurrency news`;
                    question2.textContent = `Bitcoin score and price`;
                    question3.textContent = `Recommended cryptocurrencies to enter`;
                }

                question1.addEventListener('click', function() {
                    executeQuestion(this);
                });
                question2.addEventListener('click', function() {
                    executeQuestion(this);
                });
                question3.addEventListener('click', function() {
                    executeQuestion(this);
                });

                // userDiv.appendChild(expected);
                userDiv.appendChild(question1);
                userDiv.appendChild(question2);
                userDiv.appendChild(question3);
                queryDiv.appendChild(userDiv);
                chatBox.appendChild(queryDiv);


            }
            else if (parsedResponse.data.format_type === 'viewpoint') {
                recommendedSymbols.unshift();
                revealedArticles.unshift();
                const parsed = parsedResponse.data.content;
                const language = parsedResponse.data.language;

                //console.log("parsed viewpoint: ", parsed);

                const titleDiv = document.createElement('div');
                titleDiv.style.color = 'aqua';

                titleDiv.textContent = formatDateTimeToWords(parsed.id);

                const datetimeDiv = document.createElement('div');
                datetimeDiv.textContent = `${parsed.datetime.replace('T', ' ').split(':').slice(0, 2).join(":")}`;
                // datetimeDiv.textContent = parsed.datetime;
                const gapDiv = document.createElement('div');

                // const timeTexts = timeUnits[selectedLanguage];
                // const timeTexts = timeUnits[selectedLanguage];
                const timeTexts = timeUnits[language];

                let timeGapText = '';
                if (parsed.time_gap.hours) {
                    timeGapText += `${parsed.time_gap.hours}${parsed.time_gap.hours === 1 ? timeTexts.hour : timeTexts.hours}`;
                }
                if (parsed.time_gap.minutes) {
                    timeGapText += `${parsed.time_gap.minutes}${parsed.time_gap.minutes === 1 ? timeTexts.minutes : timeTexts.minutes}`;
                }
                if (timeGapText) {
                    timeGapText += timeTexts.articles;
                }

                gapDiv.textContent = timeGapText;
                gapDiv.style.color = '#bbb';

                const timeDiv = document.createElement('div');
                timeDiv.classList.add('time');
                timeDiv.appendChild(datetimeDiv);
                timeDiv.appendChild(gapDiv);

                // Create div for image
                const imageDiv = document.createElement('div');
                const imageElement = document.createElement('img');
                imageElement.src = parsed.image_url;
                imageElement.style.width = '100%';
                imageElement.style.borderRadius = '8px';
                imageDiv.appendChild(imageElement);

                const contentDiv = document.createElement('div');
                contentDiv.textContent = parsed.content;
                contentDiv.style.marginTop = '.5rem';

                const summaryDiv = document.createElement('div');
                summaryDiv.textContent = parsed.summary;
                summaryDiv.style.display = 'none';
                summaryDiv.style.marginTop = '.5rem';

                const articleDiv = document.createElement('div');
                articleDiv.textContent = parsed.article;
                articleDiv.style.display = 'none';
                articleDiv.style.marginTop = '.5rem';

                const wrapperDiv = document.createElement('div');
                wrapperDiv.className = 'message left';
                const assistantMessageDiv = document.createElement('div');
                assistantMessageDiv.className = 'assistant';
                assistantMessageDiv.appendChild(titleDiv);
                // assistantMessageDiv.appendChild(datetimeDiv);
                // assistantMessageDiv.appendChild(gapDiv);
                assistantMessageDiv.appendChild(timeDiv);
                assistantMessageDiv.appendChild(imageDiv);
                assistantMessageDiv.appendChild(contentDiv);
                assistantMessageDiv.appendChild(summaryDiv);
                assistantMessageDiv.appendChild(articleDiv);
                wrapperDiv.appendChild(assistantMessageDiv);
                chatBox.appendChild(wrapperDiv);

                //create query options
                const queryDiv = document.createElement('div');
                queryDiv.classList.add('message', 'right');
                queryDiv.style.marginTop = '0';
                const userDiv = document.createElement('div');
                userDiv.className = 'user';
                const expected = document.createElement('span');
                expected.textContent = 'Expected Questions';
                const question1 = document.createElement('p');
                const question2 = document.createElement('p');
                const question3 = document.createElement('p');

                question1.classList.add('major-news', 'question');
                question2.classList.add('analyze-btc', 'question');
                question3.classList.add('recommend-crypto', 'question');

                if (selectedLanguage === 'kr') {
                    question1.textContent = `암호 화폐 관련 주요 뉴스`;
                    question2.textContent = `비트코인 24시간 스코어 정보`;
                    question3.textContent = `진입하기 좋은 암호 화폐 추천`;
                } else if (selectedLanguage === 'jp') {
                    question1.textContent = `暗号通貨関連の主要ニュース`; // "Major news about cryptocurrencies" in Japanese
                    question2.textContent = `ビットコインの過去24時間のスコアデータ`; // "What is the Goya Score?" in Japanese
                    question3.textContent = `エントリーに適した暗号通貨のおすすめ`; // "Recommended cryptocurrencies to enter" in Japanese
                } else if (selectedLanguage === 'en') {
                    question1.textContent = `Major news about cryptocurrencies`;
                    question2.textContent = `Bitcoin　24 hours score data`;
                    question3.textContent = `Recommended cryptocurrencies to enter`;
                }

                question1.addEventListener('click', function() {
                    executeQuestion(this);
                });
                question2.addEventListener('click', function() {
                    executeQuestion(this);
                });
                question3.addEventListener('click', function() {
                    executeQuestion(this);
                });

                // userDiv.appendChild(expected);
                userDiv.appendChild(question1);
                userDiv.appendChild(question2);
                userDiv.appendChild(question3);
                queryDiv.appendChild(userDiv);
                chatBox.appendChild(queryDiv);

            }
            else if (parsedResponse.data.format_type === 'default') {
                // Add the assistant's message to the chat box
                const wrapperDiv = document.createElement('div');
                wrapperDiv.className = 'message left';
                const assistantMessageDiv = document.createElement('div');
                assistantMessageDiv.className = 'assistant';
                assistantMessageDiv.innerHTML = parsedResponse.data.content.replace(/\n/g, '<br>');
                wrapperDiv.appendChild(assistantMessageDiv);
                chatBox.appendChild(wrapperDiv);

                // Retrieve the object and parse it
                const userMessage = {
                    role: "user",
                    content: message
                };

                conversation.push(userMessage);
                const assistantMessage = {
                    role: "assistant",
                    content: data.responseText
                };
                conversation.push(assistantMessage);
                console.log("conversation: ", conversation);
            }

            // Hide the "Generating..." message
            generatingWrapper.style.display = 'none';

            // Fetch user tokens
            await fetchUserCharge();

            if (data.summary != null) {
                conversation = [];
                if (data.summary) {
                    conversation.push(data.summary);
                }
            }

            //finally set the maxUsage input value
            // Convert to a number, default to 0 if conversion results in NaN
            maxUsageInput.value = isNaN(Number(data.maxUsage)) ? 0 : Number(data.maxUsage);
            // Scroll to the bottom of the chat box
            chatBox.scrollTop = chatBox.scrollHeight;
        } else {
            // Hide the "Generating..." message and show error message
            // generatingMessage.style.display = 'none';
            generatingWrapper.style.display = 'none';
            const errorMessageDiv = document.createElement('div');
            errorMessageDiv.className = 'error-message';
            switch (selectedLanguage) {
                case 'kr':
                    errorMessageDiv.textContent = '응답 생성에 실패하였습니다.';
                    break;
                case 'jp':
                    errorMessageDiv.textContent = '回答生成に失敗しました。';
                    break;
                case 'en':
                    errorMessageDiv.textContent = 'Failed to generate message.';
                    break;
                default:
                    errorMessageDiv.textContent = 'Something probably went wrong.';
                    break;
            }
            chatBox.appendChild(errorMessageDiv);
        }
    } catch (error) {
        // Hide the "Generating..." message and show error message
        generatingWrapper.style.display = 'none';
        const errorMessageDiv = document.createElement('div');
        errorMessageDiv.className = 'error-message';
        switch (selectedLanguage) {
            case 'kr':
                errorMessageDiv.textContent = '응답 생성에 실패하였습니다.';
                break;
            case 'jp':
                errorMessageDiv.textContent = 'メッセージ生成に失敗しました。';
                break;
            case 'en':
                errorMessageDiv.textContent = 'Failed to generate message.';
                break;
            default:
                errorMessageDiv.textContent = 'Something probably went wrong.';
                break;
        }
        chatBox.appendChild(errorMessageDiv);
        console.error('Error:', error);
    } finally {
        messageInput.readOnly = false;
        chatForm.classList.remove('locked');
        // messageInput.classList.remove('locked');
        document.querySelectorAll('.question').forEach(div => {
            div.classList.remove('locked');
        });
        console.log("symbols: ", recommendedSymbols);
        console.log("articles: ", revealedArticles);
    }
}

let drawChart = (priceMovement, scoreMovement, labels, canvas) => {

    const ctx = canvas.getContext('2d');

    const firstLabel = labels[0];
    const middleLabel = labels[Math.floor(labels.length / 2)];
    const lastLabel = labels[labels.length - 1];

    const customLabels = labels.map((label, index) => {
        if (label === firstLabel || label === middleLabel || label === lastLabel) {
            return label; // Keep first, middle, and last labels
        }
        return ''; // Hide other labels by making them empty strings
    });
    //console.log('custom labels: ', customLabels);

    const formatDate = (isoString) => {
        if (!isoString) return ''; // Handle empty strings

        // Split the date and time parts from the ISO string
        const [datePart, timePartWithOffset] = isoString.split('T');

        // Extract the year, month, and day from the date part
        const [year, month, day] = datePart.split('-');

        // Extract the time part by removing the timezone offset
        const timePart = timePartWithOffset.split(/[+-]/)[0]; // Split by '+' or '-' to remove timezone
        const [hours, minutes] = timePart.split(':'); // Extract hours and minutes from the time part
        // Return the formatted string as MM/DD HH:mm
        return `${month}/${day} ${hours}:${minutes}`;
    };

    // console.log("customLabels: ", customLabels);

    const myChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: customLabels,
            datasets: [
                {
                    label: 'Market Price',
                    data: priceMovement,
                    borderColor: 'rgba(153, 102, 255, 1)', // Color of the first line
                    borderWidth: 2,
                    fill: false, // Don't fill under the line
                    yAxisID: 'y-right',
                    pointRadius: 1,
                    pointHoverRadius: 3,
                    hitRadius: 10,
                    tension: 0.4
                },
                {
                    label: 'Goya Score',
                    data: scoreMovement,
                    borderColor: 'rgba(75, 192, 192, 1)', // Color of the second line
                    borderWidth: 2,
                    fill: false, // Don't fill under the line
                    yAxisID: 'y-left',
                    pointRadius: 1,
                    pointHoverRadius: 3,
                    hitRadius: 10,
                    tension: 0.4
                }
            ]
        },
        options: {
            scales: {
                'y-right': { // Left y-axis for Price Movement
                    type: 'linear',
                    position: 'right',

                },
                'y-left': { // Right y-axis for Score Movement
                    type: 'linear',
                    position: 'left',

                },
                x: {
                    display: true,
                    ticks: {
                        callback: function(value, index) {
                            if (customLabels[index]) {
                                return formatDate(customLabels[index]);
                                // return customLabels[index];
                            }
                            return ''; // Hide empty labels
                        },
                        autoSkip: false,
                        maxTicksLimit: 3
                    }
                }
            },
            plugins: {
                tooltip: {
                    enabled: true,
                    mode: 'nearest',
                    intersect: true,
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
                intersect: true,
            },
        }
    });
    // const myChart = new Chart(ctx, {
    //     type: 'line',
    //     data: {
    //         labels: customLabels,
    //         datasets: [
    //             {
    //                 label: 'Market Price',
    //                 data: priceMovement,
    //                 borderColor: 'rgba(153, 102, 255, 1)', // Color of the first line
    //                 borderWidth: 2,
    //                 fill: false, // Don't fill under the line
    //                 yAxisID: 'y-left',
    //                 pointRadius: 1,
    //                 pointHoverRadius: 3,
    //                 tension: 0.4
    //             },
    //             {
    //                 label: 'Goya Score',
    //                 data: scoreMovement,
    //                 borderColor: 'rgba(75, 192, 192, 1)', // Color of the second line
    //                 borderWidth: 2,
    //                 fill: false, // Don't fill under the line
    //                 yAxisID: 'y-right',
    //                 pointRadius: 1,
    //                 pointHoverRadius: 3,
    //                 tension: 0.4
    //             }
    //         ]
    //     },
    //     options: {
    //         scales: {
    //             'y-left': { // Left y-axis for Price Movement
    //                 type: 'linear',
    //                 position: 'left',
    //             },
    //             'y-right': { // Right y-axis for Score Movement
    //                 type: 'linear',
    //                 position: 'right',
    //             },
    //             x: {
    //                 display: true,
    //                 ticks: {
    //                     callback: function(value, index) {
    //                         if (customLabels[index]) {
    //                             return formatDate(customLabels[index]);
    //                         }
    //                         return ''; // Hide empty labels
    //                     },
    //                     autoSkip: true,  // Ensure that labels are auto-skipped if they don’t fit
    //                     autoSkipPadding: 10,  // Add padding between skipped labels
    //                     maxRotation: 0,  // Prevents labels from rotating
    //                     minRotation: 0,  // Ensures labels are not rotated
    //                     maxTicksLimit: 3,  // Limits the number of ticks displayed
    //                 },
    //                 afterFit: function(scale) {
    //                     scale.paddingRight = 20;  // Adjust padding on the right to prevent cut-offs
    //                     scale.paddingLeft = 20;   // Adjust padding on the left to prevent cut-offs
    //                 }
    //             }
    //         },
    //         plugins: {
    //             tooltip: {
    //                 callbacks: {
    //                     title: function(tooltipItems) {
    //                         return tooltipItems[0].label;  // Display the label when hovering
    //                     }
    //                 }
    //             },
    //             legend: {
    //                 display: false
    //             }
    //         },
    //         interaction: {
    //             mode: 'point',
    //             axis: 'x',
    //             intersect: true
    //         }
    //     }
    // });
}

function formatDateTimeToWords(dateTimeString) {
    // Split the input string into date part and time part
    const [dateString, timePart] = dateTimeString.split('_'); // ["20240908", "AM"]

    // Extract year, month, and day from the date string
    const year = dateString.substring(0, 4);   // "2024"
    const month = dateString.substring(4, 6);  // "09"
    const day = dateString.substring(6, 8);    // "08"

    // Convert month number to month name
    const monthNumber = parseInt(month, 10);
    const monthNames = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];

    // Get month name from the month number
    const monthName = monthNumber <= 12 ? monthNames[monthNumber - 1] : '';

    // Convert day to a number to handle suffix
    const dayNumber = parseInt(day, 10);

    // Add the appropriate suffix to the day
    const suffix = (dayNumber === 1 || dayNumber === 21 || dayNumber === 31) ? 'st' :
        (dayNumber === 2 || dayNumber === 22) ? 'nd' :
            (dayNumber === 3 || dayNumber === 23) ? 'rd' : 'th';

    // Format the final string with the prefix and time first
    // Return the formatted string
    return monthName ? `Goya AI Market Analysis, ${timePart} ${monthName} ${dayNumber}${suffix}` : 'Invalid Date';
}

let executeQuestion = (elem) => {
    if (elem.textContent !== '') {
        message = elem.textContent;
        console.log("user message: ", message);
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
